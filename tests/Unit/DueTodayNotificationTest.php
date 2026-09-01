<?php

use App\Enums\NotificationLevel;
use App\Models\User;
use App\Notifications\DueTodayNotification;
use Illuminate\Support\Facades\URL;
use NotificationChannels\Telegram\TelegramChannel;

function dueAlert(): array
{
    return [
        'alert_date' => '2026-09-01',
        'total_items' => 3,
        'income' => 2500.0,
        'expense' => 990.0,
        'net' => 1510.0,
        'days' => [
            [
                'key' => 'today',
                'label' => 'Hoje',
                'date' => '01/09/2026',
                'incomes' => [[
                    'description' => 'Pró-labore',
                    'amount' => 2500.0,
                    'destination' => 'Conta Principal',
                    'is_recurrence' => false,
                    'is_invoice' => false,
                    'transactions_count' => 0,
                    'recurrences_count' => 0,
                ]],
                'expenses' => [[
                    'description' => 'Internet',
                    'amount' => 90.0,
                    'destination' => 'Conta Principal',
                    'is_recurrence' => true,
                    'is_invoice' => false,
                    'transactions_count' => 0,
                    'recurrences_count' => 0,
                ]],
                'invoices' => [],
            ],
            [
                'key' => 'tomorrow',
                'label' => 'Amanhã',
                'date' => '02/09/2026',
                'incomes' => [],
                'expenses' => [],
                'invoices' => [[
                    'description' => 'Fatura Cartão Principal',
                    'amount' => 900.0,
                    'destination' => 'Cartão Principal',
                    'is_recurrence' => false,
                    'is_invoice' => true,
                    'transactions_count' => 3,
                    'recurrences_count' => 1,
                ]],
            ],
        ],
    ];
}

it('stores a structured due alert for the James notification view', function () {
    $notification = new DueTodayNotification(dueAlert());

    expect($notification->toDatabase(new User))->toMatchArray([
        'title' => 'Vencimentos próximos',
        'action_url' => route('financial.dashboard'),
        'action_label' => 'Ver vencimentos',
        'level' => 'warning',
        'presentation' => 'due-alert',
        'due_alert' => dueAlert(),
    ]);
});

it('sends a compact telegram alert with a direct notification link', function () {
    config([
        'services.telegram-bot-api.token' => 'fake-token',
        'services.telegram-bot-api.chat_id' => '999999',
    ]);
    URL::forceRootUrl('https://james.test');

    $notification = new DueTodayNotification(dueAlert());
    $notification->id = 'due-alert-notification-id';

    $payload = $notification->toTelegram(new User)->toArray();
    $button = json_decode($payload['reply_markup'], true)['inline_keyboard'][0][0];

    expect($payload['text'])->toContain('*VENCIMENTOS — HOJE E AMANHÃ*')
        ->and($payload['text'])->toContain('Receitas: '.formatCurrency(2500))
        ->and($payload['text'])->toContain('Despesas: '.formatCurrency(990))
        ->and($payload['text'])->not->toContain('Pró-labore')
        ->and($button['text'])->toBe('Ver vencimentos')
        ->and($button['url'])->toBe(route('notifications.show', 'due-alert-notification-id'));
});

it('uses the dedicated markdown template for the due alert email', function () {
    $notification = new DueTodayNotification(dueAlert());
    $notification->id = 'due-alert-notification-id';
    $user = new User(['name' => 'Arthur', 'email' => 'arthur@example.com']);

    $mail = $notification->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('[James] Vencimentos próximos')
        ->and($mail->markdown)->toBe('emails.notifications.due-alert')
        ->and($mail->viewData['alert'])->toBe(dueAlert())
        ->and($html)->toContain('Vencimentos de hoje e amanhã')
        ->and($html)->toContain('Pró-labore')
        ->and($html)->toContain('Fatura Cartão Principal');
});

it('uses only configured delivery channels', function () {
    config([
        'services.notifications.mail' => true,
        'services.telegram-bot-api.token' => 'fake-token',
        'services.telegram-bot-api.chat_id' => '999999',
    ]);

    $notification = new DueTodayNotification(dueAlert(), NotificationLevel::Warning);
    $user = new User(['email' => 'arthur@example.com']);

    expect($notification->via($user))->toContain('database', 'mail', TelegramChannel::class);
});
