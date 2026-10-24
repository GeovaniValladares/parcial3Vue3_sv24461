<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('teacher');
            $t->string('phone')->nullable();
            $t->boolean('must_change_password')->default(true);
            $t->text('totp_secret')->nullable();
            $t->timestamp('totp_confirmed_at')->nullable();
            $t->unsignedBigInteger('totp_last_counter')->nullable();
            $t->string('invitation_token', 64)->nullable()->unique();
            $t->timestamp('invitation_expires_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
        });
        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->string('student_code', 20)->unique();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('career');
            $t->timestamps();
        });
        Schema::create('academic_groups', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('subject');
            $t->string('cycle', 20);
            $t->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('academic_group_id')->constrained()->cascadeOnDelete();
            $t->unique(['student_id', 'academic_group_id']);
        });
        Schema::create('grades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('academic_group_id')->constrained()->restrictOnDelete();
            $t->unsignedTinyInteger('evaluation');
            $t->decimal('score', 4, 2);
            $t->date('recorded_on');
            $t->text('observations')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['student_id', 'academic_group_id', 'evaluation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('academic_groups');
        Schema::dropIfExists('students');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'phone', 'must_change_password', 'totp_secret', 'totp_confirmed_at', 'totp_last_counter', 'invitation_token', 'invitation_expires_at', 'last_login_at']));
    }
};
