<?php

use App\Http\Controllers\AcademicController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EnrollmentController;
use App\Services\MailStatus;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/auth/csrf', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'no-store'));
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/verify', [AuthController::class, 'verify']);
        Route::get('/auth/invitations/{token}', [AuthController::class, 'invitationInfo']);
        Route::post('/auth/invitations/{token}/confirm', [AuthController::class, 'confirm']);
    });
    Route::post('/auth/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1');
    Route::post('/auth/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1');
    Route::put('/auth/password', [AuthController::class, 'changePassword'])->middleware(['academic:password', 'throttle:5,1']);
    Route::get('/enrollment', [EnrollmentController::class, 'show'])->middleware('academic');
    Route::get('/enrollment/pdf', [EnrollmentController::class, 'pdf'])->middleware('academic');
    Route::get('/profile', [AuthController::class, 'profile'])->middleware('academic');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('academic');
    Route::middleware('academic')->controller(AcademicController::class)->group(function () {
        Route::get('/dashboard', 'dashboard');
        Route::get('/options', 'options');
        Route::get('/grades', 'grades');
        Route::post('/grades', 'storeGrade')->middleware('academic:staff');
        Route::put('/grades/{grade}', 'updateGrade')->middleware('academic:staff');
        Route::delete('/grades/{grade}', 'deleteGrade')->middleware('academic:staff');
        Route::post('/grades/{grade}/email', 'emailGrade')->middleware(['academic:staff', 'throttle:10,1']);
        Route::get('/groups', 'groups');
        Route::get('/reports', 'report')->middleware('academic:staff');
        Route::get('/reports/csv', 'csv')->middleware('academic:staff');
        Route::get('/reports/excel', 'excel')->middleware('academic:staff');
        Route::get('/reports/pdf', 'pdf')->middleware('academic:staff');
    });
    Route::middleware('academic:admin')->controller(AcademicController::class)->group(function () {
        Route::get('/system/mail-status', function (MailStatus $mail) {
            $result = $mail->check();

            return response()->json($result, $result['ok'] ? 200 : 503);
        });
        Route::get('/teachers', 'teachers');
        Route::post('/teachers', 'storeTeacher')->middleware('throttle:10,1');
        Route::put('/teachers/{teacher}', 'updateTeacher');
        Route::delete('/teachers/{teacher}', 'deleteTeacher');
        Route::post('/teachers/{teacher}/invite', 'reinvite')->middleware('throttle:5,1');
        Route::post('/teachers/{teacher}/reset-access', 'resetTeacherAccess')->middleware('throttle:5,1');
        Route::get('/students', 'students');
        Route::post('/students', 'storeStudent');
        Route::put('/students/{student}', 'updateStudent');
        Route::delete('/students/{student}', 'deleteStudent');
        Route::post('/students/{student}/invite', 'reinviteStudent')->middleware('throttle:5,1');
        Route::post('/students/{student}/reset-access', 'resetStudentAccess')->middleware('throttle:5,1');
        Route::post('/groups', 'storeGroup');
        Route::put('/groups/{group}', 'updateGroup');
        Route::delete('/groups/{group}', 'deleteGroup');
    });
});
