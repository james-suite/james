<?php

use App\Enums\NotificationLevel;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('inherits recurrence tag roles on newly generated occurrences', function (string $destination, bool $hasTags, bool $hasPrimary): void {
    Carbon::setTestNow('2026-03-31 12:00:00');
    $account = FinancialAccount::factory()->create();
    $card = $destination === 'card'
        ? FinancialCreditCard::factory()->create(['financial_account_id' => $account->id])
        : null;
    $recurrence = FinancialRecurrence::factory()->create([
        'financial_account_id' => $card ? null : $account->id,
        'financial_credit_card_id' => $card?->id,
        'start_date' => '2026-01-31',
        'next_processing_date' => '2026-01-31',
    ]);
    $expectedTags = [];

    if ($hasTags) {
        $primaryTag = FinancialTag::factory()->create();
        $secondaryTag = FinancialTag::factory()->create();
        $expectedTags = [
            $primaryTag->id => $hasPrimary,
            $secondaryTag->id => false,
        ];
        $recurrence->tags()->sync([
            $primaryTag->id => ['is_primary' => $hasPrimary],
            $secondaryTag->id => ['is_primary' => false],
        ]);
    }

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    $transactions = $recurrence->transactions()->with('tags')->get();

    expect($transactions)->toHaveCount(3);

    foreach ($transactions as $transaction) {
        expect($transaction->tags->mapWithKeys(
            fn (FinancialTag $tag): array => [$tag->id => (bool) $tag->pivot->is_primary]
        )->sortKeys()->all())->toBe(collect($expectedTags)->sortKeys()->all());
    }

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    expect($recurrence->transactions()->count())->toBe(3);
})->with([
    'account with primary and secondary tags' => ['account', true, true],
    'card with primary and secondary tags' => ['card', true, true],
    'account with only secondary tags' => ['account', true, false],
    'card with only secondary tags' => ['card', true, false],
    'account without tags' => ['account', false, false],
    'card without tags' => ['card', false, false],
]);

it('notifies users when recurrences are processed with count and total', function () {
    $account = FinancialAccount::factory()->create();

    FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 100.00,
        'frequency' => 'monthly',
        'is_active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'next_processing_date' => Carbon::today(),
    ]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    Notification::assertSentTo($this->user, GeneralNotification::class, function ($notification) {
        return $notification->title === 'Recorrências Processadas'
            && $notification->level === NotificationLevel::Info
            && isset($notification->details['Quantidade'])
            && isset($notification->details['Valor Total']);
    });
});

