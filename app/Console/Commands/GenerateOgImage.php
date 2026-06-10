<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates the 1200x630 Open Graph / Twitter share image (public/og-image.png)
 * so links shared to WhatsApp, X, Discord, Slack, etc. render an on-brand
 * preview card. Run once (committed), or again to rebrand.
 */
class GenerateOgImage extends Command
{
    protected $signature = 'og:generate {--path= : Output path (defaults to public/og-image.png)}';

    protected $description = 'Generate the 1200x630 Open Graph share image';

    private const WIDTH = 1200;

    private const HEIGHT = 630;

    public function handle(): int
    {
        if (! \function_exists('imagecreatetruecolor')) {
            $this->error('The GD extension is required to generate the OG image.');

            return self::FAILURE;
        }

        $path = $this->stringOption('path') ?? public_path('og-image.png');

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            $this->error('Could not allocate the image canvas.');

            return self::FAILURE;
        }

        $bg = (int) imagecolorallocate($image, 10, 13, 18);        // #0A0D12
        $accent = (int) imagecolorallocate($image, 198, 255, 58);  // #C6FF3A
        $text = (int) imagecolorallocate($image, 230, 234, 240);   // #E6EAF0
        $muted = (int) imagecolorallocate($image, 138, 147, 163);  // #8A93A3
        $red = (int) imagecolorallocate($image, 255, 61, 61);      // #FF3D3D

        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, $bg);
        // Brand accent rail down the left edge.
        imagefilledrectangle($image, 0, 0, 14, self::HEIGHT, $accent);

        $bold = $this->fontPath(['Arial Bold.ttf', 'DejaVuSans-Bold.ttf', 'LiberationSans-Bold.ttf']);
        $regular = $this->fontPath(['Arial.ttf', 'DejaVuSans.ttf', 'LiberationSans-Regular.ttf']);

        if ($bold === null || $regular === null) {
            // No TrueType font available — still emit a valid (plainer) image.
            imagestring($image, 5, 80, 290, 'LiveGoal', $text);
            imagestring($image, 5, 80, 320, 'Live Football Scores - World Cup 2026', $muted);
            $this->writePng($image, $path);
            $this->warn('No TrueType font found; generated a basic OG image at '.$path);

            return self::SUCCESS;
        }

        // Wordmark: "Live" in white + "Goal" in the lime accent, drawn together.
        $wordmarkSize = 120;
        $liveWidth = $this->textWidth($wordmarkSize, $bold, 'Live');
        $goalWidth = $this->textWidth($wordmarkSize, $bold, 'Goal');
        $wordmarkWidth = $liveWidth + $goalWidth;

        $startX = (int) ((self::WIDTH - $wordmarkWidth) / 2);
        $baseline = 320;

        imagettftext($image, $wordmarkSize, 0, $startX, $baseline, $text, $bold, 'Live');
        imagettftext($image, $wordmarkSize, 0, $startX + $liveWidth, $baseline, $accent, $bold, 'Goal');

        // Brand "live" dot above the wordmark's trailing edge.
        imagefilledellipse($image, $startX + $wordmarkWidth + 26, $baseline - 86, 26, 26, $red);

        // Lime underline beneath the wordmark.
        imagefilledrectangle($image, $startX, $baseline + 34, $startX + $wordmarkWidth, $baseline + 42, $accent);

        $this->centeredText($image, 'Live Football Scores · World Cup 2026 & Top Leagues', 40, $regular, $text, 430);
        $this->centeredText($image, 'Free · No betting ads · livegoal.win', 30, $regular, $muted, 490);

        $this->writePng($image, $path);

        $this->info('OG image generated at '.$path);

        return self::SUCCESS;
    }

    /**
     * Draw horizontally-centered TrueType text at a given baseline, shrinking the
     * font so it always fits within the canvas margins (no edge clipping).
     *
     * @param  \GdImage  $image
     */
    private function centeredText($image, string $string, int $size, string $font, int $color, int $baseline): void
    {
        $maxWidth = self::WIDTH - 160;
        $width = $this->textWidth($size, $font, $string);

        if ($width > $maxWidth) {
            $size = (int) floor($size * $maxWidth / $width);
            $width = $this->textWidth($size, $font, $string);
        }

        $x = (int) ((self::WIDTH - $width) / 2);

        imagettftext($image, $size, 0, $x, $baseline, $color, $font, $string);
    }

    /**
     * Rendered pixel width of a TrueType string (0 when the metrics can't be read).
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
     * First readable TrueType font from the candidate filenames across common
     * macOS and Linux font directories, or null when none is available.
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

    /**
     * @param  \GdImage  $image
     */
    private function writePng($image, string $path): void
    {
        imagepng($image, $path);
        imagedestroy($image);
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
