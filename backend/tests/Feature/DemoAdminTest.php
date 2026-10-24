<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DemoAdminAccount;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class DemoAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_install_creates_demo_data_and_admin_without_otp(): void
    {
        Mail::fake();
        $initialFile = base_path('../.runtime/acceso-inicial.txt');
        $previousHash = is_file($initialFile) ? hash_file('sha256', $initialFile) : null;
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('academic_groups', 3);
        $this->assertDatabaseCount('students', 12);
        $this->assertDatabaseCount('enrollments', 36);
        $this->assertDatabaseCount('grades', 72);
        $this->postJson('/api/auth/login', ['email' => DemoAdminAccount::EMAIL, 'password' => DemoAdminAccount::PASSWORD])->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('user.must_change_password', false)->assertJsonPath('user.totp_confirmed_at', null);
        $this->getJson('/api/dashboard?month=2026-10')->assertOk()->assertJsonPath('stats.students', 12)->assertJsonPath('stats.teachers', 6)->assertJsonPath('stats.grades', 36);
        Mail::assertNothingSent();
        $this->assertSame($previousHash, is_file($initialFile) ? hash_file('sha256', $initialFile) : null);
    }

    public function test_reseeding_does_not_duplicate_or_change_existing_accounts(): void
    {
        $owner = User::factory()->create(['role' => 'admin', 'password' => 'Owner@Existing2026!']);
        $this->seed(DemoAdminSeeder::class);
        $demo = User::where('email', DemoAdminAccount::EMAIL)->firstOrFail();
        $demo->update(['password' => 'Changed@Demo2026!']);
        $this->seed(DemoAdminSeeder::class);
        $this->assertSame(1, User::where('email', DemoAdminAccount::EMAIL)->count());
        $this->assertTrue(Hash::check('Changed@Demo2026!', $demo->fresh()->password));
        $this->assertTrue(Hash::check('Owner@Existing2026!', $owner->fresh()->password));
    }

    public function test_reset_command_only_restores_demo_account_and_revokes_its_sessions(): void
    {
        $demo = app(DemoAdminAccount::class)->ensure();
        $owner = User::factory()->create(['role' => 'admin', 'password' => 'Owner@Existing2026!']);
        $demo->update(['password' => 'Changed@Demo2026!', 'must_change_password' => true]);
        DB::table('sessions')->insert(['id' => 'demo-session', 'user_id' => $demo->id, 'payload' => '', 'last_activity' => time()]);
        $this->artisan('uls:demo-admin', ['--reset' => true])->assertExitCode(0);
        $this->assertTrue(Hash::check(DemoAdminAccount::PASSWORD, $demo->fresh()->password));
        $this->assertFalse($demo->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Owner@Existing2026!', $owner->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'demo-session']);
    }

    public function test_demo_is_not_created_in_production(): void
    {
        $this->app['env'] = 'production';
        app(DemoAdminSeeder::class)->run();
        $this->artisan('uls:demo-admin')->assertExitCode(1);
        $this->assertDatabaseMissing('users', ['email' => DemoAdminAccount::EMAIL]);
    }

    public function test_existing_non_admin_is_never_promoted_by_demo_setup(): void
    {
        $existing = User::factory()->create(['email' => DemoAdminAccount::EMAIL, 'role' => 'teacher']);
        try {
            app(DemoAdminAccount::class)->ensure();
            $this->fail('La cuenta existente no debe convertirse en administrador.');
        } catch (RuntimeException $error) {
            $this->assertSame('teacher', $existing->fresh()->role);
        }
    }
}
