<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The og:generate command produces the 1200x630 Open Graph share image used by
 * the SEO shell's og:image / twitter:image tags.
 */
class GenerateOgImageTest extends TestCase
{
    public function test_it_generates_a_1200x630_png(): void
    {
        $path = sys_get_temp_dir().'/livegoal-og-test.png';

        if (file_exists($path)) {
            unlink($path);
        }

        $this->artisan('og:generate', ['--path' => $path])->assertSuccessful();

        $this->assertFileExists($path);

        $size = getimagesize($path);
        $this->assertIsArray($size);
        $this->assertSame(1200, $size[0]);
        $this->assertSame(630, $size[1]);
        $this->assertSame('image/png', $size['mime']);

        unlink($path);
    }
}
