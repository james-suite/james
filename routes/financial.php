<?php

use App\Http\Controllers\FinanceDashboardChartController;
use App\Http\Controllers\FinanceDashboardController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\FinancialCreditCardController;
use App\Http\Controllers\FinancialCreditCardInvoiceController;
use App\Http\Controllers\FinancialRecurrenceController;
use App\Http\Controllers\FinancialTagController;
use App\Http\Controllers\FinancialTransactionController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::prefix('financial')->name('financial.')->group(function () {
    Route::get('/dashboard', FinanceDashboardController::class)->name('dashboard');
    Route::get('/dashboard/chart-data', FinanceDashboardChartController::class)->name('dashboard.chart-data');

    // Reports
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports');

    // Accounts
    Route::get('/accounts/trashed', [FinancialAccountController::class, 'trashed'])->name('accounts.trashed');
    Route::patch('/accounts/{financialAccount}/restore', [FinancialAccountController::class, 'restore'])->name('accounts.restore')->withTrashed();
    Route::delete('/accounts/{financialAccount}/force', [FinancialAccountController::class, 'forceDestroy'])->name('accounts.forceDestroy')->withTrashed();
    Route::post('/accounts/{financialAccount}/adjust-balance', [FinancialAccountController::class, 'adjustBalance'])->name('accounts.adjust-balance');
    Route::resource('accounts', FinancialAccountController::class)->parameters([
        'accounts' => 'financialAccount',
    ]);

    // Tags
    Route::post('/tags/defaults', [FinancialTagController::class, 'storeDefaults'])->name('tags.defaults');
    Route::resource('tags', FinancialTagController::class)->parameters([
        'tags' => 'financialTag',
    ]);

    // Cards
    Route::get('/cards/trashed', [FinancialCreditCardController::class, 'trashed'])->name('cards.trashed');
    Route::patch('/cards/{card}/restore', [FinancialCreditCardController::class, 'restore'])->name('cards.restore')->withTrashed();
    Route::delete('/cards/{card}/force', [FinancialCreditCardController::class, 'forceDestroy'])->name('cards.forceDestroy')->withTrashed();
    Route::resource('cards', FinancialCreditCardController::class);
    Route::get('cards/{card}/invoices/{invoice}', [FinancialCreditCardInvoiceController::class, 'show'])->name('cards.invoices.show');
    Route::put('cards/{card}/invoices/{invoice}', [FinancialCreditCardInvoiceController::class, 'update'])->name('cards.invoices.update');
    Route::post('cards/{card}/invoices/{invoice}/pay', [FinancialCreditCardInvoiceController::class, 'pay'])->name('cards.invoices.pay');
    Route::post('cards/{card}/invoices/{invoice}/unpay', [FinancialCreditCardInvoiceController::class, 'unpay'])->name('cards.invoices.unpay');

    // Transactions
    Route::get('/transactions/trashed', [FinancialTransactionController::class, 'trashed'])->name('transactions.trashed');
    Route::patch('/transactions/{transaction}/restore', [FinancialTransactionController::class, 'restore'])->name('transactions.restore')->withTrashed();
    Route::delete('/transactions/{transaction}/force', [FinancialTransactionController::class, 'forceDestroy'])->name('transactions.forceDestroy')->withTrashed();
    Route::post('transactions/transfer', [FinancialTransactionController::class, 'storeTransfer'])->name('transactions.transfer.store');
    Route::get('transactions/{transaction}/transfer/edit', [FinancialTransactionController::class, 'editTransfer'])->name('transactions.transfer.edit');
    Route::put('transactions/{transaction}/transfer', [FinancialTransactionController::class, 'updateTransfer'])->name('transactions.transfer.update');
    Route::post('transactions/import-nfce', [FinancialTransactionController::class, 'importNfce'])->name('transactions.nfce.import');
    Route::get('transactions/import-nfce/retry', [FinancialTransactionController::class, 'retryNfceImport'])->name('transactions.nfce.retry')->middleware('signed');
    Route::resource('transactions', FinancialTransactionController::class)->parameters([
        'transactions' => 'transaction',
    ]);

    // Recurrences
    Route::get('/recurrences/trashed', [FinancialRecurrenceController::class, 'trashed'])->name('recurrences.trashed');
    Route::patch('/recurrences/{recurrence}/restore', [FinancialRecurrenceController::class, 'restore'])->name('recurrences.restore')->withTrashed();
    Route::delete('/recurrences/{recurrence}/force', [FinancialRecurrenceController::class, 'forceDestroy'])->name('recurrences.forceDestroy')->withTrashed();
    Route::resource('recurrences', FinancialRecurrenceController::class);
});
