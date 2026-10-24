<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $code): Student
    {
        $user = User::factory()->create(['role' => 'student', 'must_change_password' => false]);

        return Student::create(['user_id' => $user->id, 'name' => 'Estudiante '.$code, 'student_code' => $code, 'email' => $user->email, 'career' => Student::CAREER]);
    }

    private function group(string $code, string $cycle): AcademicGroup
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Docente '.$code]);

        return AcademicGroup::create(['code' => $code, 'subject' => 'Asignatura '.$code, 'cycle' => $cycle, 'teacher_id' => $teacher->id]);
    }

    public function test_enrollment_requires_student_role_and_password_change(): void
    {
        foreach (['/api/enrollment', '/api/enrollment/pdf'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        foreach (['admin', 'teacher'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'must_change_password' => false]));
            $this->getJson('/api/enrollment')->assertForbidden();
            $this->getJson('/api/enrollment/pdf')->assertForbidden();
        }
        $student = $this->student('PENDING');
        $student->user->update(['must_change_password' => true]);
        $this->actingAs($student->user)->getJson('/api/enrollment/pdf')->assertForbidden();
    }

    public function test_only_own_enrollments_are_returned_and_cycles_are_filtered(): void
    {
        $own = $this->student('OWN001');
        $other = $this->student('OTHER001');
        $current = $this->group('PRG-01', 'II-2026');
        $previous = $this->group('BDD-01', 'I-2026');
        $foreign = $this->group('PRIVATE-01', 'II-2026');
        $own->groups()->attach([$current->id, $previous->id]);
        $other->groups()->attach($foreign->id);
        $response = $this->actingAs($own->user)->getJson('/api/enrollment?cycle=II-2026')->assertOk()->assertJsonPath('student.student_code', 'OWN001')->assertJsonCount(2, 'cycles')->assertJsonCount(1, 'groups')->assertJsonPath('groups.0.code', 'PRG-01')->assertJsonPath('groups.0.teacher', 'Docente PRG-01');
        $response->assertJsonMissing(['code' => 'PRIVATE-01'])->assertJsonMissingPath('student.user_id');
        $this->getJson('/api/enrollment?cycle=I-2026')->assertOk()->assertJsonPath('groups.0.code', 'BDD-01');
        $this->getJson('/api/enrollment?cycle=I-1900')->assertUnprocessable();
        $this->getJson('/api/enrollment?student_id='.$other->id)->assertUnprocessable();
        $this->getJson('/api/enrollment/pdf?user_id='.$other->user_id)->assertUnprocessable();
    }

    public function test_pdf_is_inline_with_logo_and_student_data(): void
    {
        $student = $this->student('SV90001');
        $group = $this->group('WEB-01', 'II-2026');
        $student->groups()->attach($group->id);
        $this->actingAs($student->user);
        $data = $this->getJson('/api/enrollment?cycle=II-2026')->assertOk()->json();
        $html = view('reports.enrollment', array_replace($data, ['groups' => collect($data['groups']), 'issuedAt' => now()]))->render();
        $this->assertStringContainsString('Boleta de inscripción', $html);
        $this->assertStringContainsString('SV90001', $html);
        $this->assertStringContainsString('Asignatura WEB-01', $html);
        $this->assertStringContainsString('data:image/jpeg;base64,', $html);
        $pdf = $this->get('/api/enrollment/pdf?cycle=II-2026')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('boleta-inscripcion-SV90001.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('/Subtype /Image', $pdf->getContent());
    }

    public function test_empty_enrollment_does_not_generate_a_misleading_document(): void
    {
        $student = $this->student('EMPTY001');
        $this->actingAs($student->user)->getJson('/api/enrollment')->assertOk()->assertJsonCount(0, 'groups')->assertJsonCount(0, 'cycles');
        $this->getJson('/api/enrollment/pdf')->assertUnprocessable();
    }
}
