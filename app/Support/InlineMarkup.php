<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A sentence that is allowed to carry a little markup, and nothing else.
 *
 * WHY THIS EXISTS
 *
 * The public guide pages print translated prose, and that prose genuinely
 * carries tags: "Certificates are issued by <strong>Let's Encrypt</strong>"
 * is one key, not three, because a translator has to be able to move the
 * emphasis to wherever their language puts it. Eighty-six of the seeded
 * values on /ssl and /guides/mail-setup hold a tag for that reason.
 *
 * Those sentences were therefore printed with {!! !!}, and the values do not
 * come only from lang/en/*.php - DbTranslationLoader layers dynamic_translations
 * over the files, and that table is written by the admin translation editor
 * (TranslationController::saveTranslation, bulkSave, aiTranslate, import) with
 * no sanitising anywhere in the write path. An operator holding nothing but
 * manage_settings could store
 *
 *     Issued by <strong>Let's Encrypt</strong>.<script>alert(1)</script>
 *
 * and have it execute for every unauthenticated visitor to /ssl. Reproduced
 * through the real editor route before this class was written.
 *
 * WHY THE GUARD IS ON RENDER AND NOT ON WRITE
 *
 * Sanitising on write would have to be repeated in four controller methods
 * and in every seed migration, would leave whatever is already in the table
 * dangerous, and would be reopened by the fifth write path somebody adds. The
 * render side is one gate that every source passes through - editor, importer,
 * AI translation, seed migration, language file - and it is the only place
 * that knows the value is about to become HTML.
 *
 * WHY NOT SPLIT THE SENTENCES SO THE MARKUP LIVES IN THE BLADE
 *
 * Because word order is not a constant. "Issued by <strong>Let's Encrypt</strong>"
 * becomes "<strong>Let's Encrypt</strong> tarafından verilir" in Turkish: the
 * emphasised words move to the front and the verb goes last. A blade that
 * hard-codes where the <strong> opens can only be right in one language. It
 * also takes away the link an operator legitimately wants inside a sentence.
 *
 * HOW IT WORKS
 *
 * Escape everything, then put back a fixed, tiny list of literal forms. The
 * escape pass leaves no raw '<' or '>' in the string at all, so restoration is
 * the only thing that can produce a tag, and restoration matches exact literals.
 * There is no parser to confuse and no attribute to inject into: the paired
 * tags are restored only in their bare form, and the one tag that carries an
 * attribute - <a href="..."> - is restored only after its URL is decoded and
 * checked against a scheme allow-list, then written back escaped. Its one
 * optional extra - the literal target="_blank" rel="noopener" pair an
 * off-site link wants - is matched as a fixed string rather than parsed, so
 * there is still nowhere for a second attribute to go.
 *
 * Anything outside the list stays escaped and is shown to the reader as text,
 * which is the behaviour an operator who typed the wrong tag should see.
 *
 * The result is an HtmlString, so call sites read {{ trans_markup(...) }} with
 * no {!! !!} anywhere: a reviewer grepping for raw echoes in these views finds
 * none, and the only way to reach the page unescaped is through this class.
 */
final class InlineMarkup
{
    /**
     * Tags that may appear in a sentence, bare and paired.
     *
     * strong, em and code are what the seeded values use; b, i and small are
     * the ones an operator reaches for meaning the same thing; span is what
     * sections/hero.blade.php styles the second half of its headline with.
     * None of them may carry an attribute, so none of them can carry an
     * event handler.
     */
    private const TAGS = 'b|code|em|i|small|span|strong';

    /** Schemes a restored <a href> may use. */
    private const URL_SCHEMES = '~^(?:https?://|mailto:)~i';

    /**
     * The only attribute pair a restored link may carry besides its href,
     * matched and re-emitted as this exact literal. An off-site link wants
     * it; there is no third spelling to support and no parsing to get wrong.
     */
    private const LINK_EXTERNAL_ATTRS = ' target="_blank" rel="noopener"';

