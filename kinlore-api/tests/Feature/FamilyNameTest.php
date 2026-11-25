<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\FamilyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The family name: set at setup, changed by the admin, seen by members only (docs/reference/api.md section 3).
 */
class FamilyNameTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = [
        'name' => 'Anna Example',
        'email' => 'anna@example.test',
        'password' => 'correct horse 42',
        'password_confirmation' => 'correct horse 42',
        'setup_token' => 'the-secret',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.setup_token' => 'the-secret', 'app.name' => 'Kinlore']);
    }

    private function pulledName(): string
    {
        return $this->postJson('/api/v1/sync/pull', ['lastPulledAt' => null])->assertOk()->json('family_name');
    }

    public function test_setup_stores_the_given_name_or_suggests_the_admins_full_name(): void
    {
        // The field is optional: only the other fields are `required`.
        $form = $this->get('/setup')->assertOk()->assertSee('name="family_name"', false)->getContent();
        $this->assertSame(1, preg_match('/<input id="family_name"[^>]*>/', $form, $input));
        $this->assertStringNotContainsString('required', $input[0]);
        $this->assertSame(5, substr_count($form, ' required'));

        $this->post('/setup', self::FORM + ['family_name' => 'The Smiths'])->assertOk()->assertSee('The Smiths is set up');
        $this->assertSame('The Smiths', app(FamilyService::class)->name());

        User::query()->delete();
        $this->post('/setup', self::FORM + ['family_name' => ''])->assertOk()->assertSee('Anna Example Family is set up');

        // Stored at setup: a later change of the admin's own name does not rename the family.
        User::first()->update(['name' => 'Anna Other']);
        $this->assertSame('Anna Example Family', app(FamilyService::class)->name());
    }

    public function test_a_server_from_before_this_feature_suggests_the_admins_full_name(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(['name' => 'Ben van  Dijk ']));

        $this->assertSame('Ben van Dijk Family', $this->pulledName());

        // A very long name still fits the limit of the name.
        $this->assertSame(60, mb_strlen(FamilyService::suggest(str_repeat('a', 80))));
    }

    public function test_only_the_admin_renames_and_members_get_the_name_with_the_next_pull(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->family()->create();

        Sanctum::actingAs($member);
        $this->putJson('/api/v1/family', ['name' => 'Hackers'])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/family', ['name' => '  Zouaghi Family  '])->assertOk()->assertJsonPath('data.family_name', 'Zouaghi Family');
        $this->putJson('/api/v1/family', ['name' => ''])->assertStatus(422);
        $this->putJson('/api/v1/family', ['name' => '   '])->assertStatus(422);
        $this->putJson('/api/v1/family', ['name' => str_repeat('a', 61)])->assertStatus(422);

        Sanctum::actingAs($member);
        $this->assertSame('Zouaghi Family', $this->pulledName());
    }

    public function test_public_endpoints_and_pages_do_not_show_the_family_name(): void
    {
        User::factory()->admin()->create();
        app(FamilyService::class)->rename('Zouaghi Family');

        $this->getJson('/api/auth/setup-status')->assertOk()->assertJsonPath('data.instance_name', 'Kinlore');
        $this->getJson('/api/health')->assertOk()->assertDontSee('Zouaghi');
        $this->get('/')->assertOk()->assertDontSee('Zouaghi');
        $this->get('/invite/unknown')->assertNotFound()->assertDontSee('Zouaghi');
        $this->postJson('/api/v1/sync/pull', ['lastPulledAt' => null])->assertUnauthorized();
    }
}
