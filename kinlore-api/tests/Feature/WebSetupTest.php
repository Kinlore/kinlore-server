<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * First admin through the web setup page (docs/API_CONTRACT.md sections 3 and 10).
 */
class WebSetupTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = [
        'name' => 'Owner',
        'email' => 'owner@example.test',
        'password' => 'correct horse 42',
        'password_confirmation' => 'correct horse 42',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.setup_token' => null]);
    }

    public function test_home_redirects_to_setup_on_an_empty_instance_and_shows_status_otherwise(): void
    {
        config(['app.name' => 'The Smiths', 'app.url' => 'https://kin.example.test']);
        $this->get('/')->assertRedirect('/setup');

        $user = User::factory()->admin()->create(['email' => 'secret-admin@example.test']);
        $this->get('/')->assertOk()
            ->assertSee('Kinlore is running')
            ->assertSee('The Smiths')
            ->assertSee('https://kin.example.test')
            ->assertDontSee($user->email)
            ->assertDontSee($user->name);
        $this->get('/setup')->assertRedirect('/');
    }

    public function test_setup_form_creates_exactly_one_admin(): void
    {
        $this->get('/setup')->assertOk()->assertSee('name="password_confirmation"', false)->assertDontSee('name="setup_token"', false);

        $this->post('/setup', self::FORM)->assertOk()->assertSee('Your admin account is ready');

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(Role::Admin, User::first()->role);
        $this->assertTrue(User::first()->is_admin);
    }

    public function test_second_setup_is_refused_and_creates_no_user(): void
    {
        $this->post('/setup', self::FORM)->assertOk();

        $this->post('/setup', ['email' => 'intruder@example.test'] + self::FORM)->assertRedirect('/');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.test']);
    }

    public function test_create_first_admin_refuses_inside_the_lock_when_a_user_exists(): void
    {
        User::factory()->admin()->create();

        $this->expectException(ValidationException::class);
        try {
            app(AuthService::class)->createFirstAdmin(self::FORM);
        } finally {
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_setup_token_is_required_and_checked_when_configured(): void
    {
        config(['app.setup_token' => 'the-secret']);
        $this->get('/setup')->assertOk()->assertSee('name="setup_token"', false);

        $this->from('/setup')->post('/setup', self::FORM)->assertRedirect('/setup')->assertSessionHasErrors('setup_token');
        $this->from('/setup')->post('/setup', ['setup_token' => 'wrong'] + self::FORM)->assertRedirect('/setup')->assertSessionHasErrors('setup_token');
        $this->assertDatabaseCount('users', 0);

        $this->post('/setup', ['setup_token' => 'the-secret'] + self::FORM)->assertOk()->assertSee('Your admin account is ready');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_validation_errors_are_shown_on_the_form(): void
    {
        $this->from('/setup')->post('/setup', ['email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other'] + self::FORM)
            ->assertRedirect('/setup');
        // No assertSessionHasErrors() here: with JSON sessions that helper loads the error bag as an
        // object, and the next test request then loses it. The page below proves the errors instead.
        $this->assertDatabaseCount('users', 0);

        // The redirected form shows the messages and keeps the email, never the password.
        $this->get('/setup')->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="not-an-email"', false)
            ->assertDontSee('value="short"', false);
    }

    public function test_csrf_token_is_needed(): void
    {
        $this->app['env'] = 'production'; // the CSRF middleware skips checks while running unit tests

        $this->post('/setup', self::FORM)->assertStatus(419);
        $this->assertDatabaseCount('users', 0);

        $this->withSession(['_token' => 'known-token'])
            ->post('/setup', ['_token' => 'known-token'] + self::FORM)
            ->assertOk();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_pages_escape_the_instance_name(): void
    {
        config(['app.name' => '<script>alert(1)</script>']);

        $this->get('/setup')->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>', false);
    }

    public function test_form_action_uses_the_https_address_behind_a_reverse_proxy(): void
    {
        $page = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'photos.example.com'])
            ->get('/setup')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<form [^>]*action="https://photos\.example\.com/setup"#', $page);
    }

    public function test_done_page_shows_address_and_email_but_not_the_password(): void
    {
        config(['app.url' => 'https://kin.example.test']);

        $this->post('/setup', self::FORM)->assertOk()
            ->assertSee('https://kin.example.test')
            ->assertSee('owner@example.test')
            ->assertDontSee('correct horse 42');
    }

    public function test_setup_post_is_throttled(): void
    {
        foreach (range(1, 5) as $_) {
            $this->post('/setup', ['email' => 'bad'] + self::FORM)->assertRedirect();
        }

        $this->post('/setup', self::FORM)->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
    }
}
