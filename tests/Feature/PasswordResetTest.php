<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_renders(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('استعادة كلمة المرور');
    }

    public function test_reset_link_is_sent_for_an_existing_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_link_is_not_sent_for_an_unknown_email(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_reset_form_renders_with_a_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('تعيين كلمة مرور جديدة')
            ->assertSee($user->email);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reserved_slugs_are_not_bio_pages(): void
    {
        $this->get('/reset-password')->assertRedirect(route('password.request'));
        $this->get('/forgot-password')->assertOk();
    }

    public function test_new_reserved_slugs_cannot_be_registered_as_usernames(): void
    {
        $this->post('/register', [
            'name' => 'Hadeel',
            'username' => 'forgot-password',
            'email' => 'hadeel@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('users', ['email' => 'hadeel@example.com']);
    }
}
