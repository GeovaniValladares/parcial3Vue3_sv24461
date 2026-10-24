<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AcademicTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin', bool $change = false): User
    {
        return User::factory()->create(['role' => $role, 'must_change_password' => $change, 'totp_secret' => app(Totp::class)->secret(), 'totp_confirmed_at' => now()]);
    }

    private function fixture(User $teacher): array
    {
        $g = AcademicGroup::create(['code' => 'GR-'.uniqid(), 'subject' => 'Programación', 'cycle' => 'II-2026', 'teacher_id' => $teacher->id]);
        $s = Student::create(['name' => 'Sofía Test', 'email' => uniqid().'@example.com', 'student_code' => uniqid(), 'career' => 'Ingeniería']);
        $g->students()->attach($s);

        return [$g, $s];
    }

    private function gradeBody(AcademicGroup $g, Student $s): array
    {
        return ['academic_group_id' => $g->id, 'student_id' => $s->id, 'evaluation' => 1, 'score' => 8.5, 'recorded_on' => now()->toDateString()];
    }

    public function test_guests_and_password_change_gate(): void
    {
        $this->getJson('/api/grades')->assertUnauthorized();
        $u = $this->user('admin', true);
        $this->actingAs($u)->getJson('/api/grades')->assertForbidden();
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.must_change_password', true);
    }

    public function test_admin_grade_crud_validation_duplicate_and_pagination(): void
    {
        $u = $this->user();
        [$g,$s] = $this->fixture($this->user('teacher'));
        $body = $this->gradeBody($g, $s);
        $this->actingAs($u);
        $this->postJson('/api/grades', array_replace($body, ['score' => 11]))->assertUnprocessable();
        $id = $this->postJson('/api/grades', $body)->assertCreated()->json('id');
        $this->postJson('/api/grades', $body)->assertUnprocessable();
        $this->putJson('/api/grades/'.$id, array_replace($body, ['score' => 9.4]))->assertOk()->assertJsonPath('score', 9.4);
        $this->getJson('/api/grades')->assertOk()->assertJsonPath('per_page', 5)->assertJsonCount(1, 'data');
        $this->deleteJson('/api/grades/'.$id)->assertOk();
        $this->assertDatabaseMissing('grades', ['id' => $id]);
    }

    public function test_teacher_cannot_access_other_groups_or_admin_routes(): void
    {
        $t = $this->user('teacher');
        $other = $this->user('teacher');
        [$own,$s] = $this->fixture($t);
        [$foreign,$s2] = $this->fixture($other);
        $this->actingAs($t);
        $this->postJson('/api/grades', $this->gradeBody($own, $s))->assertCreated();
        $this->postJson('/api/grades', $this->gradeBody($foreign, $s2))->assertNotFound();
        $this->getJson('/api/teachers')->assertForbidden();
        $this->getJson('/api/students')->assertForbidden();
        $this->getJson('/api/groups')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/options')->assertOk()->assertJsonCount(1, 'groups');
        $this->getJson('/api/reports?group_id='.$foreign->id)->assertOk()->assertJsonCount(0, 'grades');
        $this->postJson('/api/groups', ['code' => 'new'])->assertForbidden();
    }

    public function test_teacher_cannot_modify_delete_or_email_foreign_grade(): void
    {
        Mail::fake();
        $t = $this->user('teacher');
        [$g,$s] = $this->fixture($this->user('teacher'));
        $grade = Grade::create($this->gradeBody($g, $s) + ['created_by' => $g->teacher_id]);
        $this->actingAs($t)->putJson('/api/grades/'.$grade->id, $this->gradeBody($g, $s))->assertNotFound();
        $this->deleteJson('/api/grades/'.$grade->id)->assertNotFound();
        $this->postJson('/api/grades/'.$grade->id.'/email')->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_student_must_be_enrolled(): void
    {
        $t = $this->user('teacher');
        [$g,$s] = $this->fixture($t);
        $g->students()->detach($s);
        $this->actingAs($t)->postJson('/api/grades', $this->gradeBody($g, $s))->assertUnprocessable();
    }

    public function test_reports_pdf_csv_month_filters_and_unique_students(): void
    {
        $u = $this->user();
        [$g,$s] = $this->fixture($this->user('teacher'));
        $first = $this->gradeBody($g, $s);
        Grade::create($first + ['created_by' => $u->id]);
        Grade::create(array_replace($first, ['evaluation' => 2, 'score' => 7]) + ['created_by' => $u->id]);
        $this->actingAs($u);
        $this->getJson('/api/reports?month='.now()->format('Y-m'))->assertOk()->assertJsonPath('passed', 1)->assertJsonPath('failed', 0)->assertJsonCount(2, 'grades');
        $this->getJson('/api/reports?month=2000-01')->assertOk()->assertJsonCount(0, 'grades');
        $this->getJson('/api/reports?month=oops')->assertUnprocessable();
        $csv = $this->get('/api/reports/csv')->assertOk()->assertDownload('notas-uls.csv');
        $this->assertStringContainsString('Sofía Test', $csv->streamedContent());
        $pdf = $this->get('/api/reports/pdf')->assertOk()->assertDownload('notas-uls.pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_create_teacher_invitation_and_reinvite_without_real_mail(): void
    {
        config(['mail.default' => 'array']);
        $u = $this->user();
        $this->actingAs($u)->postJson('/api/teachers', ['name' => 'Docente Nuevo', 'email' => 'nuevo@example.com', 'phone' => '70000000'])->assertCreated();
        $teacher = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertNotNull($teacher->invitation_token);
        $this->assertNotNull($teacher->totp_secret);
        $this->assertNull($teacher->totp_confirmed_at);
        $old = $teacher->invitation_token;
        $this->postJson('/api/teachers/'.$teacher->id.'/invite')->assertOk();
        $this->assertNotSame($old, $teacher->fresh()->invitation_token);
        $this->assertCount(2, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_email_grade_uses_student_recipient(): void
    {
        config(['mail.default' => 'array']);
        $u = $this->user();
        [$g,$s] = $this->fixture($this->user('teacher'));
        $grade = Grade::create($this->gradeBody($g, $s) + ['created_by' => $u->id]);
        $this->actingAs($u)->postJson('/api/grades/'.$grade->id.'/email')->assertOk();
        $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame($s->email, $sent->first()->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_grade_dependencies_protect_students_and_groups(): void
    {
        $u = $this->user();
        [$g,$s] = $this->fixture($this->user('teacher'));
        Grade::create($this->gradeBody($g, $s) + ['created_by' => $u->id]);
        $this->actingAs($u)->deleteJson('/api/students/'.$s->id)->assertUnprocessable();
        $this->deleteJson('/api/groups/'.$g->id)->assertUnprocessable();
    }
}
