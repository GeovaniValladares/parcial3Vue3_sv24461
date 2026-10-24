<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class StudentAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'must_change_password' => false]));
    }

    private function studentData(string $email = 'alumno@example.com'): array
    {
        return ['name' => 'Alumno de prueba', 'email' => $email, 'student_code' => 'SV90001', 'career' => Student::CAREER, 'group_ids' => []];
    }

    public function test_student_creation_sends_invitation_and_exposes_activation_status(): void
    {
        $this->postJson('/api/students', $this->studentData())->assertCreated()->assertJsonPath('student.career', Student::CAREER)->assertJsonPath('student.totp_confirmed_at', null);
        $student = Student::firstOrFail();
        $this->assertSame('student', $student->user->role);
        $this->assertNotNull($student->user->invitation_token);
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $message = $sent->first()->getOriginalMessage();
        $this->assertSame($student->email, $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('/activar/', $message->getHtmlBody());
        $student->user->update(['totp_confirmed_at' => now()]);
        $this->getJson('/api/students')->assertOk()->assertJsonMissingPath('data.0.user');
        $this->assertNotNull($this->getJson('/api/students')->json('data.0.totp_confirmed_at'));
    }

    public function test_existing_student_can_receive_first_invitation_without_losing_enrollment(): void
    {
        $student = Student::create(collect($this->studentData())->except('group_ids')->all());
        $this->postJson('/api/students/'.$student->id.'/invite')->assertOk();
        $first = $student->fresh()->user;
        $token = $first->invitation_token;
        $this->postJson('/api/students/'.$student->id.'/invite')->assertOk();
        $this->assertNotSame($token, $first->fresh()->invitation_token);
        $this->assertSame(1, Student::count());
        $this->assertCount(2, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_active_student_and_teacher_receive_access_mail_without_resetting_authentication(): void
    {
        foreach (['student', 'teacher'] as $role) {
            $user = User::factory()->create(['role' => $role, 'totp_secret' => app(Totp::class)->secret(), 'totp_confirmed_at' => now(), 'must_change_password' => false]);
            $password = $user->password;
            $secret = $user->totp_secret;
            if ($role === 'student') {
                $student = Student::create(['name' => $user->name, 'email' => $user->email, 'student_code' => 'ACTIVE', 'career' => Student::CAREER, 'user_id' => $user->id]);
                $path = '/api/students/'.$student->id.'/invite';
            } else {
                $path = '/api/teachers/'.$user->id.'/invite';
            }
            $this->postJson($path)->assertOk();
            $this->assertSame($password, $user->fresh()->password);
            $this->assertSame($secret, $user->fresh()->totp_secret);
            $this->assertFalse($user->fresh()->must_change_password);
        }
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $this->assertStringContainsString('/login', $message->getOriginalMessage()->getHtmlBody());
        }
    }

    public function test_students_and_teachers_can_edit_names_and_email_addresses(): void
    {
        $this->postJson('/api/students', $this->studentData())->assertCreated();
        $student = Student::firstOrFail();
        $this->putJson('/api/students/'.$student->id, array_replace($this->studentData('nuevo-alumno@example.com'), ['name' => 'Alumno editado']))->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Alumno editado', 'email' => 'nuevo-alumno@example.com']);
        $this->assertDatabaseHas('users', ['id' => $student->user_id, 'name' => 'Alumno editado', 'email' => 'nuevo-alumno@example.com']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->putJson('/api/teachers/'.$teacher->id, ['name' => 'Docente editado', 'email' => 'nuevo-docente@example.com', 'phone' => '70000000'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'name' => 'Docente editado', 'email' => 'nuevo-docente@example.com']);
    }

    public function test_mail_failure_rolls_back_student_and_account_creation(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('SMTP no disponible'));
        $this->postJson('/api/students', $this->studentData())->assertStatus(503);
        $this->assertDatabaseMissing('students', ['email' => 'alumno@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'alumno@example.com']);
    }

    public function test_student_can_only_read_their_own_grades_and_cannot_modify_them(): void
    {
        $this->postJson('/api/students', $this->studentData())->assertCreated();
        $student = Student::firstOrFail();
        $user = $student->user;
        $user->update(['totp_confirmed_at' => now(), 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $group = AcademicGroup::create(['code' => 'QA-01', 'subject' => 'Computación', 'cycle' => 'II-2026', 'teacher_id' => $teacher->id]);
        $other = Student::create(['name' => 'Otro alumno', 'email' => 'otro@example.com', 'student_code' => 'SV90002', 'career' => Student::CAREER]);
        $group->students()->attach([$student->id, $other->id]);
        foreach ([$student, $other] as $person) {
            Grade::create(['student_id' => $person->id, 'academic_group_id' => $group->id, 'score' => 8, 'evaluation' => 1, 'recorded_on' => now()->toDateString(), 'created_by' => $teacher->id]);
        }
        $this->actingAs($user);
        $this->getJson('/api/grades')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_id', $student->id);
        $this->getJson('/api/reports')->assertForbidden();
        $this->getJson('/api/reports/pdf')->assertForbidden();
        $this->getJson('/api/reports/csv')->assertForbidden();
        $this->getJson('/api/reports/excel')->assertForbidden();
        $this->getJson('/api/dashboard')->assertOk()->assertJsonMissingPath('stats');
        $this->getJson('/api/options')->assertOk()->assertJsonCount(1, 'groups.0.students');
        $this->postJson('/api/grades', [])->assertForbidden();
        $this->postJson('/api/grades/'.Grade::first()->id.'/email')->assertForbidden();
        $this->getJson('/api/students')->assertForbidden();
        $this->getJson('/api/teachers')->assertForbidden();
    }
}
