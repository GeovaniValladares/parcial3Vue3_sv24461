<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_logs_in_with_password_without_authenticator(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'Admin@Password123', 'totp_confirmed_at' => null, 'must_change_password' => false]);
        $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'Admin@Password123'])->assertOk()->assertJsonPath('user.id', $admin->id)->assertJsonMissingPath('user.password');
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.role', 'admin');
        $this->getJson('/api/dashboard')->assertOk();
        $this->postJson('/api/auth/verify', ['code' => '123456'])->assertUnauthorized();
    }

    public function test_teachers_and_students_still_require_otp(): void
    {
        foreach (['teacher', 'student'] as $role) {
            $user = User::factory()->create(['role' => $role, 'password' => 'Test@Password123', 'totp_secret' => app(Totp::class)->secret(), 'totp_confirmed_at' => now(), 'must_change_password' => false]);
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Test@Password123', 'role' => 'admin'])->assertOk()->assertJsonMissingPath('user');
            $this->getJson('/api/auth/me')->assertUnauthorized();
            $code = app(Totp::class)->code($user->totp_secret, intdiv(time(), 30));
            $this->postJson('/api/auth/verify', ['code' => $code])->assertOk()->assertJsonPath('user.role', $role);
            $this->postJson('/api/auth/logout')->assertOk();
        }
    }

    public function test_qr_activation_opens_authenticated_panel_with_password_gate(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'password' => 'Initial@Password123', 'totp_secret' => app(Totp::class)->secret(), 'invitation_token' => hash('sha256', 'onboard-token'), 'invitation_expires_at' => now()->addHour(), 'totp_confirmed_at' => null, 'must_change_password' => true]);
        $code = app(Totp::class)->code($user->totp_secret, intdiv(time(), 30));
        $this->postJson('/api/auth/invitations/onboard-token/confirm', ['code' => $code])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.must_change_password', true)->assertJsonMissingPath('user.totp_secret');
        $this->getJson('/api/auth/me')->assertOk();
        $this->getJson('/api/grades')->assertForbidden();
        $this->putJson('/api/auth/password', ['current_password' => 'Initial@Password123', 'password' => 'Permanent@Password123', 'password_confirmation' => 'Permanent@Password123'])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->getJson('/api/grades')->assertOk();
        $this->assertTrue(Hash::check('Permanent@Password123', $user->fresh()->password));
        $this->getJson('/api/auth/invitations/onboard-token')->assertNotFound();
    }

    public function test_profile_updates_only_own_contact_data_and_syncs_student(): void
    {
        $user = User::factory()->create(['role' => 'student', 'must_change_password' => false]);
        $other = User::factory()->create(['name' => 'Otro usuario']);
        $student = Student::create(['name' => $user->name, 'email' => $user->email, 'career' => Student::CAREER, 'student_code' => 'PROFILE001', 'user_id' => $user->id]);
        $this->actingAs($user)->getJson('/api/profile')->assertOk()->assertJsonPath('student.student_code', 'PROFILE001')->assertJsonMissingPath('user.password');
        $this->putJson('/api/profile', ['name' => 'Mi nombre actualizado', 'phone' => '77770000', 'role' => 'admin', 'email' => 'changed@example.com', 'id' => $other->id])->assertOk()->assertJsonPath('user.name', 'Mi nombre actualizado')->assertJsonPath('user.role', 'student')->assertJsonPath('user.email', $user->email);
        $this->assertSame('Mi nombre actualizado', $student->fresh()->name);
        $this->assertSame('Otro usuario', $other->fresh()->name);
        $this->putJson('/api/profile', ['name' => '', 'phone' => str_repeat('1', 30)])->assertUnprocessable();
    }

    public function test_welcome_email_is_personalized_for_students_and_teachers(): void
    {
        config(['mail.default' => 'array']);
        foreach (['student', 'teacher'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            app(InvitationService::class)->send($user);
        }
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages()->values();
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('Tu nueva etapa comienza aquí', $messages[0]->getOriginalMessage()->getSubject());
        $this->assertStringContainsString('tu formación', $messages[0]->getOriginalMessage()->getHtmlBody());
        $this->assertStringContainsString('equipo docente', $messages[1]->getOriginalMessage()->getHtmlBody());
        foreach ($messages as $message) {
            $html = $message->getOriginalMessage()->getHtmlBody();
            $this->assertStringContainsString('Ingresar a mi portal académico', $html);
            $this->assertStringContainsString('Contraseña temporal', $html);
            $this->assertStringContainsString('/activar/', $html);
        }
    }
}
