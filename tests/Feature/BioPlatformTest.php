<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ---------------------------------------------------------------------------
 * MySQL verification suite for the Bio Page platform.
 * ---------------------------------------------------------------------------
 * These tests prove the Phase 1 schema behaves correctly on a REAL MySQL /
 * MariaDB server — not just on SQLite. In particular they exercise:
 *
 *  1. `username`: unique index + lowercase model mutator.
 *  2. `profiles`: 1:1 UNIQUE(user_id), FK cascade on user delete, accessors.
 *  3. `links`: reserved-word `order` column ordering, boolean cast of
 *     `is_active`, FK cascade on user delete.
 *  4. Public bio page: shows ACTIVE links in `order` sequence, hides drafts.
 *  5. Registration: rejects duplicate / badly-formed usernames at BOTH the
 *     validation layer and the database layer.
 *
 * Driver selection (see phpunit.xml): the suite runs against whatever
 * DB_CONNECTION phpunit sets. Locally that is MySQL (bio_page_testing);
 * in CI without MySQL you can override with DB_CONNECTION=sqlite.
 * ---------------------------------------------------------------------------
 */
class BioPlatformTest extends TestCase
{
    use RefreshDatabase;

    /** Build a fully-populated demo account. */
    private function makeUser(string $username = 'hadeel'): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'username' => $username,
            'email' => $username . '@example.com',
        ]);

        $user->profile()->create([
            'display_name' => 'Hadeel Demo',
            'bio' => 'Software Engineer & Graphic Designer',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
        ]);

        $user->links()->createMany([
            ['title' => 'Second Link', 'url' => 'https://second.example', 'order' => 1, 'is_active' => true],
            ['title' => 'Hidden Draft', 'url' => 'https://draft.example', 'order' => 2, 'is_active' => false],
            ['title' => 'First Link', 'url' => 'https://first.example', 'order' => 0, 'is_active' => true],
        ]);

        return $user->fresh(['profile', 'links']);
    }

    /* ================= USERNAME (unique + lowercase) ====================== */

    public function test_username_is_lowercased_by_the_model_mutator(): void
    {
        $user = User::factory()->create(['username' => 'HaDeEl_Mix-99']);

        $this->assertSame('hadeel_mix-99', $user->fresh()->username);
    }

    public function test_duplicate_username_violates_the_unique_index(): void
    {
        $this->makeUser('hadeel');

        // The model mutator lowercases BEFORE insert, so 'HADEEL' collides
        // with 'hadeel' at the DATABASE layer (unique index), not just in
        // application validation — last line of defence against races.
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['username' => 'HADEEL']);
    }

    public function test_username_uniqueness_is_case_insensitive_at_the_database_level(): void
    {
        $this->makeUser('hadeel');

        // Bypass the model mutator entirely (raw query builder) to prove the
        // COLLATION — utf8mb4_unicode_ci on MySQL — enforces case-insensitive
        // uniqueness on its own. On SQLite this assertion is skipped because
        // SQLite unique indexes are case-sensitive (BINARY collation).
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Case-insensitive unique enforcement is a MySQL-collation guarantee.');
        }

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('users')->insert([
            'name' => 'Raw Insert',
            'username' => 'HADEEL', // distinct bytes, same letters → must collide
            'email' => 'raw@example.com',
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    /* ================= PROFILES (1:1 + cascade + accessor) ================ */

    public function test_one_profile_per_user_is_enforced(): void
    {
        $user = $this->makeUser('hadeel');

        $this->expectException(\Illuminate\Database\QueryException::class);

        // UNIQUE(user_id) must reject a second profile row for the same user.
        Profile::create(['user_id' => $user->id, 'display_name' => 'Second']);
    }

    public function test_deleting_a_user_cascades_to_profile_and_links(): void
    {
        $user = $this->makeUser('hadeel');
        $userId = $user->id;

        $user->delete();

        $this->assertSame(0, Profile::where('user_id', $userId)->count());
        $this->assertSame(0, Link::where('user_id', $userId)->count());
    }

    public function test_header_image_url_falls_back_without_a_file(): void
    {
        $user = $this->makeUser('hadeel');

        $this->assertStringContainsString(
            'unsplash',
            $user->profile->header_image_url,
            'With no stored path, the accessor must return the stock cover.'
        );
    }

    /* ================= LINKS (reserved `order` + boolean cast) ============ */

    public function test_links_come_back_in_manual_order_with_boolean_flags(): void
    {
        $user = $this->makeUser('hadeel');

        $ordered = $user->links()->orderBy('order')->get();

        $this->assertSame(['First Link', 'Second Link', 'Hidden Draft'], $ordered->pluck('title')->all());
        $this->assertSame([true, true, false], $ordered->pluck('is_active')->all());
        $this->assertTrue($ordered->first()->is_active === true, 'is_active must cast to a real boolean');
    }
    /* ================= PUBLIC BIO PAGE ==================================== */

    public function test_bio_page_renders_active_links_in_order_and_hides_drafts(): void
    {
        $this->makeUser('hadeel');

        $response = $this->get('/hadeel');

        $response->assertOk();
        $response->assertSee('Hadeel Demo');
        $response->assertSee('First Link');
        $response->assertSee('Second Link');
        // Drafts are filtered at QUERY level → their URLs never reach the DOM.
        $response->assertDontSee('draft.example');
        // DB-driven gradient reaches the rendered page.
        $response->assertSee('#b88c8f', false);
    }

    public function test_bio_page_404s_for_unknown_username(): void
    {
        $this->get('/no-such-user-xyz')->assertNotFound();
    }

    /* ================= REGISTRATION (validation + DB) ===================== */

    public function test_registration_rejects_reserved_and_duplicate_usernames(): void
    {
        $this->makeUser('hadeel');

        $payload = [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Reserved slug: the route regex would never serve this username.
        $this->post('/register', [...$payload, 'username' => 'dashboard'])
            ->assertSessionHasErrors('username');

        // Bad charset: spaces are not URL-safe.
        $this->post('/register', [...$payload, 'username' => 'not valid!'])
            ->assertSessionHasErrors('username');

        // Duplicate of the seeded demo account.
        $this->post('/register', [...$payload, 'username' => 'hadeel'])
            ->assertSessionHasErrors('username');

        // A clean registration succeeds and lands on the dashboard.
        $this->post('/register', [...$payload, 'username' => 'new_user-1'])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['username' => 'new_user-1']);
    }
}
