<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\FinancialTagController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/settings', 'settings')->name('settings');

    Route::get('/ui/icons/{name}', [FinancialTagController::class, 'fetchIcon'])->name('ui.icons.show');
    Route::get('/attachments/{media}/{filename?}', [AttachmentController::class, 'download'])
        ->middleware('signed')
        ->name('attachments.download');

    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/audit/{activity}', [AuditController::class, 'show'])->name('audit.show');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    require __DIR__.'/contacts.php';
    require __DIR__.'/financial.php';
    require __DIR__.'/settlements.php';
});
