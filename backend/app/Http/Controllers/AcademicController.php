<?php

namespace App\Http\Controllers;

use App\Models\AcademicGroup;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\InvitationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcademicController extends Controller
{
    private function groupQuery(Request $r)
    {
        return AcademicGroup::query()->when($r->user()->role === 'teacher', fn ($q) => $q->where('teacher_id', $r->user()->id))
            ->when($r->user()->role === 'student', fn ($q) => $q->whereHas('students', fn ($s) => $s->where('user_id', $r->user()->id)));
    }

    private function gradeQuery(Request $r)
    {
        $r->validate(['month' => 'nullable|date_format:Y-m', 'group_id' => 'nullable|integer|exists:academic_groups,id', 'evaluation' => 'nullable|integer|between:1,3', 'search' => 'nullable|string|max:100']);

        return Grade::query()->with(['student', 'group.teacher'])->when($r->user()->role === 'student', fn ($q) => $q->whereHas('student', fn ($s) => $s->where('user_id', $r->user()->id)))->whereHas('group', fn ($q) => $q->when($r->user()->role === 'teacher', fn ($q) => $q->where('teacher_id', $r->user()->id)))->when($r->filled('month'), fn ($q) => $q->whereBetween('recorded_on', [$r->month.'-01', date('Y-m-t', strtotime($r->month.'-01'))]))->when($r->filled('group_id'), fn ($q) => $q->where('academic_group_id', $r->group_id))->when($r->filled('evaluation'), fn ($q) => $q->where('evaluation', $r->evaluation))->when($r->filled('search'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where(fn ($s) => $s->where('name', 'like', '%'.$r->search.'%')->orWhere('student_code', 'like', '%'.$r->search.'%'))));
    }

    private function ownGroup(Request $r, int $id): AcademicGroup
    {
        return $this->groupQuery($r)->findOrFail($id);
    }

    public function options(Request $r)
    {
        $groups = $this->groupQuery($r)->with(['students' => fn ($q) => $q->select('students.id', 'name', 'student_code')->when($r->user()->role === 'student', fn ($q) => $q->where('user_id', $r->user()->id))])->get();

        return ['groups' => $groups, 'students' => $r->user()->role === 'admin' ? Student::orderBy('name')->get() : Student::when($r->user()->role === 'student', fn ($q) => $q->where('user_id', $r->user()->id), fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('teacher_id', $r->user()->id)))->orderBy('name')->get(), 'teachers' => $r->user()->role === 'admin' ? User::where('role', 'teacher')->orderBy('name')->get() : []];
    }

    public function dashboard(Request $r)
    {
        $grades = $this->gradeQuery($r)->get();
        $averages = $grades->groupBy('student_id')->map(fn ($rows) => $rows->avg('score'));
        $passed = $averages->filter(fn ($v) => $v >= 6)->count();
        $groups = $this->groupQuery($r)->with(['teacher:id,name'])->withCount(['students', 'grades'])->get();

        if ($r->user()->role === 'student') {
            return ['recent' => $grades->sortByDesc('updated_at')->take(5)->values(), 'groups' => $groups->take(3)->values()];
        }

        return ['stats' => ['students' => $r->user()->role === 'admin' ? Student::count() : Student::when($r->user()->role === 'student', fn ($q) => $q->where('user_id', $r->user()->id), fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('teacher_id', $r->user()->id)))->count(), 'teachers' => User::where('role', 'teacher')->count(), 'groups' => $groups->count(), 'average' => round($grades->avg('score') ?? 0, 2), 'passed' => $passed, 'failed' => $averages->count() - $passed, 'graded_students' => $averages->count(), 'grades' => $grades->count()], 'recent' => $grades->sortByDesc('updated_at')->take(5)->values(), 'groups' => $groups->take(3)->values()];
    }

    public function grades(Request $r)
    {
        return $this->gradeQuery($r)->latest('updated_at')->paginate(5);
    }

    private function gradeData(Request $r, ?Grade $grade = null): array
    {
        $d = $r->validate(['student_id' => 'required|integer|exists:students,id', 'academic_group_id' => 'required|integer|exists:academic_groups,id', 'evaluation' => 'required|integer|between:1,3', 'score' => 'required|numeric|between:0,10', 'recorded_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'observations' => 'nullable|string|max:1000']);
        $g = $this->ownGroup($r, $d['academic_group_id']);
        if (! $g->students()->where('students.id', $d['student_id'])->exists()) {
            throw ValidationException::withMessages(['student_id' => 'El estudiante no está inscrito en este grupo.']);
        }$duplicate = Grade::where('student_id', $d['student_id'])->where('academic_group_id', $g->id)->where('evaluation', $d['evaluation'])->when($grade, fn ($q) => $q->where('id', '!=', $grade->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['evaluation' => 'Ya existe una nota para este estudiante, grupo y evaluación.']);
        }

        return $d;
    }

    public function storeGrade(Request $r)
    {
        $d = $this->gradeData($r);

        return response()->json(Grade::create($d + ['created_by' => $r->user()->id])->load(['student', 'group']), 201);
    }

    public function updateGrade(Request $r, Grade $grade)
    {
        $this->ownGroup($r, $grade->academic_group_id);
        $grade->update($this->gradeData($r, $grade));

        return $grade->fresh()->load(['student', 'group']);
    }

    public function deleteGrade(Request $r, Grade $grade)
    {
        $this->ownGroup($r, $grade->academic_group_id);
        $grade->delete();

        return ['message' => 'Nota eliminada.'];
    }

    public function teachers(Request $r)
    {
        return User::where('role', 'teacher')->when($r->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->search.'%')->orWhere('email', 'like', '%'.$r->search.'%')))->latest()->paginate(5);
    }

    public function storeTeacher(Request $r, InvitationService $service)
    {
        $d = $r->validate(['name' => 'required|string|max:150', 'email' => 'required|email|max:255|unique:users', 'phone' => 'nullable|string|max:25']);
        $u = DB::transaction(function () use ($d, $service) {
            $u = User::create($d + ['password' => bin2hex(random_bytes(20)), 'role' => 'teacher']);
            $service->send($u);

            return $u;
        });

        return response()->json(['user' => $u, 'message' => 'Docente creado e invitación enviada.'], 201);
    }

    public function updateTeacher(Request $r, User $teacher, InvitationService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        $d = $r->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($teacher->id)], 'phone' => 'nullable|string|max:25']);
        DB::transaction(function () use ($teacher, $d, $service) {
            $changedEmail = $d['email'] !== $teacher->email;
            $teacher->update($d);
            if ($changedEmail) {
                $teacher->update(['email_verified_at' => null]);
                $service->resend($teacher);
                DB::table('sessions')->where('user_id', $teacher->id)->delete();
            }
        });

        return $teacher;
    }

    public function deleteTeacher(User $teacher)
    {
        abort_unless($teacher->role === 'teacher', 404);
        abort_if(AcademicGroup::where('teacher_id', $teacher->id)->exists() || Grade::where('created_by', $teacher->id)->exists(), 422, 'El docente tiene grupos o notas asociadas. Reasigna sus grupos antes de eliminarlo.');
        DB::table('sessions')->where('user_id', $teacher->id)->delete();
        $teacher->delete();

        return ['message' => 'Docente eliminado.'];
    }

    public function reinvite(User $teacher, InvitationService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        DB::transaction(fn () => $service->resend($teacher));

        return ['message' => $teacher->totp_confirmed_at ? 'Correo de acceso reenviado.' : 'Invitación reenviada con una nueva contraseña temporal.'];
    }

    public function resetTeacherAccess(User $teacher, InvitationService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        $service->resetAccess($teacher);

        return ['message' => 'Acceso restablecido. Se envió un enlace para configurar un QR nuevo y una contraseña temporal al correo del docente.'];
    }

    public function resetStudentAccess(Student $student, InvitationService $service)
    {
        DB::transaction(function () use ($student, $service) {
            $this->studentAccount($student);
            abort_unless($student->user->role === 'student', 422, 'La cuenta vinculada no corresponde a un estudiante.');
            $service->resetAccess($student->user);
        });

        return ['message' => 'Acceso restablecido. Se envió un enlace para configurar un QR nuevo y una contraseña temporal al correo del estudiante.'];
    }

    public function students(Request $r)
    {
        return Student::with(['groups:id,code', 'user'])->when($r->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$r->search.'%')->orWhere('student_code', 'like', '%'.$r->search.'%')))->latest()->paginate(5);
    }

    private function studentData(Request $r, ?Student $student = null): array
    {
        return $r->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('students')->ignore($student?->id), Rule::unique('users')->ignore($student?->user_id)], 'student_code' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student?->id), Rule::unique('users')->ignore($student?->user_id)], 'career' => ['required', Rule::in([Student::CAREER])], 'group_ids' => 'present|array', 'group_ids.*' => 'integer|distinct|exists:academic_groups,id']);
    }

    public function storeStudent(Request $r, InvitationService $service)
    {
        $d = $this->studentData($r);
        $student = DB::transaction(function () use ($d, $service) {
            $groups = $d['group_ids'];
            unset($d['group_ids']);
            $s = Student::create($d);
            $s->groups()->sync($groups);
            $this->studentAccount($s);
            $service->send($s->user);

            return $s;
        });

        return response()->json(['student' => $student->fresh('user'), 'message' => 'Estudiante creado e invitación enviada.'], 201);
    }

    public function updateStudent(Request $r, Student $student, InvitationService $service)
    {
        $d = $this->studentData($r, $student);
        $used = Grade::where('student_id', $student->id)->pluck('academic_group_id')->unique();
        abort_if($used->diff($d['group_ids'])->isNotEmpty(), 422, 'No puedes retirar grupos que ya tienen notas.');
        DB::transaction(function () use ($student, $d, $service) {
            $groups = $d['group_ids'];
            unset($d['group_ids']);
            $changedEmail = $student->email !== $d['email'];
            $student->update($d);
            $student->groups()->sync($groups);
            if ($student->user) {
                $student->user->update(['name' => $student->name, 'email' => $student->email]);
                if ($changedEmail) {
                    $student->user->update(['email_verified_at' => null]);
                    $service->resend($student->user);
                    DB::table('sessions')->where('user_id', $student->user_id)->delete();
                }
            }
        });

        return $student->fresh();
    }

    public function deleteStudent(Student $student)
    {
        abort_if(Grade::where('student_id', $student->id)->exists(), 422, 'No puedes eliminar un estudiante con notas registradas.');
        DB::transaction(function () use ($student) {
            $user = $student->user;
            $student->delete();
            if ($user) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();
            }
        });

        return ['message' => 'Estudiante eliminado.'];
    }

    private function studentAccount(Student $student): void
    {
        if ($student->user_id) {
            return;
        }
        if (User::where('email', $student->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este correo ya pertenece a otra cuenta. Edita el correo del estudiante.']);
        }
        $user = User::create(['name' => $student->name, 'email' => $student->email, 'role' => 'student', 'password' => bin2hex(random_bytes(20))]);
        $student->update(['user_id' => $user->id]);
        $student->setRelation('user', $user);
    }

    public function reinviteStudent(Student $student, InvitationService $service)
    {
        DB::transaction(function () use ($student, $service) {
            $this->studentAccount($student);
            $service->resend($student->user);
        });

        return ['message' => $student->user->totp_confirmed_at ? 'Correo de acceso reenviado.' : 'Invitación de activación enviada al estudiante.'];
    }

    public function groups(Request $r)
    {
        return $this->groupQuery($r)->with('teacher:id,name')->withCount(['students', 'grades'])->when($r->search, fn ($q) => $q->where(fn ($q) => $q->where('subject', 'like', '%'.$r->search.'%')->orWhere('code', 'like', '%'.$r->search.'%')))->latest()->paginate(5);
    }

    private function groupData(Request $r, ?AcademicGroup $group = null): array
    {
        return $r->validate(['code' => ['required', 'string', 'max:30', Rule::unique('academic_groups')->ignore($group?->id)], 'subject' => 'required|string|max:150', 'cycle' => 'required|string|max:20', 'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')]]);
    }

    public function storeGroup(Request $r)
    {
        return response()->json(AcademicGroup::create($this->groupData($r)), 201);
    }

    public function updateGroup(Request $r, AcademicGroup $group)
    {
        $group->update($this->groupData($r, $group));

        return $group->fresh();
    }

    public function deleteGroup(AcademicGroup $group)
    {
        abort_if($group->grades()->exists() || $group->students()->exists(), 422, 'El grupo tiene estudiantes o notas asociadas.');
        $group->delete();

        return ['message' => 'Grupo eliminado.'];
    }

    public function report(Request $r)
    {
        $grades = $this->gradeQuery($r)->orderBy('student_id')->get();
        $averages = $grades->groupBy('student_id')->map(fn ($rows) => ['student' => $rows->first()->student, 'average' => round($rows->avg('score'), 2)]);

        return ['grades' => $grades, 'students' => $averages->values(), 'passed' => $averages->filter(fn ($row) => $row['average'] >= 6)->count(), 'failed' => $averages->filter(fn ($row) => $row['average'] < 6)->count(), 'average' => round($grades->avg('score') ?? 0, 2)];
    }

    public function csv(Request $r)
    {
        $rows = $this->gradeQuery($r)->orderBy('student_id')->get();

        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Carné', 'Estudiante', 'Grupo', 'Asignatura', 'Ciclo', 'Evaluación', 'Nota', 'Estado', 'Fecha']);
            foreach ($rows as $g) {
                $safe = fn ($s) => preg_match('/^[=+@\-\t\r]/u', (string) $s) ? "'".$s : $s;
                fputcsv($f, array_map($safe, [$g->student->student_code, $g->student->name, $g->group->code, $g->group->subject, $g->group->cycle, $g->evaluation, $g->score, $g->score >= 6 ? 'Aprobado' : 'Reprobado', $g->recorded_on]));
            }fclose($f);
        }, 'notas-uls.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $r)
    {
        $data = $this->report($r);

        return Pdf::loadView('reports.grades', $data + ['author' => $r->user(), 'month' => $r->month])->setPaper('a4', 'landscape')->download('notas-uls.pdf');
    }

    public function emailGrade(Request $r, Grade $grade)
    {
        $this->ownGroup($r, $grade->academic_group_id);
        $grade->load(['student', 'group']);
        Mail::send('emails.grade', ['grade' => $grade], fn ($m) => $m->to($grade->student->email)->subject('Nota registrada · '.$grade->group->subject));

        return ['message' => 'Nota enviada al correo del estudiante.'];
    }
}
