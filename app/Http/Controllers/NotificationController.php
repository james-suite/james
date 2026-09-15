<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Exibe todas as notificações do usuário autenticado com filtros.
     */
    public function index(Request $request): View
    {
        $query = $request->user()->notifications()->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('data', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->status === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        if ($request->filled('date_start')) {
            $query->whereDate('created_at', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $query->whereDate('created_at', '<=', $request->date_end);
        }

        if ($request->filled('sort') && $request->sort === 'oldest') {
            $query->reorder()->oldest();
        }

        $notifications = $query->paginate(20)->withQueryString();
        $unreadCount = $request->user()->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Exibe os detalhes de uma notificação e a marca como lida.
     */
    public function show(Request $request, DatabaseNotification $notification): View
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 403);

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return view('notifications.show', compact('notification'));
    }

    /**
     * Marca uma notificação específica como lida.
     */
    public function markAsRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 403);

        $notification->markAsRead();

        $actionUrl = $notification->data['action_url'] ?? null;

        if ($request->boolean('redirect_action') && $actionUrl) {
            return redirect($actionUrl);
        }

        return back()->with('success', 'Notificação marcada como lida.');
    }

    /**
     * Marca todas as notificações do usuário como lidas.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    /**
     * Exclui uma notificação.
     */
    public function destroy(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 403);

        $notification->delete();

        return redirect()->route('notifications.index')->with('success', 'Notificação removida com sucesso.');
    }
}
