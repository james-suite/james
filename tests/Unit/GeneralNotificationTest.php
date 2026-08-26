<?php

use App\Enums\NotificationLevel;
use App\Models\User;
use App\Notifications\GeneralNotification;
use NotificationChannels\Telegram\TelegramChannel;

it('sends only to the database channel when telegram is not configured', function () {
    config(['services.telegram-bot-api.token' => null]);
    config(['services.telegram-bot-api.chat_id' => null]);

    $notification = new GeneralNotification('Título', 'Mensagem');
    $user = new User;

    expect($notification->via($user))->toBe(['database']);
});

it('includes telegram channel when both token and chat_id are configured', function () {
    config(['services.telegram-bot-api.token' => 'fake-token']);
    config(['services.telegram-bot-api.chat_id' => '123456']);

    $notification = new GeneralNotification('Título', 'Mensagem');
    $user = new User;

    expect($notification->via($user))->toContain('database', TelegramChannel::class);
});

it('does not include telegram when only token is set', function () {
    config(['services.telegram-bot-api.token' => 'fake-token']);
    config(['services.telegram-bot-api.chat_id' => null]);

    $notification = new GeneralNotification('Título', 'Mensagem');
    $user = new User;

    expect($notification->via($user))->toBe(['database']);
});

it('does not include mail channel when notifications.mail is disabled', function () {
    config(['services.notifications.mail' => false]);

    $notification = new GeneralNotification(
        title: 'Título',
        message: 'Mensagem',
        channels: ['database', 'mail']
    );
    $user = new User(['email' => 'teste@exemplo.com']);

    expect($notification->via($user))->toBe(['database']);
});

it('includes mail channel when notifications.mail is enabled and user has email', function () {
    config(['services.notifications.mail' => true]);

    $notification = new GeneralNotification(
        title: 'Título',
        message: 'Mensagem',
        channels: ['database', 'mail']
    );
    $user = new User(['email' => 'teste@exemplo.com']);

    expect($notification->via($user))->toContain('database', 'mail');
});

it('returns correct toDatabase payload with level and details', function () {
    $notification = new GeneralNotification(
        title: 'Meu Título',
        message: 'Minha Mensagem',
        actionUrl: 'https://example.com',
        level: 'warning',
        details: ['Valor' => 'R$ 100,00']
    );
    $user = new User;

    expect($notification->toDatabase($user))->toBe([
        'title' => 'Meu Título',
        'message' => 'Minha Mensagem',
        'action_url' => 'https://example.com',
        'action_label' => 'Acessar no Sistema',
        'level' => 'warning',
        'details' => ['Valor' => 'R$ 100,00'],
        'items' => [],
    ]);
});

it('formats detailed items for database, telegram and mail', function () {
    config(['services.telegram-bot-api.chat_id' => '999999']);

    $items = [
        [
            'description' => 'Café especial',
            'quantity' => '2',
            'unit_price' => 'R$ 18,50',
            'total' => 'R$ 37,00',
        ],
        [
            'description' => 'Desconto da NFC-e',
            'quantity' => '1',
            'unit_price' => '-R$ 5,00',
            'total' => '-R$ 5,00',
        ],
    ];
    $notification = new GeneralNotification(
        title: 'NFC-e importada com sucesso',
        message: 'A transação está pronta para revisão.',
        items: $items,
    );
    $user = new User(['name' => 'Arthur']);

    expect($notification->toDatabase($user)['items'])->toBe($items);

    $telegram = $notification->toTelegram($user)->toArray();
    expect($telegram['text'])->toContain('*Itens:*')
        ->and($telegram['text'])->toContain('• *Café especial*')
        ->and($telegram['text'])->toContain('Qtd.: 2 | Unitário: R$ 18,50 | Total: R$ 37,00')
        ->and($telegram['text'])->toContain('Qtd.: 1 | Unitário: -R$ 5,00 | Total: -R$ 5,00')
        ->and($telegram['text'])->not->toContain('{"description"');

    $mail = $notification->toMail($user);
    expect($mail->introLines)->toContain('**Itens:**')
        ->and($mail->introLines)->toContain('**Café especial**')
        ->and($mail->introLines)->toContain('Qtd.: 2 | Unitário: R$ 18,50 | Total: R$ 37,00')
        ->and($mail->introLines)->toContain('Qtd.: 1 | Unitário: -R$ 5,00 | Total: -R$ 5,00')
        ->and($mail->introLines)->not->toContain('{"description"');
});

it('formats telegram message with clean bold title, prefix and details', function () {
    config(['services.telegram-bot-api.chat_id' => '999999']);

    $notification = new GeneralNotification(
        title: 'Fatura Fechada',
        message: 'Sua fatura fechou.',
        actionUrl: 'https://example.com/fatura',
        level: NotificationLevel::Warning,
        details: ['Total' => 'R$ 500,00', 'Vencimento' => '25/08'],
        actionLabel: 'Revisar fatura',
    );
    $user = new User;

    $telegram = $notification->toTelegram($user);
    $payload = $telegram->toArray();

    expect($payload['chat_id'])->toBe('999999');
    expect($payload['text'])->toContain('*ALERTA: Fatura Fechada*');
    expect($payload['text'])->toContain('• *Total:* R$ 500,00');
    expect($payload['text'])->toContain('• *Vencimento:* 25/08');
    expect(json_decode($payload['reply_markup'], true)['inline_keyboard'][0][0]['text'])->toBe('Revisar fatura');
});

it('formats mail message with subject, greeting, details and action', function () {
    $notification = new GeneralNotification(
        title: 'Transação Aprovada',
        message: 'Pagamento confirmado com sucesso.',
        actionUrl: 'https://example.com/tx/1',
        level: 'success',
        details: ['Valor' => 'R$ 250,00'],
        actionLabel: 'Revisar transação',
    );
    $user = new User(['name' => 'Arthur', 'email' => 'arthur@example.com']);

    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('[James] Transação Aprovada');
    expect($mail->greeting)->toContain('Arthur');
    expect($mail->introLines)->toContain('Pagamento confirmado com sucesso.');
    expect($mail->introLines)->toContain('**Valor:** R$ 250,00');
    expect($mail->actionText)->toBe('Revisar transação');
    expect($mail->actionUrl)->toBe('https://example.com/tx/1');
});
