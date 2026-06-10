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

    public function test_unknown_paths_return_404_but_still_render_the_spa_shell(): void
    {
        $this->withoutVite();

        // An undefined path shape (no matching SPA route) must surface a real 404
        // status — not a soft-404 200 — while still rendering the shell so the Vue
        // app can show its NotFound page on a direct hit.
        $this->get('/competitions/anything')
            ->assertNotFound()
            ->assertSee('id="app"', false);
    }

    public function test_deep_link_paths_fall_back_to_the_spa(): void
    {
        $this->withoutVite();

        $this->get('/match/123')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_fallback_route_is_named_spa_and_returns_404(): void
    {
        $this->withoutVite();

        // The catch-all is still named `spa`, but now answers unmatched paths with
        // a 404 status (the shell is rendered so the SPA's NotFound page shows).
        $this->get(route('spa', ['fallbackPlaceholder' => 'standings']))
            ->assertNotFound()
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
