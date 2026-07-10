<?php

namespace App\Services\Mail;

/**
 * Minimal, dependency-free MIME parser for incoming ticket mail.
 *
 * Handles: header unfolding + RFC2047 decoding, nested multiparts,
 * base64 / quoted-printable transfer encodings, charset conversion to
 * UTF-8, text/plain preference with text/html fallback, attachments.
 */
class RawMessageParser
{
    /**
     * @return array{
     *   subject: string,
     *   from_email: string,
     *   from_name: string,
     *   message_id: ?string,
     *   auto_submitted: bool,
     *   body_text: string,
     *   attachments: array<int, array{filename: string, mime: string, content: string}>
     * }
     */
    public function parse(string $raw): array
    {
        [$headerBlock, $body] = $this->splitHeadersBody($raw);
        $headers = $this->parseHeaders($headerBlock);

        [$fromEmail, $fromName] = $this->parseAddress($headers['from'] ?? '');

        $parts = [];
        $this->walkPart($headers, $body, $parts);

        $bodyText = $this->pickBodyText($parts);
        $attachments = array_values(array_filter($parts, fn ($p) => $p['is_attachment']));

        return [
            'subject'        => $this->decodeHeader($headers['subject'] ?? ''),
            'from_email'     => strtolower($fromEmail),
            'from_name'      => $fromName,
            'message_id'     => isset($headers['message-id']) ? trim($headers['message-id'], " \t<>") : null,
            'auto_submitted' => $this->isAutoSubmitted($headers),
            'body_text'      => trim($bodyText),
            'attachments'    => array_map(fn ($p) => [
                'filename' => $p['filename'],
                'mime'     => $p['mime'],
                'content'  => $p['content'],
            ], $attachments),
        ];
    }

    // -------------------------------------------------------------------

    private function splitHeadersBody(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");
        if ($pos === false) {
            return [$raw, ''];
        }

        return [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    /** @return array<string, string> lowercase header name => raw value */
    private function parseHeaders(string $block): array
    {
        $headers = [];
        $current = null;
        foreach (explode("\n", $block) as $line) {
            if ($current !== null && ($line !== '' && ($line[0] === ' ' || $line[0] === "\t"))) {
                $headers[$current] .= ' ' . trim($line); // unfold
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $current = strtolower(trim($name));
                // keep the FIRST occurrence (Received etc. repeat; we only read singletons)
                if (!isset($headers[$current])) {
                    $headers[$current] = trim($value);
                } else {
                    $current = null;
                }
            }
        }

        return $headers;
    }

    private function decodeHeader(string $value): string
    {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded !== false ? trim($decoded) : trim($value);
    }

    /** @return array{0: string, 1: string} [email, name] */
    private function parseAddress(string $value): array
    {
        $value = $this->decodeHeader($value);

        if (preg_match('/<([^>]+)>/', $value, $m)) {
            $email = trim($m[1]);
            $name  = trim(str_replace($m[0], '', $value), " \t\"'");
            return [$email, $name];
        }

        return [trim($value), ''];
    }

    private function isAutoSubmitted(array $headers): bool
    {
        $auto = strtolower($headers['auto-submitted'] ?? 'no');
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }
        $precedence = strtolower($headers['precedence'] ?? '');
        if (in_array($precedence, ['bulk', 'junk', 'auto_reply', 'list'], true)) {
            return true;
        }
        if (isset($headers['x-autoreply']) || isset($headers['x-autorespond'])) {
            return true;
        }
        if (isset($headers['x-auto-response-suppress'])) {
            return false; // suppress-header on a normal mail is fine
        }

        return false;
    }

    /**
     * Recursively flatten MIME parts into
     * [ ['mime','charset','content','filename','is_attachment'], ... ].
     */
    private function walkPart(array $headers, string $body, array &$parts): void
    {
        $contentType = strtolower($headers['content-type'] ?? 'text/plain');
        preg_match('#^([a-z0-9/+.\-]+)#', trim($contentType), $m);
        $mime = $m[1] ?? 'text/plain';

        if (str_starts_with($mime, 'multipart/')) {
            if (!preg_match('/boundary="?([^";]+)"?/i', $headers['content-type'] ?? '', $bm)) {
                return;
            }
            foreach ($this->splitMultipart($body, $bm[1]) as $chunk) {
                [$subHeaderBlock, $subBody] = $this->splitHeadersBody($chunk);
                $this->walkPart($this->parseHeaders($subHeaderBlock), $subBody, $parts);
            }
            return;
        }

        $content = $this->decodeTransferEncoding($body, strtolower($headers['content-transfer-encoding'] ?? '7bit'));

        $charset = 'UTF-8';
        if (preg_match('/charset="?([^";]+)"?/i', $headers['content-type'] ?? '', $cm)) {
            $charset = strtoupper(trim($cm[1]));
        }

        $disposition = strtolower($headers['content-disposition'] ?? '');
        $filename = null;
        foreach (['content-disposition', 'content-type'] as $h) {
            if (preg_match('/(?:file)?name="?([^";]+)"?/i', $headers[$h] ?? '', $fm)) {
                $filename = $this->decodeHeader($fm[1]);
                break;
            }
        }

        $isAttachment = str_starts_with($disposition, 'attachment')
            || ($filename !== null && !in_array($mime, ['text/plain', 'text/html'], true));

        if (!$isAttachment && in_array($mime, ['text/plain', 'text/html'], true)) {
            $content = $this->toUtf8($content, $charset);
        }

        $parts[] = [
            'mime'          => $mime,
            'content'       => $content,
            'filename'      => $filename ?? '',
            'is_attachment' => $isAttachment,
        ];
    }

    private function splitMultipart(string $body, string $boundary): array
    {
        $chunks = preg_split('/^--' . preg_quote($boundary, '/') . '(?:--)?\s*$/m', "\n" . $body) ?: [];
        // first chunk is the preamble, last (after closing boundary) is epilogue
        array_shift($chunks);

        return array_values(array_filter(array_map(fn ($c) => ltrim($c, "\n"), $chunks), fn ($c) => trim($c) !== ''));
    }

    private function decodeTransferEncoding(string $content, string $encoding): string
    {
        return match ($encoding) {
            'base64'           => base64_decode(preg_replace('/\s+/', '', $content)) ?: '',
            'quoted-printable' => quoted_printable_decode($content),
            default            => $content,
        };
    }

    private function toUtf8(string $content, string $charset): string
    {
        if ($charset === 'UTF-8' || $charset === 'US-ASCII') {
            return $content;
        }

        $converted = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $content);

        return $converted !== false ? $converted : $content;
    }

    private function pickBodyText(array $parts): string
    {
        foreach ($parts as $part) {
            if (!$part['is_attachment'] && $part['mime'] === 'text/plain' && trim($part['content']) !== '') {
                return $part['content'];
            }
        }
        foreach ($parts as $part) {
            if (!$part['is_attachment'] && $part['mime'] === 'text/html' && trim($part['content']) !== '') {
                $html = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#si', '', $part['content']);
                $html = preg_replace('#<br\s*/?>|</p>|</div>#i', "\n", $html);
                return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return '';
    }
}
