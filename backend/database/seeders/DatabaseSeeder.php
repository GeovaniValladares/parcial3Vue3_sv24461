<?php

namespace Database\Seeders;

use App\Models\AcademicGroup;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Database\Seeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::where('role', 'admin')->exists()) {
            $this->call(DemoAdminSeeder::class);
            $this->command?->info('Ya existen datos iniciales; no se modificaron.');

            return;
        }
        $token = Str::random(64);
        $password = Str::password(16);
        $admin = User::create(['name' => 'José Valladares', 'email' => 'admin@uls.edu.sv', 'password' => $password, 'role' => 'admin', 'must_change_password' => true, 'totp_secret' => app(Totp::class)->secret(), 'invitation_token' => hash('sha256', $token), 'invitation_expires_at' => now()->addDays(3)]);
        if (! app()->environment('testing')) {
        $folder = base_path('../.runtime');
        if (! is_dir($folder)) {
            mkdir($folder, 0700, true);
        }
        file_put_contents($folder.'/acceso-inicial.txt', "ACCESO INICIAL DE PRACTICA ULS\nUsuario: admin@uls.edu.sv\nClave temporal: $password\nConfigurar Google Authenticator: ".rtrim(config('app.frontend_url'), '/')."/activar/$token\n\nAdministrador: inicia sesión con tu correo y contraseña, sin código de autenticación. En tu primer acceso, el panel mostrará el modal para cambiar la clave.\n");
        }
        $teachers = [];
        foreach (['Ana Martínez', 'Carlos Hernández', 'María López', 'Roberto Pérez', 'Gabriela Flores', 'Luis Ramírez'] as $i => $name) {
            $teachers[] = User::create(['name' => $name, 'email' => 'docente'.($i + 1).'@example.com', 'password' => Str::password(20), 'role' => 'teacher', 'phone' => '7000-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)]);
        }
        $groups = [];
        foreach (['Programación de aplicaciones web', 'Base de datos II', 'Redes de computadoras'] as $i => $subject) {
            $groups[] = AcademicGroup::create(['code' => ['PRG-02', 'BDD-01', 'RED-01'][$i], 'subject' => $subject, 'cycle' => 'II-2026', 'teacher_id' => $teachers[$i]->id]);
        }
        $names = ['Sofía Hernández', 'Diego Martínez', 'Valeria López', 'Andrés García', 'Camila Rodríguez', 'Gabriel Pérez', 'Daniela Cruz', 'Mateo Sánchez', 'Isabella Flores', 'Alejandro Ramos', 'Lucía Morales', 'Samuel Aguilar'];
        foreach ($names as $i => $name) {
            $student = Student::create(['student_code' => 'SV'.(24401 + $i), 'name' => $name, 'email' => 'estudiante'.($i + 1).'@example.com', 'career' => 'Licenciatura en ciencias de la computacion']);
            $student->groups()->sync(array_map(fn ($g) => $g->id, $groups));
            foreach ($groups as $j => $g) {
                foreach ([1, 2] as $evaluation) {
                    Grade::create(['student_id' => $student->id, 'academic_group_id' => $g->id, 'evaluation' => $evaluation, 'score' => [8.5, 9.2, 5.3, 7.8, 9.5, 6.4, 8.8, 4.7, 7.3, 8.2, 9.1, 5.6][($i + $j) % 12], 'recorded_on' => $evaluation === 1 ? '2026-09-18' : '2026-10-16', 'created_by' => $admin->id, 'observations' => 'Evaluación de práctica.']);
                }
            }
        }
        $this->call(DemoAdminSeeder::class);
        $this->command?->info('Datos de práctica creados. El administrador de evaluación está disponible para la prueba. No se enviaron correos.');
    }
}
