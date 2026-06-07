---
name: test-runner
description: PHPUnit testing patterns for the socplay Laravel app — how to scaffold, write, and run feature/unit tests with factories, RefreshDatabase, Inertia assertions, and HTTP helpers. Use when writing or running tests, debugging failing tests, or adding coverage for new Laravel code.
---

# test-runner

PHPUnit conventions for **socplay** (Laravel 13, PHPUnit 12). Tests run against an **in-memory SQLite** DB (see `phpunit.xml`): cache=array, queue=sync, mail=array — fully isolated, no external services.

## Running tests

```bash
php artisan test --compact                                   # everything
php artisan test --compact tests/Feature/ProfileTest.php     # one file
php artisan test --compact --filter=test_user_can_update     # one method/name
```

Run the narrowest filter while iterating; run the file (or suite) once green. CI runs `php artisan test --compact`.

## Scaffolding

```bash
php artisan make:test ProfileTest            # feature test (default) -> tests/Feature
php artisan make:test CalculatorTest --unit  # pure unit test          -> tests/Unit
```

Always `--phpunit` is implied by this project (PHPUnit, **not Pest**). If you ever see Pest syntax, convert it to a PHPUnit class.

## Anatomy of a feature test

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->put(route('profile.update'))->assertRedirect(route('login'));
    }

    public function test_email_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'X', 'email' => ''])
            ->assertSessionHasErrors('email');
    }
}
```

## Key tools

- **Factories** — `User::factory()->create()`, `->make()`, `->count(3)->create()`. Prefer existing factory **states** before setting attributes manually.
- **Auth** — `$this->actingAs($user)`.
- **HTTP** — `$this->get/post/put/patch/delete(route('name'), $data)`. Always reference routes with `route()`, never hardcoded paths.
- **Status/redirect** — `assertOk()`, `assertStatus(403)`, `assertForbidden()`, `assertRedirect()`, `assertSessionHasErrors([...])`.
- **Database** — `assertDatabaseHas/Missing($table, [...])`, `assertModelExists/Missing($model)`.
- **Inertia** (page assertions):
  ```php
  use Inertia\Testing\AssertableInertia as Assert;

  $this->get(route('home'))->assertInertia(
      fn (Assert $page) => $page->component('Welcome')->has('auth.user')
  );
  ```

## Coverage expectations

Every new behavior needs: the **happy path**, an **authorization** guard (unauthorized → 403/redirect), **validation** failures (invalid input → errors), and any meaningful **edge case**. Keep tests independent — `RefreshDatabase` resets state between methods; never rely on cross-test state.

## Do / Don't

- Do extend `Tests\TestCase` and seed data in `setUp()` after `parent::setUp()`.
- Do not modify application code to make a test pass — if app code is wrong, report it.
- Do not remove or weaken existing tests without approval.
