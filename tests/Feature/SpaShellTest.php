<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Locks in the Inertia → decoupled Vue SPA conversion:
 * a Laravel catch-all (`Route::fallback`) serves the SPA blade shell for every
 * unmatched path, while the framework health endpoint (`/up`) is not shadowed.
 */
class SpaShellTest extends TestCase
{
    public function test_spa_shell_is_served_at_root(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_unknown_paths_fall_back_to_the_spa(): void
    {
        $this->withoutVite();

        $this->get('/competitions/anything')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_deep_link_paths_fall_back_to_the_spa(): void
    {
        $this->withoutVite();

        $this->get('/match/123')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_fallback_route_is_named_spa(): void
    {
        $this->withoutVite();

        $this->get(route('spa', ['fallbackPlaceholder' => 'standings']))
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_health_endpoint_is_not_shadowed_by_the_spa_fallback(): void
    {
        $this->withoutVite();

        $this->get('/up')
            ->assertOk()
            ->assertDontSee('id="app"', false);
    }
}
