<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function pending(): User
    {
        return User::factory()->create(['role' => 'teacher', 'password' => 'Initial@Password123', 'must_change_password' => true, 'totp_secret' => app(Totp::class)->secret(), 'invitation_token' => hash('sha256', 'test-token'), 'invitation_expires_at' => now()->addHour()]);
    }

    public function test_activation_password_otp_and_mandatory_change_flow(): void
    {
        $u = $this->pending();
        $t = app(Totp::class);
        $this->getJson('/api/auth/invitations/test-token')->assertOk()->assertJsonStructure(['uri', 'secret', 'name']);
        $counter = intdiv(time(), 30);
        $code = $t->code($u->totp_secret, $counter);
        $this->postJson('/api/auth/invitations/test-token/confirm', ['code' => $code])->assertOk();
        $this->getJson('/api/auth/invitations/test-token')->assertNotFound();
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'Initial@Password123'])->assertOk();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/verify', ['code' => $code])->assertUnprocessable();
        $this->postJson('/api/auth/verify', ['code' => $t->code($u->totp_secret, $counter + 1)])->assertOk()->assertJsonPath('user.must_change_password', true);
        $this->getJson('/api/grades')->assertForbidden();
        $this->putJson('/api/auth/password', ['current_password' => 'wrong', 'password' => 'Permanent@Password123', 'password_confirmation' => 'Permanent@Password123'])->assertUnprocessable();
        $this->putJson('/api/auth/password', ['current_password' => 'Initial@Password123', 'password' => 'Permanent@Password123', 'password_confirmation' => 'Permanent@Password123'])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->assertTrue(Hash::check('Permanent@Password123', $u->fresh()->password));
        $this->getJson('/api/grades')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_expired_invitation_and_unactivated_login_are_rejected(): void
    {
        $u = $this->pending();
        $u->update(['invitation_expires_at' => now()->subMinute()]);
        $this->getJson('/api/auth/invitations/test-token')->assertNotFound();
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'Initial@Password123'])->assertUnprocessable();
    }

    public function test_otp_requires_password_and_challenge_expires(): void
    {
        $this->postJson('/api/auth/verify', ['code' => '123456'])->assertUnauthorized();
        $this->withSession(['challenge_user' => 99, 'challenge_expires' => time() - 5])->postJson('/api/auth/verify', ['code' => '123456'])->assertUnauthorized();
    }

    public function test_authentication_hides_all_secrets(): void
    {
        $u = $this->pending();
        $this->actingAs($u)->getJson('/api/auth/me')->assertOk()->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.totp_secret')->assertJsonMissingPath('user.invitation_token');
        $raw = $u->getRawOriginal('totp_secret');
        $this->assertNotSame($u->totp_secret, $raw);
    }

    public function test_password_reset_revokes_sessions_and_keeps_authenticator(): void
    {
        config(['mail.default' => 'array']);
        $u = $this->pending();
        $u->update(['totp_confirmed_at' => now()]);
        $secret = $u->totp_secret;
        $this->postJson('/api/auth/forgot-password', ['email' => $u->email])->assertOk();
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $token = Password::broker()->createToken($u);
        $this->postJson('/api/auth/reset-password', ['email' => $u->email, 'token' => $token, 'password' => 'Recovered@Password123', 'password_confirmation' => 'Recovered@Password123'])->assertOk();
        $this->assertTrue(Hash::check('Recovered@Password123', $u->fresh()->password));
        $this->assertSame($secret, $u->fresh()->totp_secret);
        $this->postJson('/api/auth/reset-password', ['email' => $u->email, 'token' => $token, 'password' => 'Recovered@Password123', 'password_confirmation' => 'Recovered@Password123'])->assertUnprocessable();
    }
}
