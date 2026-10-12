<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code drawn on this server, as an image a page can show directly.
 *
 * The two-factor setup screens used to load the code from a public QR service,
 * which meant the otpauth:// address, and the shared secret in it, was sent to
 * a third party every time someone set up two-factor sign-in. Drawn here as
 * SVG it needs no extension and never leaves the page.
 */
class QrCode
{
    public static function dataUri(string $text, int $size = 200): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
