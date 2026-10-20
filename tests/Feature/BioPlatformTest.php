<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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
            'email' => $username.'@example.com',
        ]);

        $user->profile()->create([
            'display_name' => 'Hadeel Demo',
            'bio' => 'Software Engineer & Graphic Designer',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
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
        $this->expectException(QueryException::class);

        User::factory()->create(['username' => 'HADEEL']);
    }

    public function test_username_uniqueness_is_case_insensitive_at_the_database_level(): void
    {
        $this->makeUser('hadeel');

        // Bypass the model mutator entirely (raw query builder) to prove the
        // COLLATION — utf8mb4_unicode_ci on MySQL — enforces case-insensitive
        // uniqueness on its own. On SQLite this assertion is skipped because
        // SQLite unique indexes are case-sensitive (BINARY collation).
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Case-insensitive unique enforcement is a MySQL-collation guarantee.');
        }

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
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

        $this->expectException(QueryException::class);

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

    public function test_header_image_url_is_null_when_no_image_is_stored(): void
    {
        $user = $this->makeUser('hadeel');

        $this->assertNull(
            $user->profile->header_image_url,
            'The cover is optional: no stored path must resolve to null, not a stock image.'
        );
    }

    public function test_dashboard_preview_uses_default_hijab_illustration_without_uploaded_cover(): void
    {
        $user = User::factory()->create([
            'username' => 'dashboard-preview',
            'email' => 'dashboard-preview@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.edit'))
            ->assertOk()
            ->assertSee('hijab-portrait.svg');
    }

    public function test_dashboard_shows_manual_hex_inputs_and_outer_page_background_control(): void
    {
        $user = User::factory()->create([
            'username' => 'palette-preview',
            'email' => 'palette-preview@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.edit'))
            ->assertOk()
            ->assertSee('name="bg_color_1"', false)
            ->assertSee('name="bg_color_2"', false)
            ->assertSee('name="text_color"', false)
            ->assertSee('name="page_background_color"', false)
            ->assertSee('لون خلفية الصفحة خلف الجوال');
    }

    public function test_manually_entered_hex_colors_are_saved_and_published(): void
    {
        $user = $this->makeUser('palette-save');

        $this->actingAs($user)
            ->put(route('dashboard.update'), [
                'display_name' => 'Palette Demo',
                'bio' => 'Colors entered as hex',
                'bg_color_1' => '#ABCDEF',
                'bg_color_2' => '#123456',
                'text_color' => '#F0E1D2',
                'page_background_color' => '#DDEEFF',
                'links' => [],
            ])
            ->assertRedirect(route('dashboard.edit'));

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'bg_color_1' => '#ABCDEF',
            'bg_color_2' => '#123456',
            'text_color' => '#F0E1D2',
            'page_background_color' => '#DDEEFF',
        ]);

        $this->get(route('bio.show', 'palette-save'))
            ->assertOk()
            ->assertSee('#ABCDEF', false)
            ->assertSee('#123456', false)
            ->assertSee('#F0E1D2', false);

        $this->get(route('bio.show', 'palette-save'))
            ->assertOk()
            ->assertSee('background-color: #DDEEFF;', false);
    }

    public function test_profile_can_be_saved_without_a_header_image(): void
    {
        $user = $this->makeUser('hadeel');

        $this->actingAs($user)->put(route('dashboard.update'), [
            'display_name' => 'Hadeel Demo',
            'bio' => 'No cover needed',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
            'links' => [],
        ])->assertRedirect(route('dashboard.edit'));

        $this->assertNull($user->profile->fresh()->header_image);
    }

    public function test_remove_header_image_flag_clears_an_existing_cover(): void
    {
        Storage::fake('public');

        $user = $this->makeUser('hadeel');
        $profile = $user->profile;

        Storage::disk('public')->put('headers/old.jpg', 'fake-bytes');
        $profile->header_image = 'headers/old.jpg';
        $profile->save();

        $this->actingAs($user)->put(route('dashboard.update'), [
            'display_name' => 'Hadeel Demo',
            'bio' => 'Cover removed',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
            'remove_header_image' => '1',
            'links' => [],
        ])->assertRedirect(route('dashboard.edit'));

        $this->assertNull($profile->fresh()->header_image);
        Storage::disk('public')->assertMissing('headers/old.jpg');
    }

    public function test_header_image_validation_rejects_non_images_and_oversized_files(): void
    {
        Storage::fake('public');

        $user = $this->makeUser('cover-validation');
        Storage::disk('public')->put('headers/old.jpg', 'old-image');
        $user->profile->update(['header_image' => 'headers/old.jpg']);

        $payload = [
            'display_name' => 'Cover Validation',
            'bio' => 'Validate uploads',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
            'links' => [],
        ];

        $this->actingAs($user)->put(route('dashboard.update'), [
            ...$payload,
            'header_image' => UploadedFile::fake()->create('not-an-image.jpg', 10, 'text/plain'),
        ])->assertSessionHasErrors('header_image');

        $this->actingAs($user)->put(route('dashboard.update'), [
            ...$payload,
            'header_image' => UploadedFile::fake()->image('too-large.jpg')->size(2049),
        ])->assertSessionHasErrors('header_image');

        $this->assertSame('headers/old.jpg', $user->profile->fresh()->header_image);
        Storage::disk('public')->assertExists('headers/old.jpg');
        $this->assertSame(['headers/old.jpg'], Storage::disk('public')->allFiles('headers'));
    }

    public function test_replacing_a_header_image_removes_the_previous_file_after_saving(): void
    {
        Storage::fake('public');

        $user = $this->makeUser('cover-replace');
        Storage::disk('public')->put('headers/old.jpg', 'old-image');
        $user->profile->update(['header_image' => 'headers/old.jpg']);

        $this->actingAs($user)->put(route('dashboard.update'), [
            'display_name' => 'Cover Replace',
            'bio' => 'New cover',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
            'header_image' => UploadedFile::fake()->image('new-cover.png'),
            'links' => [],
        ])->assertRedirect(route('dashboard.edit'));

        $newPath = $user->profile->fresh()->header_image;

        $this->assertNotSame('headers/old.jpg', $newPath);
        Storage::disk('public')->assertMissing('headers/old.jpg');
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_failed_profile_update_preserves_old_image_and_removes_new_upload(): void
    {
        Storage::fake('public');

        $user = $this->makeUser('cover-rollback');
        Storage::disk('public')->put('headers/old.jpg', 'old-image');
        $user->profile->update(['header_image' => 'headers/old.jpg']);

        Link::creating(function () {
            throw new \RuntimeException('Simulated link synchronization failure.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->put(route('dashboard.update'), [
                'display_name' => 'Cover Rollback',
                'bio' => 'Failed update',
                'bg_color_1' => '#b88c8f',
                'bg_color_2' => '#ba8b8f',
                'text_color' => '#1a1a1a',
                'page_background_color' => '#f4efed',
                'header_image' => UploadedFile::fake()->image('new-cover.png'),
                'links' => [[
                    'title' => 'New link',
                    'url' => 'https://example.com',
                ]],
            ]);

            $this->fail('The simulated database failure should escape the request.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated link synchronization failure.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: App\Models\Link');
        }

        $this->assertSame('headers/old.jpg', $user->profile->fresh()->header_image);
        Storage::disk('public')->assertExists('headers/old.jpg');
        $this->assertSame(['headers/old.jpg'], Storage::disk('public')->allFiles('headers'));
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

    public function test_dashboard_can_reorder_links_and_publish_state_is_respected(): void
    {
        $user = $this->makeUser('link-order');
        $links = $user->links()->orderBy('order')->get()->keyBy('title');

        $this->actingAs($user)->put(route('dashboard.update'), [
            'display_name' => 'Link Order',
            'bio' => 'Reordered links',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
            'links' => [
                [
                    'id' => $links['Second Link']->id,
                    'title' => 'Second Link',
                    'url' => 'https://second.example',
                    'is_active' => '1',
                ],
                [
                    'id' => $links['First Link']->id,
                    'title' => 'First Link',
                    'url' => 'https://first.example',
                    'is_active' => '1',
                ],
                [
                    'id' => $links['Hidden Draft']->id,
                    'title' => 'Hidden Draft',
                    'url' => 'https://draft.example',
                    'is_active' => '0',
                ],
            ],
        ])->assertRedirect(route('dashboard.edit'));

        $this->assertSame(
            ['Second Link', 'First Link', 'Hidden Draft'],
            $user->links()->orderBy('order')->pluck('title')->all()
        );

        $this->get('/link-order')
            ->assertOk()
            ->assertSeeInOrder(['Second Link', 'First Link'])
            ->assertDontSee('draft.example');
    }

    public function test_dashboard_link_updates_cannot_modify_another_users_link(): void
    {
        $owner = $this->makeUser('link-owner');
        $otherUser = $this->makeUser('link-other');
        $ownedLink = $owner->links()->create([
            'title' => 'Owned link',
            'url' => 'https://owner.example',
            'order' => 10,
        ]);
        $foreignLink = $otherUser->links()->create([
            'title' => 'Foreign link',
            'url' => 'https://foreign.example',
            'order' => 10,
        ]);

        $this->actingAs($owner)->put(route('dashboard.update'), [
            'display_name' => 'Link Owner',
            'bio' => 'Ownership boundary',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
            'user_id' => $otherUser->id,
            'links' => [
                [
                    'id' => $ownedLink->id,
                    'title' => 'Owned link updated',
                    'url' => 'https://owner-updated.example',
                    'is_active' => '1',
                ],
                [
                    'id' => $foreignLink->id,
                    'title' => 'Forged link update',
                    'url' => 'https://attacker.example',
                    'is_active' => '1',
                ],
            ],
        ])->assertRedirect(route('dashboard.edit'));

        $this->assertDatabaseHas('links', [
            'id' => $foreignLink->id,
            'user_id' => $otherUser->id,
            'title' => 'Foreign link',
            'url' => 'https://foreign.example',
        ]);
        $this->assertDatabaseHas('links', [
            'id' => $ownedLink->id,
            'user_id' => $owner->id,
            'title' => 'Owned link updated',
        ]);
        $this->assertDatabaseMissing('profiles', ['user_id' => $otherUser->id, 'display_name' => 'Link Owner']);
    }

    public function test_dashboard_rejects_non_http_link_schemes(): void
    {
        $user = $this->makeUser('link-scheme');

        $this->actingAs($user)->put(route('dashboard.update'), [
            'display_name' => 'Link Scheme',
            'bio' => 'Only web links',
            'bg_color_1' => '#b88c8f',
            'bg_color_2' => '#ba8b8f',
            'text_color' => '#1a1a1a',
            'page_background_color' => '#f4efed',
            'links' => [[
                'title' => 'Unsupported scheme',
                'url' => 'ftp://example.com/file',
            ]],
        ])->assertSessionHasErrors('links.0.url');

        $this->assertDatabaseMissing('links', [
            'user_id' => $user->id,
            'url' => 'ftp://example.com/file',
        ]);
    }

    public function test_bio_page_404s_for_unknown_username(): void
    {
        $this->get('/no-such-user-xyz')->assertNotFound();
    }

    public function test_public_profile_text_is_html_escaped(): void
    {
        $user = $this->makeUser('escaped-profile');
        $user->profile->update([
            'display_name' => '<script>alert(1)</script>',
            'bio' => '<img src=x onerror=alert(1)>',
        ]);
        $user->links()->create([
            'title' => '<svg onload=alert(1)>',
            'url' => 'https://example.com',
            'order' => 10,
            'is_active' => true,
        ]);

        $this->get('/escaped-profile')
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSee('&lt;svg onload=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<svg onload=alert(1)>', false);
    }

    public function test_usernames_with_reserved_prefixes_remain_public_and_case_insensitive(): void
    {
        User::factory()->create([
            'username' => 'dashboard2',
            'email' => 'dashboard2@example.com',
        ]);

        $this->get('/dashboard2')->assertOk();
        $this->get('/DASHBOARD2')->assertOk();
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

        $this->post('/register', [...$payload, 'username' => 'DASHBOARD'])
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

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('login.store'), [
                'email' => "missing{$attempt}@example.com",
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => 'missing@example.com',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_login_authenticates_a_valid_user(): void
    {
        $user = $this->makeUser('login-success');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard.edit'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard.edit'))->assertOk();
    }

    public function test_dashboard_routes_require_authentication(): void
    {
        $this->get(route('dashboard.edit'))->assertRedirect(route('login'));
        $this->put(route('dashboard.update'), [])->assertRedirect(route('login'));
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('register.store'), [])
                ->assertSessionHasErrors(['name', 'username', 'email', 'password']);
        }

        $this->post(route('register.store'), [])->assertTooManyRequests();
    }

    public function test_dashboard_logout_returns_to_landing_page(): void
    {
        $user = User::factory()->create([
            'username' => 'hadeel',
            'email' => 'hadeel@example.com',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('تسجيل الخروج');

        $this->post('/logout')
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->get('/')
            ->assertOk()
            ->assertSee('بايوـلينك');
    }
}
