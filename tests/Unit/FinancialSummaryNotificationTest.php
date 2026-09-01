<?php

use App\Enums\NotificationLevel;
use App\Models\User;
use App\Notifications\FinancialSummaryNotification;
use Illuminate\Support\Facades\URL;
use NotificationChannels\Telegram\TelegramChannel;

function financialSummary(): array
{
    return [
        'period' => 'Agosto de 2026',
        'previous_period' => 'Julho de 2026',
        'income' => 5000.0,
        'income_variation' => 1000.0,
        'expense' => 3200.0,
        'expense_variation' => -400.0,
        'net' => 1800.0,
        'net_variation' => 1400.0,
        'account_balance' => 12000.0,
        'pending_commitments' => 2500.0,
        'net_balance' => 9500.0,
        'income_categories' => [
            ['id' => 1, 'name' => 'Salário', 'icon' => 'heroicon-o-banknotes', 'color' => '#16a34a', 'amount' => 5000.0, 'percentage' => 100.0],
        ],
        'expense_categories' => [
            ['id' => 2, 'name' => 'Moradia', 'icon' => 'heroicon-o-home', 'color' => '#dc2626', 'amount' => 1800.0, 'percentage' => 56.3],
            ['id' => 3, 'name' => 'Alimentação', 'icon' => 'heroicon-o-receipt-percent', 'color' => '#f59e0b', 'amount' => 700.0, 'percentage' => 21.9],
            ['id' => 4, 'name' => 'Lazer', 'icon' => 'heroicon-o-flag', 'color' => '#8b5cf6', 'amount' => 400.0, 'percentage' => 12.5],
            ['id' => 5, 'name' => 'Outros', 'icon' => 'heroicon-o-tag', 'color' => '#9ca3af', 'amount' => 300.0, 'percentage' => 9.3],
        ],
    ];
}

it('stores a structured financial summary for the James notification view', function () {
    $notification = new FinancialSummaryNotification(financialSummary(), NotificationLevel::Success);

    expect($notification->toDatabase(new User))->toMatchArray([
        'title' => 'Resumo Financeiro — Agosto de 2026',
        'action_url' => route('financial.dashboard'),
        'action_label' => 'Abrir painel financeiro',
        'level' => 'success',
        'presentation' => 'financial-summary',
        'financial_summary' => financialSummary(),
    ]);
});

it('sends a compact telegram summary with a direct notification link', function () {
    config([
        'services.telegram-bot-api.token' => 'fake-token',
        'services.telegram-bot-api.chat_id' => '999999',
    ]);
    URL::forceRootUrl('https://james.test');

    $notification = new FinancialSummaryNotification(financialSummary(), NotificationLevel::Success);
    $notification->id = 'summary-notification-id';

    $payload = $notification->toTelegram(new User)->toArray();
    $button = json_decode($payload['reply_markup'], true)['inline_keyboard'][0][0];

    expect($payload['text'])->toContain('*RESUMO FINANCEIRO — Agosto de 2026*')
        ->and($payload['text'])->toContain('Receitas: '.formatCurrency(5000))
        ->and($payload['text'])->toContain('Despesas: '.formatCurrency(3200))
        ->and($payload['text'])->toContain('Saldo líquido: + '.formatCurrency(9500))
        ->and($payload['text'])->not->toContain('Moradia')
        ->and($button['text'])->toBe('Ver detalhes')
        ->and($button['url'])->toBe(route('notifications.show', 'summary-notification-id'));
});

it('uses the dedicated markdown template for the email summary', function () {
    $notification = new FinancialSummaryNotification(financialSummary(), NotificationLevel::Success);
    $notification->id = 'summary-notification-id';
    $user = new User(['name' => 'Arthur', 'email' => 'arthur@example.com']);

    $mail = $notification->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('[James] Resumo Financeiro — Agosto de 2026')
        ->and($mail->markdown)->toBe('emails.notifications.financial-summary')
        ->and($mail->viewData['summary'])->toBe(financialSummary())
        ->and($mail->viewData['detailUrl'])->toContain('/notifications/summary-notification-id')
        ->and($html)->toContain('Resumo financeiro de Agosto de 2026')
        ->and($html)->toContain('Moradia')
        ->and($html)->not->toContain('Outros');
});

it('uses only configured delivery channels', function () {
    config([
        'services.notifications.mail' => true,
        'services.telegram-bot-api.token' => 'fake-token',
        'services.telegram-bot-api.chat_id' => '999999',
    ]);

    $notification = new FinancialSummaryNotification(financialSummary(), NotificationLevel::Success);
    $user = new User(['email' => 'arthur@example.com']);

    expect($notification->via($user))->toContain('database', 'mail', TelegramChannel::class);
});
