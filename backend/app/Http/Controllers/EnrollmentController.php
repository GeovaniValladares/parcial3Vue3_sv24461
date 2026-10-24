<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    private function enrollment(Request $request): array
    {
        abort_unless($request->user()->role === 'student', 403, 'Esta sección es exclusiva para estudiantes.');
        $request->validate(['cycle' => 'nullable|string|max:20', 'student_id' => 'prohibited', 'user_id' => 'prohibited']);
        $student = Student::where('user_id', $request->user()->id)->first();
        abort_unless($student, 404, 'No se encontró tu ficha estudiantil. Contacta al administrador.');
        $groups = $student->groups()->with('teacher:id,name')->orderByDesc('academic_groups.created_at')->orderByDesc('academic_groups.id')->get();
        $cycles = $groups->pluck('cycle')->unique()->values();
        $cycle = $request->filled('cycle') ? $request->query('cycle') : $cycles->first();
        if ($cycle !== null && ! $cycles->contains($cycle)) {
            throw ValidationException::withMessages(['cycle' => 'El ciclo seleccionado no pertenece a tus inscripciones.']);
        }

        return [
            'student' => $student->only(['name', 'student_code', 'email', 'career']),
            'cycle' => $cycle,
            'cycles' => $cycles,
            'groups' => $groups->where('cycle', $cycle)->sortBy('subject')->values()->map(fn ($group) => [
                'code' => $group->code,
                'subject' => $group->subject,
                'cycle' => $group->cycle,
                'teacher' => $group->teacher?->name,
            ]),
        ];
    }

    public function show(Request $request)
    {
        return response()->json($this->enrollment($request))->header('Cache-Control', 'private, no-store');
    }

    public function pdf(Request $request)
    {
        $data = $this->enrollment($request);
        abort_if($data['groups']->isEmpty(), 422, 'Aún no tienes asignaturas inscritas para generar tu boleta.');
        $filename = 'boleta-inscripcion-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $data['student']['student_code']).'.pdf';

        return Pdf::loadView('reports.enrollment', $data + ['issuedAt' => now()])
            ->setPaper('a4', 'portrait')
            ->stream($filename)
            ->header('Cache-Control', 'private, no-store');
    }
}
