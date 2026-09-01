<?php

namespace App\Notifications;

use App\Enums\NotificationLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class FinancialSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{period: string, previous_period: string, income: float, income_variation: float, expense: float, expense_variation: float, net: float, net_variation: float, account_balance: float, pending_commitments: float, net_balance: float, income_categories: list<array{id: int, name: string, icon: string, color: string, amount: float, percentage: float}>, expense_categories: list<array{id: int, name: string, icon: string, color: string, amount: float, percentage: float}>}  $summary
     */
    public function __construct(
        public readonly array $summary,
        public readonly NotificationLevel $level,
    ) {}

    /**
     * @return array<int, class-string|string>
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
     * @return array{title: string, message: string, action_url: string, action_label: string, level: string, presentation: string, financial_summary: array{period: string, previous_period: string, income: float, income_variation: float, expense: float, expense_variation: float, net: float, net_variation: float, account_balance: float, pending_commitments: float, net_balance: float, income_categories: list<array{id: int, name: string, icon: string, color: string, amount: float, percentage: float}>, expense_categories: list<array{id: int, name: string, icon: string, color: string, amount: float, percentage: float}>}, details: array{}, items: array{}}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => "Resumo Financeiro — {$this->summary['period']}",
            'message' => "Confira o resultado de {$this->summary['period']}, a comparação com {$this->summary['previous_period']} e sua posição financeira atual.",
            'action_url' => route('financial.dashboard'),
            'action_label' => 'Abrir painel financeiro',
            'level' => $this->level->value,
            'presentation' => 'financial-summary',
            'financial_summary' => $this->summary,
            'details' => [],
            'items' => [],
        ];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $lines = [
            "*RESUMO FINANCEIRO — {$this->summary['period']}*",
            '',
            "Receitas: {$this->formatCurrency($this->summary['income'])}",
            "Despesas: {$this->formatCurrency($this->summary['expense'])}",
            "Resultado: {$this->formatSignedCurrency($this->summary['net'])}",
            "Saldo líquido: {$this->formatSignedCurrency($this->summary['net_balance'])}",
        ];

        $telegram = TelegramMessage::create()
            ->to(config('services.telegram-bot-api.chat_id'));

        $detailUrl = $this->detailUrl();

        if ($detailUrl !== null) {
            if (str_contains($detailUrl, 'localhost') || str_contains($detailUrl, '127.0.0.1')) {
                $lines[] = '';
                $lines[] = "Detalhes: {$detailUrl}";
            } else {
                $telegram->button('Ver detalhes', $detailUrl);
            }
        }

        return $telegram->content(implode("\n", $lines));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[James] Resumo Financeiro — {$this->summary['period']}")
            ->markdown('emails.notifications.financial-summary', [
                'name' => $notifiable->name ?? 'usuário',
                'summary' => $this->summary,
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
