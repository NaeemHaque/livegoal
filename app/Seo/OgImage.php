<?php

namespace App\Seo;

/**
 * Renders a 1200x630 Open Graph card (PNG bytes) for an entity — an on-brand
 * eyebrow / title / subtitle layout, so a shared match link shows the teams and
 * score instead of the generic site card. Returns null when GD or a TrueType
 * font is unavailable, so the caller can fall back to the static image.
 */
class OgImage
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    /**
     * @return string|null PNG bytes, or null when the image can't be rendered.
     */
    public function card(string $eyebrow, string $title, string $subtitle): ?string
    {
        if (! \function_exists('imagecreatetruecolor')) {
            return null;
        }

        $bold = $this->fontPath(['Arial Bold.ttf', 'DejaVuSans-Bold.ttf', 'LiberationSans-Bold.ttf']);
        $regular = $this->fontPath(['Arial.ttf', 'DejaVuSans.ttf', 'LiberationSans-Regular.ttf']);

        if ($bold === null || $regular === null) {
            return null;
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            return null;
        }

        $bg = (int) imagecolorallocate($image, 10, 13, 18);
        $accent = (int) imagecolorallocate($image, 198, 255, 58);
        $text = (int) imagecolorallocate($image, 230, 234, 240);
        $muted = (int) imagecolorallocate($image, 138, 147, 163);
        $red = (int) imagecolorallocate($image, 255, 61, 61);

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $bg);
        imagefilledrectangle($image, 0, 0, 14, self::HEIGHT, $accent);

        // "LiveGoal" wordmark, top-left, with the live dot.
        $brandSize = 34;
        imagettftext($image, $brandSize, 0, 70, 118, $text, $bold, 'Live');
        $liveWidth = $this->textWidth($brandSize, $bold, 'Live');
        imagettftext($image, $brandSize, 0, 70 + $liveWidth, 118, $accent, $bold, 'Goal');
        $goalWidth = $this->textWidth($brandSize, $bold, 'Goal');
        imagefilledellipse($image, 70 + $liveWidth + $goalWidth + 18, 100, 15, 15, $red);

        $maxWidth = self::WIDTH - 160;

        if ($eyebrow !== '') {
            $this->centeredText($image, strtoupper($eyebrow), 28, $bold, $muted, 250, $maxWidth);
        }

        $this->centeredText($image, $title, 64, $bold, $text, 362, $maxWidth);

        if ($subtitle !== '') {
            $this->centeredText($image, $subtitle, 40, $regular, $accent, 452, $maxWidth);
        }

        $this->centeredText($image, 'Free live football scores · livegoal.win', 26, $regular, $muted, 560, $maxWidth);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    /**
     * Draw horizontally-centered TrueType text, shrinking the font so it always
     * fits within the given max width.
     *
     * @param  \GdImage  $image
     */
    private function centeredText($image, string $string, int $size, string $font, int $color, int $baseline, int $maxWidth): void
    {
        $width = $this->textWidth($size, $font, $string);

        if ($width > $maxWidth && $width > 0) {
            $size = (int) floor($size * $maxWidth / $width);
            $width = $this->textWidth($size, $font, $string);
        }

        $x = (int) ((self::WIDTH - $width) / 2);

        imagettftext($image, $size, 0, $x, $baseline, $color, $font, $string);
    }

    /**
     * Rendered pixel width of a TrueType string (0 when metrics can't be read).
     */
    private function textWidth(int $size, string $font, string $string): int
    {
        $box = imagettfbbox($size, 0, $font, $string);

        if ($box === false) {
            return 0;
        }

        $right = is_numeric($box[2]) ? (int) $box[2] : 0;
        $left = is_numeric($box[0]) ? (int) $box[0] : 0;

        return $right - $left;
    }

    /**
     * First readable TrueType font from the candidates across common macOS and
     * Linux font directories, or null when none is available.
     *
     * @param  list<string>  $candidates
     */
    private function fontPath(array $candidates): ?string
    {
        $directories = [
            '/System/Library/Fonts/Supplemental/',
            '/System/Library/Fonts/',
            '/Library/Fonts/',
            '/usr/share/fonts/truetype/dejavu/',
            '/usr/share/fonts/truetype/liberation/',
        ];

        foreach ($candidates as $candidate) {
            foreach ($directories as $directory) {
                $path = $directory.$candidate;

                if (is_readable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
