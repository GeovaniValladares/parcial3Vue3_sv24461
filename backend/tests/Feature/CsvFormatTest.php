<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvFormatTest extends TestCase
{
    use RefreshDatabase;

    private function parse(string $csv): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $file = fopen('php://temp', 'w+');
        fwrite($file, substr($csv, 3));
        rewind($file);
        $lines = [];
        while (($row = fgetcsv($file, 0, ',', '"', '')) !== false) {
            $this->assertCount(10, $row);
            $lines[] = $row;
        }
        fclose($file);

        return $lines;
    }

    private function group(User $teacher, string $code, string $subject): AcademicGroup
    {
        return AcademicGroup::create(['teacher_id' => $teacher->id, 'code' => $code, 'subject' => $subject, 'cycle' => 'II-2026']);
    }

    private function student(string $code, string $name): Student
    {
        return Student::create(['student_code' => $code, 'name' => $name, 'email' => strtolower($code).'@example.invalid', 'career' => Student::CAREER]);
    }

    public function test_csv_has_metadata_then_table_with_filters_and_safe_cells(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => '  =1+1', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Docente ULS']);
        $group = $this->group($teacher, 'WEB-01', 'Programación, aplicaciones web');
        $student = $this->student('SV90001', '  =2+2');
        foreach ([['2026-10-15', 1], ['2026-10-20', 2], ['2026-09-15', 3]] as [$date, $evaluation]) {
            Grade::create(['student_id' => $student->id, 'academic_group_id' => $group->id, 'evaluation' => $evaluation, 'score' => 8.5, 'recorded_on' => $date, 'created_by' => $admin->id]);
        }
        $csv = $this->actingAs($admin)->get('/api/reports/csv?month=2026-10&group_id='.$group->id)->assertOk()->assertDownload('notas-uls.csv')->streamedContent();
        $lines = $this->parse($csv);
        $metadata = [];
        $header = null;
        foreach ($lines as $index => $row) {
            if ($row[0] === 'Fecha') {
                $header = $index;
                break;
            }
            if ($row[0] !== '') {
                $metadata[$row[0]] = $row[1];
            }
        }
        $this->assertSame('Institución', $lines[0][0]);
        $this->assertSame('Universidad Luterana Salvadoreña', $metadata['Institución']);
        $this->assertSame('01/10/2026 - 31/10/2026', $metadata['Rango de fechas']);
        $this->assertSame("'  =1+1", $metadata['Usuario que genera']);
        $this->assertMatchesRegularExpression('~^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$~', $metadata['Fecha y hora de generación']);
        $this->assertSame('1', $metadata['Estudiantes reportados']);
        $this->assertSame('2', $metadata['Notas registradas']);
        $this->assertSame('WEB-01 - Programación, aplicaciones web', $metadata['Grupo consultado']);
        $this->assertSame('II-2026', $metadata['Ciclos académicos']);
        $this->assertNotNull($header);
        $this->assertSame(['Fecha', 'Carné', 'Estudiante', 'Asignatura', 'Grupo', 'Ciclo', 'Evaluación', 'Nota', 'Estado', 'Docente'], $lines[$header]);
        $data = array_slice($lines, $header + 1);
        $this->assertCount(2, $data);
        $this->assertSame('15/10/2026', $data[0][0]);
        $this->assertSame("'  =2+2", $data[0][2]);
        $this->assertSame('8.50', $data[0][7]);
        $this->assertSame('Docente ULS', $data[0][9]);
        $this->getJson('/api/reports/excel?month=2026-10&group_id='.$group->id)
            ->assertOk()->assertJsonCount(10, 'columns')->assertJsonCount(2, 'rows')
            ->assertJsonPath('metadata.5.1', 1)->assertJsonPath('metadata.6.1', 2)
            ->assertJsonPath('rows.0.0', '2026-10-15')->assertJsonPath('rows.0.1', 'SV90001')
            ->assertJsonPath('rows.0.2', '  =2+2')->assertJsonPath('rows.0.7', 8.5);
    }

    public function test_teacher_export_and_metadata_do_not_disclose_other_groups(): void
    {
        $own = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $other = User::factory()->create(['role' => 'teacher']);
        $group = $this->group($own, 'OWN-01', 'Asignatura propia');
        $foreign = $this->group($other, 'PRIVATE-01', 'Asignatura privada');
        $student = $this->student('OWNSTUDENT', 'Mi estudiante');
        $foreignStudent = $this->student('PRIVATESTUDENT', 'Estudiante privado');
        foreach ([[$group, $student], [$foreign, $foreignStudent]] as [$g, $s]) {
            Grade::create(['student_id' => $s->id, 'academic_group_id' => $g->id, 'evaluation' => 1, 'score' => 9, 'recorded_on' => '2026-10-20', 'created_by' => $g->teacher_id]);
        }
        $csv = $this->actingAs($own)->get('/api/reports/csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Mi estudiante', $csv);
        $this->assertStringNotContainsString('PRIVATE', $csv);
        $this->assertStringNotContainsString('Estudiante privado', $csv);
        $empty = $this->get('/api/reports/csv?group_id='.$foreign->id)->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Asignatura privada', $empty);
        $this->assertStringNotContainsString('PRIVATE', $empty);
        $rows = $this->parse($empty);
        $this->assertSame('Fecha', $rows[array_key_last($rows)][0]);
        $this->getJson('/api/reports/excel')->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.2', 'Mi estudiante');
        $this->getJson('/api/reports/excel?group_id='.$foreign->id)->assertOk()->assertJsonCount(0, 'rows')->assertJsonMissing(['Asignatura privada']);
    }
}
