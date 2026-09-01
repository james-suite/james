<?php

use App\Enums\NotificationLevel;
use App\Models\User;
use App\Notifications\DueTodayNotification;
use App\Notifications\FinancialSummaryNotification;
use App\Notifications\GeneralNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires authentication to view notifications', function () {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));
});

it('can view the notifications index', function () {
    $this->user->notify(new GeneralNotification('Título', 'Mensagem de teste'));

    $this->actingAs($this->user)
        ->get(route('notifications.index'))
        ->assertSuccessful()
        ->assertViewIs('notifications.index')
        ->assertViewHas('notifications')
        ->assertViewHas('unreadCount', 1);
});

it('can filter notifications on index', function () {
    $this->user->notify(new GeneralNotification('Relatório Financeiro', 'Pronto para download'));

    $this->actingAs($this->user)
        ->get(route('notifications.index', ['status' => 'unread', 'search' => 'Financeiro']))
        ->assertSuccessful()
        ->assertViewIs('notifications.index');
});

it('can view a notification and marks it as read automatically', function () {
    $this->user->notify(new GeneralNotification('Título', 'Mensagem detalhada'));

    $notification = $this->user->notifications()->first();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($this->user)
        ->get(route('notifications.show', $notification))
        ->assertSuccessful()
        ->assertViewIs('notifications.show')
        ->assertViewHas('notification');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('renders a structured financial summary notification', function () {
    $this->user->notifyNow(new FinancialSummaryNotification([
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
        ],
    ], NotificationLevel::Success));

    $notification = $this->user->notifications()->sole();

    $this->actingAs($this->user)
        ->get(route('notifications.show', $notification))
        ->assertSuccessful();
});

it('renders a structured due alert notification', function () {
    $this->user->notifyNow(new DueTodayNotification([
        'alert_date' => '2026-09-01',
        'total_items' => 1,
        'income' => 0.0,
        'expense' => 120.0,
        'net' => -120.0,
        'days' => [[
            'key' => 'today',
            'label' => 'Hoje',
            'date' => '01/09/2026',
            'incomes' => [],
            'expenses' => [[
                'description' => 'Internet',
                'amount' => 120.0,
                'destination' => 'Conta Principal',
                'is_recurrence' => true,
                'is_invoice' => false,
                'transactions_count' => 0,
                'recurrences_count' => 0,
            ]],
            'invoices' => [],
        ]],
    ]));

    $notification = $this->user->notifications()->sole();

    $this->actingAs($this->user)
        ->get(route('notifications.show', $notification))
        ->assertSuccessful();
});

it('returns 403 when trying to view another users notification', function () {
    $otherUser = User::factory()->create();
    $otherUser->notify(new GeneralNotification('Alerta', 'Privado'));

    $notification = $otherUser->notifications()->first();

    $this->actingAs($this->user)
        ->get(route('notifications.show', $notification))
        ->assertForbidden();
});

it('can mark a single notification as read', function () {
    $this->user->notify(new GeneralNotification('Título', 'Mensagem'));

    $notification = $this->user->notifications()->first();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($this->user)
        ->patch(route('notifications.markAsRead', $notification))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('redirects to action_url after marking as read when redirect_action is true', function () {
    $this->user->notify(new GeneralNotification('Título', 'Mensagem', 'https://example.com'));

    $notification = $this->user->notifications()->first();

    $this->actingAs($this->user)
        ->patch(route('notifications.markAsRead', [$notification, 'redirect_action' => 1]))
        ->assertRedirect('https://example.com');
});

it('returns 403 when trying to mark another users notification as read', function () {
    $otherUser = User::factory()->create();
    $otherUser->notify(new GeneralNotification('Titulo', 'Mensagem'));

    $notification = $otherUser->notifications()->first();

    $this->actingAs($this->user)
        ->patch(route('notifications.markAsRead', $notification))
        ->assertForbidden();
});

it('can mark all notifications as read', function () {
    $this->user->notify(new GeneralNotification('Título 1', 'Mensagem 1'));
    $this->user->notify(new GeneralNotification('Título 2', 'Mensagem 2'));

    expect($this->user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($this->user)
        ->post(route('notifications.markAllAsRead'))
        ->assertRedirect();

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('can delete a notification', function () {
    $this->user->notify(new GeneralNotification('Excluir', 'Esta notificação'));

    $notification = $this->user->notifications()->first();

    $this->actingAs($this->user)
        ->delete(route('notifications.destroy', $notification))
        ->assertRedirect(route('notifications.index'));

    expect($this->user->notifications()->count())->toBe(0);
});

it('returns 403 when trying to delete another users notification', function () {
    $otherUser = User::factory()->create();
    $otherUser->notify(new GeneralNotification('Alerta', 'Privado'));

    $notification = $otherUser->notifications()->first();

    $this->actingAs($this->user)
        ->delete(route('notifications.destroy', $notification))
        ->assertForbidden();
});