    public static function render(?string $value): HtmlString
    {
        if ($value === null || $value === '') {
            return new HtmlString('');
        }

        // Double encoding is off so that a value written with an entity in it
        // - &amp;, &nbsp; - still reads as that character, which is what the
        // {!! !!} it replaces did. It costs nothing in safety: doubleEncode
        // only decides what happens to '&', and htmlspecialchars escapes
        // '<', '>', '"' and '\'' either way. After this line the string holds
        // no raw angle bracket of any kind.
        $html = e($value, false);

        $html = self::pairedTags($html);

        // The one void tag the language files use, in each of the three
        // spellings an author might write.
        $html = preg_replace('~&lt;br\s*/?\s*&gt;~i', '<br>', $html) ?? $html;

        return new HtmlString(self::anchors($html));
    }

    /**
     * The bare inline tags, restored in balanced pairs.
     *
     * Only the bare form matches - "&lt;strong&gt;" and never
     * "&lt;span onmouseover=..." - so an allowed tag cannot be used as a
     * carrier for an attribute; anything with one stays escaped.
     *
     * A closing tag is restored only when an opening one before it was, which
     * keeps the output balanced. An unmatched end tag is inert in a browser,
     * but a value that had its opener refused should not be able to leave a
     * live "</strong>" in the page either.
     */
    private static function pairedTags(string $html): string
    {
        $open = [];

        return preg_replace_callback(
            '~&lt;(/?)('.self::TAGS.')&gt;~i',
            static function (array $match) use (&$open): string {
                $tag = strtolower($match[2]);

                if ($match[1] === '') {
                    $open[$tag] = ($open[$tag] ?? 0) + 1;

                    return '<'.$tag.'>';
                }

                if (($open[$tag] ?? 0) < 1) {
                    return $match[0];
                }

                $open[$tag]--;

                return '</'.$tag.'>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Links, which are the only restored tag with an attribute.
     *
     * The href is decoded before it is judged, because a browser decodes
     * attribute entities too and "&#106;avascript:alert(1)" would otherwise
     * slip past a raw string comparison. It is judged by what it starts with
     * rather than by what it must not contain, so "java\tscript:" and every
     * other spelling of the same idea fails by default rather than by being
     * listed. What goes back into the page is the decoded URL re-escaped, so
     * the attribute cannot be broken out of whatever the input looked like.
     *
     * The href capture stops at the quote that closes it, so a second
     * attribute cannot be swallowed into the URL: an "<a href=... onclick=...>"
     * fails to match the shape entirely and stays escaped.
     *
     * A link whose URL is refused is left escaped and prints as text. That is
     * deliberate: the operator who typed it sees their mistake on the page
     * instead of getting a link that silently does nothing.
     */
    private static function anchors(string $html): string
    {
        $opened = 0;

        $html = preg_replace_callback(
            '~&lt;a href=&quot;((?:(?!&quot;|&gt;).)*)&quot;( target=&quot;_blank&quot; rel=&quot;noopener&quot;)?&gt;~i',
            static function (array $match) use (&$opened): string {
                $url = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));

                // A leading '//' is a link to another host wearing the clothes
                // of a relative path. https:// says the same thing out loud.
                $relative = (str_starts_with($url, '/') || str_starts_with($url, '#'))
                    && ! str_starts_with($url, '//');

                if (! $relative && ! preg_match(self::URL_SCHEMES, $url)) {
                    return $match[0];
                }

                $opened++;

                $attrs = ($match[2] ?? '') !== '' ? self::LINK_EXTERNAL_ATTRS : '';

                return '<a href="'.e($url).'"'.$attrs.'>';
            },
            $html
        ) ?? $html;

        // Only worth restoring if something opened. A closing tag on its own
        // would be inert, but leaving it escaped keeps the page honest about
        // the link that was refused.
        return $opened > 0
            ? (preg_replace('~&lt;/a&gt;~i', '</a>', $html) ?? $html)
            : $html;
    }
}
