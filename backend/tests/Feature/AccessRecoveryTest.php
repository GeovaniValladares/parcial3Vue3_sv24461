<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AccessRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'teacher'): User
    {
        return User::factory()->create(['role' => $role, 'password' => 'Original@Password123', 'must_change_password' => false, 'totp_secret' => app(Totp::class)->secret(), 'totp_confirmed_at' => now(), 'totp_last_counter' => intdiv(time(), 30) - 1, 'remember_token' => 'old-remember']);
    }

    private function administrator(): User
    {
        return User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
    }

    private function storedSession(User $user, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
    }

    public function test_admin_reset_rotates_access_and_revokes_old_sessions_and_reset_links(): void
    {
        config(['mail.default' => 'array']);
        $teacher = $this->account();
        $other = $this->account();
        $admin = $this->administrator();
        $oldSecret = $teacher->totp_secret;
        $oldPassword = $teacher->password;
        Password::broker()->createToken($teacher);
        $this->storedSession($teacher, 'teacher-session');
        $this->storedSession($other, 'other-session');
        $this->storedSession($admin, 'admin-session');
        $this->actingAs($admin)->postJson('/api/teachers/'.$teacher->id.'/reset-access')->assertOk()->assertJsonMissingPath('secret')->assertJsonMissingPath('password');
        $teacher->refresh();
        $this->assertNotSame($oldSecret, $teacher->totp_secret);
        $this->assertNotSame($oldPassword, $teacher->password);
        $this->assertFalse(Hash::check('Original@Password123', $teacher->password));
        $this->assertTrue($teacher->must_change_password);
        $this->assertNull($teacher->totp_confirmed_at);
        $this->assertNull($teacher->totp_last_counter);
        $this->assertNull($teacher->remember_token);
        $this->assertNotNull($teacher->invitation_token);
        $this->assertTrue($teacher->invitation_expires_at->isFuture());
        $this->assertDatabaseMissing('sessions', ['id' => 'teacher-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $teacher->email]);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame($teacher->email, $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('Configurar mi nuevo QR', $message->getHtmlBody());
    }

    public function test_old_login_challenge_is_rejected_and_new_qr_completes_recovery(): void
    {
        config(['mail.default' => 'array']);
        $teacher = $this->account();
        $secret = $teacher->totp_secret;
        $fingerprint = hash('sha256', $teacher->password.'|'.$secret);
        $this->actingAs($this->administrator())->withSession(['challenge_user' => $teacher->id, 'challenge_expires' => time() + 300, 'challenge_attempts' => 0, 'challenge_fingerprint' => $fingerprint])->postJson('/api/teachers/'.$teacher->id.'/reset-access')->assertOk();
        $this->postJson('/api/auth/verify', ['code' => app(Totp::class)->code($secret, intdiv(time(), 30))])->assertUnauthorized();
        $html = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody();
        $this->assertSame(1, preg_match('~/activar/([A-Za-z0-9]{64})~', $html, $link));
        $this->assertSame(1, preg_match('~<span[^>]*>([^<]+)</span>~', $html, $passwordMatch));
        $temporaryPassword = html_entity_decode($passwordMatch[1], ENT_QUOTES, 'UTF-8');
        $teacher->refresh();
        $this->assertTrue(Hash::check($temporaryPassword, $teacher->password));
        $this->getJson('/api/auth/invitations/'.$link[1])->assertOk()->assertJsonPath('secret', $teacher->totp_secret);
        $code = app(Totp::class)->code($teacher->totp_secret, intdiv(time(), 30));
        $this->postJson('/api/auth/invitations/'.$link[1].'/confirm', ['code' => $code])->assertOk()->assertJsonPath('user.must_change_password', true);
        $this->getJson('/api/grades')->assertForbidden();
        $this->putJson('/api/auth/password', ['current_password' => $temporaryPassword, 'password' => 'Recovered@Password123', 'password_confirmation' => 'Recovered@Password123'])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->getJson('/api/grades')->assertOk();
        $this->getJson('/api/auth/invitations/'.$link[1])->assertNotFound();
    }

    public function test_student_reset_keeps_grades_and_enrollments(): void
    {
        config(['mail.default' => 'array']);
        $user = $this->account('student');
        $student = Student::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'student_code' => 'RECOVERY01', 'career' => Student::CAREER]);
        $teacher = $this->account();
        $group = AcademicGroup::create(['code' => 'REC-01', 'subject' => 'Programación', 'cycle' => 'II-2026', 'teacher_id' => $teacher->id]);
        $student->groups()->attach($group->id);
        $grade = Grade::create(['student_id' => $student->id, 'academic_group_id' => $group->id, 'score' => 8.5, 'evaluation' => 1, 'recorded_on' => now()->toDateString(), 'created_by' => $teacher->id]);
        $oldSecret = $user->totp_secret;
        $this->actingAs($this->administrator())->postJson('/api/students/'.$student->id.'/reset-access')->assertOk();
        $this->assertSame($user->id, $student->fresh()->user_id);
        $this->assertNotSame($oldSecret, $user->fresh()->totp_secret);
        $this->assertNull($this->getJson('/api/students')->json('data.0.totp_confirmed_at'));
        $this->assertDatabaseHas('grades', ['id' => $grade->id, 'score' => 8.5]);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'academic_group_id' => $group->id]);
    }

    public function test_pending_invitation_is_replaced_and_legacy_student_gets_an_account(): void
    {
        config(['mail.default' => 'array']);
        $teacher = $this->account();
        $teacher->update(['totp_confirmed_at' => null, 'invitation_token' => hash('sha256', 'previous-token'), 'invitation_expires_at' => now()->addHour()]);
        $this->getJson('/api/auth/invitations/previous-token')->assertOk();
        $this->actingAs($this->administrator())->postJson('/api/teachers/'.$teacher->id.'/reset-access')->assertOk();
        $this->getJson('/api/auth/invitations/previous-token')->assertNotFound();
        $student = Student::create(['name' => 'Estudiante anterior', 'email' => 'legacy@example.invalid', 'student_code' => 'LEGACY01', 'career' => Student::CAREER]);
        $this->postJson('/api/students/'.$student->id.'/reset-access')->assertOk();
        $this->assertNotNull($student->fresh()->user_id);
        $this->assertSame('student', $student->fresh()->user->role);
    }

    public function test_recovery_is_admin_only_and_cannot_target_an_admin_as_teacher(): void
    {
        $teacher = $this->account();
        $admin = $this->administrator();
        $studentUser = $this->account('student');
        $student = Student::create(['user_id' => $studentUser->id, 'name' => $studentUser->name, 'email' => $studentUser->email, 'student_code' => 'PERMISSION01', 'career' => Student::CAREER]);
        $this->postJson('/api/teachers/'.$teacher->id.'/reset-access')->assertUnauthorized();
        foreach (['teacher', 'student'] as $role) {
            $this->actingAs($this->account($role))->postJson('/api/teachers/'.$teacher->id.'/reset-access')->assertForbidden();
            $this->postJson('/api/students/'.$student->id.'/reset-access')->assertForbidden();
        }
        $this->actingAs($admin)->postJson('/api/teachers/'.$admin->id.'/reset-access')->assertNotFound();
    }

    public function test_mail_failure_preserves_existing_access_and_sessions(): void
    {
        $teacher = $this->account();
        $original = $teacher->refresh()->getRawOriginal();
        $this->storedSession($teacher, 'preserved-session');
        Password::broker()->createToken($teacher);
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('SMTP no disponible'));
        $this->actingAs($this->administrator())->postJson('/api/teachers/'.$teacher->id.'/reset-access')->assertStatus(503);
        $teacher->refresh();
        foreach (['password', 'totp_secret', 'totp_confirmed_at', 'invitation_token', 'must_change_password'] as $field) {
            $this->assertSame($original[$field], $teacher->getRawOriginal($field));
        }
        $this->assertDatabaseHas('sessions', ['id' => 'preserved-session']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $teacher->email]);
    }
}
