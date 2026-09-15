<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('financial_transactions')
            ->where('status', 'posted')
            ->whereIn('id', function ($query): void {
                $query->select('financial_transaction_id')
                    ->from('settlement_groups')
                    ->whereNotNull('financial_transaction_id');
            })
            ->whereIn('financial_credit_card_invoice_id', function ($query): void {
                $query->select('id')
                    ->from('financial_credit_card_invoices')
                    ->whereNull('paid_at');
            })
            ->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original status cannot be safely inferred after this correction.
    }
};
