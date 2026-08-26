<?php

namespace App\Notifications;

use App\Enums\NotificationLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class GeneralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly NotificationLevel $level;

    /**
     * @param  array<string, mixed>  $details  Dados adicionais chave-valor (ex: ['Valor' => 'R$ 150,00', 'Vencimento' => '20/08/2026'])
     * @param  array<int, string>  $channels  Canais de entrega desejados ('database', 'telegram', 'mail')
     * @param  list<array{description: string, quantity: string, unit_price: string, total: string}>  $items  Itens detalhados relacionados à notificação
     */
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $actionUrl = null,
        NotificationLevel|string $level = NotificationLevel::Info,
        public readonly array $details = [],
        public readonly array $channels = ['database', 'telegram', 'mail'],
        public readonly string $actionLabel = 'Acessar no Sistema',
        public readonly array $items = [],
    ) {
        $this->level = is_string($level)
            ? (NotificationLevel::tryFrom($level) ?? NotificationLevel::Info)
            : $level;
    }

    /**
     * Canais de entrega ativos da notificação.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $activeChannels = [];

        if (in_array('database', $this->channels, true)) {
            $activeChannels[] = 'database';
        }

        if (in_array('mail', $this->channels, true) && ! empty($notifiable->email)) {
            $isMailEnabled = (bool) config('services.notifications.mail', false);

            if ($isMailEnabled) {
                $activeChannels[] = 'mail';
            }
        }

        if (in_array('telegram', $this->channels, true)) {
            $hasTelegramConfig = filled(config('services.telegram-bot-api.token'))
                && filled(config('services.telegram-bot-api.chat_id'));

            if ($hasTelegramConfig) {
                $activeChannels[] = TelegramChannel::class;
            }
        }

        return $activeChannels;
    }

    /**
     * Payload persistido na tabela notifications.
     *
     * @return array{title: string, message: string, action_url: string|null, action_label: string, level: string, details: array<string, mixed>, items: list<array{description: string, quantity: string, unit_price: string, total: string}>}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'level' => $this->level->value,
            'details' => $this->details,
            'items' => $this->items,
        ];
    }

    /**
     * Mensagem formatada e estilizada para o Bot do Telegram.
     */
    public function toTelegram(object $notifiable): TelegramMessage
    {
        $prefix = strtoupper($this->level->label());
        $lines = ["*{$prefix}: {$this->title}*", '', $this->message];

        if (! empty($this->details)) {
            $lines[] = '';
            $lines[] = '*Informações:*';
            foreach ($this->details as $key => $value) {
                $formattedValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
                $lines[] = "• *{$key}:* {$formattedValue}";
            }
        }

        if (! empty($this->items)) {
            $lines[] = '';
            $lines[] = '*Itens:*';
            foreach ($this->items as $item) {
                $lines[] = "• *{$item['description']}*";
                $lines[] = "  Qtd.: {$item['quantity']} | Unitário: {$item['unit_price']} | Total: {$item['total']}";
            }
        }

        $telegram = TelegramMessage::create()
            ->to(config('services.telegram-bot-api.chat_id'));

        if ($this->actionUrl) {
            $isLocalUrl = str_contains($this->actionUrl, 'localhost') || str_contains($this->actionUrl, '127.0.0.1');

            if ($isLocalUrl) {
                $lines[] = '';
                $lines[] = "Link: {$this->actionUrl}";
            } else {
                $telegram->button($this->actionLabel, $this->actionUrl);
            }
        }

        $content = implode("\n", $lines);
        $telegram->content($content);

        return $telegram;
    }

    /**
     * E-mail transacional formatado nativamente pelo Laravel Mail.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[James] {$this->title}")
            ->greeting('Olá, '.($notifiable->name ?? 'usuário').'!');

        if ($this->level === NotificationLevel::Danger) {
            $mail->error();
        }

        $mail->line($this->message);

        if (! empty($this->details)) {
            $mail->line('---');
            foreach ($this->details as $key => $value) {
                $formattedValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
                $mail->line("**{$key}:** {$formattedValue}");
            }
            $mail->line('---');
        }

        if (! empty($this->items)) {
            $mail->line('---');
            $mail->line('**Itens:**');
            foreach ($this->items as $item) {
                $mail->line("**{$item['description']}**");
                $mail->line("Qtd.: {$item['quantity']} | Unitário: {$item['unit_price']} | Total: {$item['total']}");
            }
            $mail->line('---');
        }

        if ($this->actionUrl) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        $mail->salutation('Atenciosamente, James');

        return $mail;
    }
}