it('does not notify when no eligible recurrences exist', function () {
    // Recurrence with next_processing_date in the future
    FinancialRecurrence::factory()->create([
        'is_active' => true,
        'next_processing_date' => Carbon::today()->addMonth(),
        'start_date' => Carbon::today()->subMonth(),
    ]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not notify when all recurrences are inactive', function () {
    FinancialRecurrence::factory()->create([
        'is_active' => false,
        'next_processing_date' => Carbon::today(),
        'start_date' => Carbon::today()->subMonth(),
    ]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    Notification::assertNothingSent();
});

it('includes aggregated total amount in the notification details', function () {
    $account = FinancialAccount::factory()->create();

    FinancialRecurrence::factory()->count(2)->create([
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 50.00,
        'frequency' => 'monthly',
        'is_active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'next_processing_date' => Carbon::today(),
    ]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    Notification::assertSentTo($this->user, GeneralNotification::class, function ($notification) {
        return str_contains($notification->details['Quantidade'], '2')
            && str_contains($notification->details['Valor Total'], '100');
    });
});

it('lists each processed recurrence item in notification details', function () {
    $account = FinancialAccount::factory()->create(['name' => 'Itaú']);

    FinancialRecurrence::factory()->create([
        'title' => 'Assinatura Netflix',
        'financial_account_id' => $account->id,
        'type' => 'expense',
        'amount' => 55.90,
        'frequency' => 'monthly',
        'is_active' => true,
        'start_date' => Carbon::today()->subMonth(),
        'next_processing_date' => Carbon::today(),
    ]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    Notification::assertSentTo($this->user, GeneralNotification::class, function ($notification) {
        return isset($notification->details['1. Assinatura Netflix'])
            && str_contains($notification->details['1. Assinatura Netflix'], '55,90')
            && str_contains($notification->details['1. Assinatura Netflix'], 'Conta Itaú');
    });
});

it('does not advance, reports failure, and retries a failed occurrence later', function () {
    $account = FinancialAccount::factory()->create();
    $processingDate = Carbon::today();
    $recurrence = FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'start_date' => $processingDate,
        'next_processing_date' => $processingDate,
    ]);
    $shouldFail = true;

    FinancialTransaction::creating(function () use (&$shouldFail): void {
        if ($shouldFail) {
            throw new RuntimeException('Falha simulada ao criar a transação.');
        }
    });

    $this->artisan('finance:process-recurrences')->assertFailed();

    expect($recurrence->fresh()->next_processing_date->toDateString())->toBe($processingDate->toDateString())
        ->and($recurrence->transactions()->count())->toBe(0);
    Notification::assertSentTo($this->user, GeneralNotification::class, function (GeneralNotification $notification): bool {
        return $notification->title === 'Falha no processamento de recorrências'
            && $notification->level === NotificationLevel::Danger;
    });

    $shouldFail = false;

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    expect($recurrence->fresh()->next_processing_date->toDateString())
        ->toBe($processingDate->copy()->addMonthNoOverflow()->toDateString())
        ->and($recurrence->transactions()->count())->toBe(1);
    Notification::assertSentTo($this->user, GeneralNotification::class);
});

it('keeps monthly recurrence dates anchored at the end of the month', function () {
    Carbon::setTestNow('2026-03-31 12:00:00');
    $account = FinancialAccount::factory()->create();
    $recurrence = FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'frequency' => 'monthly',
        'start_date' => '2026-01-31',
        'next_processing_date' => '2026-01-31',
    ]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    expect($recurrence->fresh()->next_processing_date->toDateString())->toBe('2026-04-30')
        ->and($recurrence->transactions()->orderBy('date')->get()->map(
            fn (FinancialTransaction $transaction): string => $transaction->date->toDateString()
        )->all())
        ->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('keeps yearly recurrence dates anchored across leap years', function () {
    Carbon::setTestNow('2027-02-28 12:00:00');
    $account = FinancialAccount::factory()->create();
    $recurrence = FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'frequency' => 'yearly',
        'start_date' => '2024-02-29',
        'next_processing_date' => '2027-02-28',
    ]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    expect($recurrence->fresh()->next_processing_date->toDateString())->toBe('2028-02-29')
        ->and($recurrence->transactions()->sole()->date->toDateString())->toBe('2027-02-28');
});

it('reconciles an existing occurrence without creating or notifying a duplicate', function () {
    Carbon::setTestNow('2026-01-31 12:00:00');
    $account = FinancialAccount::factory()->create();
    $recurrence = FinancialRecurrence::factory()->create([
        'financial_account_id' => $account->id,
        'frequency' => 'monthly',
        'start_date' => '2026-01-31',
        'next_processing_date' => '2026-01-31',
    ]);
    $tag = FinancialTag::factory()->create();
    $recurrence->tags()->attach($tag->id, ['is_primary' => true]);
    $transaction = FinancialTransaction::factory()->posted()->create([
        'financial_account_id' => $account->id,
        'financial_recurrence_id' => $recurrence->id,
        'date' => '2026-01-31',
    ]);
    $transaction->tags()->attach($tag->id, ['is_primary' => false]);

    $this->artisan('finance:process-recurrences')->assertSuccessful();

    expect($recurrence->transactions()->count())->toBe(1)
        ->and($recurrence->fresh()->next_processing_date->toDateString())->toBe('2026-02-28');
    $tags = $transaction->fresh()->tags;
    expect($tags)->toHaveCount(1)
        ->and((bool) $tags->sole()->pivot->is_primary)->toBeFalse();
    Notification::assertNothingSent();
});
