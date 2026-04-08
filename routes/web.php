<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\PlacementController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TutoringImportController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/inscriptions', [InscriptionController::class, 'index'])->name('inscriptions.index');
Route::post('/inscriptions/check-tier', [InscriptionController::class, 'checkTier'])
    ->name('inscriptions.check-tier')
    ->middleware('throttle:10,1');

Route::post('/webhooks/helloasso', [WebhookController::class, 'handle'])
    ->name('webhooks.helloasso')
    ->middleware('throttle:60,1');

Route::get('/placement', [PublicController::class, 'placement'])->name('public.placement');
Route::get('/placement/data', [PublicController::class, 'placementData'])->name('public.placement-data')->middleware('throttle:30,1');
Route::match(['get', 'post'], '/placement/mon-numero', [PublicController::class, 'monNumero'])->name('public.mon-numero')->middleware('throttle:10,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/sync', [SyncController::class, 'run'])->name('sync.run');
    Route::post('/sync/chunk', [SyncController::class, 'chunk'])->name('sync.chunk');
    Route::post('/sync/verify', [SyncController::class, 'verify'])->name('sync.verify');

    Route::post('/placement/run', [PlacementController::class, 'run'])->name('placement.run');
    Route::post('/placement/reset', [PlacementController::class, 'reset'])->name('placement.reset');
    Route::post('/placement/assign-numbers', [PlacementController::class, 'assignNumbers'])->name('placement.assign-numbers');

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/errors', [StudentController::class, 'errors'])->name('students.errors');
    Route::get('/students/recuperation', [StudentController::class, 'recuperation'])->name('students.recuperation');
    Route::get('/students/manual-placements', [StudentController::class, 'manualPlacements'])->name('students.manual-placements');
    Route::patch('/students/{student}/assign', [StudentController::class, 'assign'])->name('students.assign');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::get('/amphitheaters/{amphi}', [StudentController::class, 'byAmphi'])->name('students.amphi');

    Route::get('/export/amphi/{amphi}', [ExportController::class, 'amphi'])->name('export.amphi');
    Route::get('/export/emargement/{amphi}', [ExportController::class, 'emargement'])->name('export.emargement');
    Route::get('/export/all', [ExportController::class, 'allAmphis'])->name('export.all');
    Route::get('/export/student-placement/check', [ExportController::class, 'studentPlacementCheck'])->name('export.student-placement.check');
    Route::get('/export/student-placement', [ExportController::class, 'studentPlacement'])->name('export.student-placement');
    Route::get('/export/recuperation-no-option-emails', [ExportController::class, 'recuperationNoOptionEmails'])->name('export.recuperation-no-option-emails');

    Route::get('/admin/tutoring-import', [TutoringImportController::class, 'index'])
        ->name('admin.tutoring-import');
    Route::post('/admin/tutoring-import', [TutoringImportController::class, 'store'])
        ->name('admin.tutoring-import.store')
        ->middleware('throttle:10,1');

    Route::prefix('emargement')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/{amphi}/data', [AttendanceController::class, 'data'])->name('attendance.data');
        Route::patch('/{student}/toggle', [AttendanceController::class, 'toggle'])->name('attendance.toggle');
        Route::post('/{amphi}/reset', [AttendanceController::class, 'resetAmphi'])->name('attendance.reset');
    });
});
