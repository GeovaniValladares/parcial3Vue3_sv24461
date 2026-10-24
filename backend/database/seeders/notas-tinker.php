<?php

// Ejecutar desde backend: php artisan tinker --execute="require database_path('seeders/notas-tinker.php');"
use App\Models\AcademicGroup;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

if (! app()->environment('local')) {
    throw new RuntimeException('Esta carga de práctica requiere APP_ENV=local.');
}

$result = DB::transaction(function () {
    $group = AcademicGroup::orderBy('id')->firstOrFail();
    $admin = User::where('role', 'admin')->orderBy('id')->firstOrFail();
    $batch = 'CARGA-'.strtoupper(bin2hex(random_bytes(5)));
    $before = Grade::count();
    for ($i = 1; $i <= 500; $i++) {
        $code = $batch.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $student = Student::create([
            'student_code' => $code,
            'name' => 'Estudiante de prueba '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'email' => strtolower($code).'@example.invalid',
            'career' => Student::CAREER,
        ]);
        $student->groups()->attach($group->id);
        foreach ([1, 2, 3] as $evaluation) {
            Grade::create([
                'student_id' => $student->id,
                'academic_group_id' => $group->id,
                'evaluation' => $evaluation,
                'score' => random_int(30, 100) / 10,
                'recorded_on' => now()->toDateString(),
                'created_by' => $admin->id,
                'observations' => 'Datos ficticios para pruebas. Lote '.$batch,
            ]);
        }
    }
    $added = Grade::count() - $before;
    if ($added !== 1500) {
        throw new RuntimeException('La carga no produjo exactamente 1500 notas.');
    }

    return ['lote' => $batch, 'estudiantes_creados' => 500, 'matriculas_creadas' => 500, 'notas_creadas' => $added, 'notas_totales' => Grade::count(), 'grupo' => $group->code, 'fecha' => now()->toDateString()];
});
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
