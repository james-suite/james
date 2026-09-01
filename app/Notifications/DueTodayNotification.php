<?php

namespace App\Notifications;

use App\Enums\NotificationLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class DueTodayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{alert_date: string, total_items: int, income: float, expense: float, net: float, days: list<array{key: string, label: string, date: string, incomes: list<array<string, mixed>>, expenses: list<array<string, mixed>>, invoices: list<array<string, mixed>>}>  $alert
     */
    public function __construct(
        public readonly array $alert,
        public readonly NotificationLevel $level = NotificationLevel::Warning,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $activeChannels = ['database'];

        if (filled($notifiable->email) && config('services.notifications.mail', false)) {
            $activeChannels[] = 'mail';
        }

        if (filled(config('services.telegram-bot-api.token')) && filled(config('services.telegram-bot-api.chat_id'))) {
            $activeChannels[] = TelegramChannel::class;
        }

        return $activeChannels;
    }

    /**
     * @return array{title: string, message: string, action_url: string, action_label: string, level: string, presentation: string, due_alert: array{alert_date: string, total_items: int, income: float, expense: float, net: float, days: list<array{key: string, label: string, date: string, incomes: list<array<string, mixed>>, expenses: list<array<string, mixed>>, invoices: list<array<string, mixed>>}>}, details: array{}, items: array{}}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Vencimentos próximos',
            'message' => "{$this->alert['total_items']} ".($this->alert['total_items'] === 1 ? 'item previsto para hoje e amanhã.' : 'itens previstos para hoje e amanhã.'),
            'action_url' => route('financial.dashboard'),
            'action_label' => 'Ver vencimentos',
            'level' => $this->level->value,
            'presentation' => 'due-alert',
            'due_alert' => $this->alert,
            'details' => [],
            'items' => [],
        ];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $lines = [
            '*VENCIMENTOS — HOJE E AMANHÃ*',
            '',
            "Receitas: {$this->formatCurrency($this->alert['income'])}",
            "Despesas: {$this->formatCurrency($this->alert['expense'])}",
            "Impacto líquido: {$this->formatSignedCurrency($this->alert['net'])}",
            "Itens: {$this->alert['total_items']}",
        ];

        $telegram = TelegramMessage::create()
            ->to(config('services.telegram-bot-api.chat_id'));

        $detailUrl = $this->detailUrl();

        if ($detailUrl !== null) {
            if (str_contains($detailUrl, 'localhost') || str_contains($detailUrl, '127.0.0.1')) {
                $lines[] = '';
                $lines[] = "Detalhes: {$detailUrl}";
            } else {
                $telegram->button('Ver vencimentos', $detailUrl);
            }
        }

        return $telegram->content(implode("\n", $lines));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[James] Vencimentos próximos')
            ->markdown('emails.notifications.due-alert', [
                'name' => $notifiable->name ?? 'usuário',
                'alert' => $this->alert,
                'detailUrl' => $this->detailUrl() ?? route('financial.dashboard'),
            ]);
    }

    private function detailUrl(): ?string
    {
        if (! $this->id) {
            return null;
        }

        return route('notifications.show', ['notification' => $this->id]);
    }

    private function formatCurrency(float $amount): string
    {
        return formatCurrency($amount);
    }

    private function formatSignedCurrency(float $amount): string
    {
        if (abs($amount) < 0.005) {
            return formatCurrency(0);
        }

        return ($amount > 0 ? '+ ' : '- ').formatCurrency(abs($amount));
    }
}
