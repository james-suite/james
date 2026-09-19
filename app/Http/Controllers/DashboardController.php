<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\FinanceDashboardService;
use App\Services\SettlementBalanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private FinanceDashboardService $financeDashboardService,
        private SettlementBalanceCalculator $settlementBalanceCalculator,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();
        $financialSummary = $this->financeDashboardService->getKpiNumbers();
        $settlements = $this->getSettlementSummary();

        return view('dashboard', [
            'today' => $today,
            'financialSummary' => $financialSummary,
            'settlementSummary' => $settlements['summary'],
            'settlements' => $settlements['items'],
            'contactCount' => Contact::count(),
            'unreadNotificationCount' => $user->unreadNotifications()->count(),
            'recentNotifications' => $user->unreadNotifications()->latest()->limit(3)->get(),
        ]);
    }

    /**
     * @return array{summary: array<string, float|int>, items: Collection}
     */
    private function getSettlementSummary(): array
    {
        $contacts = Contact::query()
            ->select(['id', 'name'])
            ->with([
                'media',
                'settlements' => fn ($query) => $query
                    ->select(['id', 'contact_id', 'type', 'amount', 'date'])
                    ->orderBy('date')
                    ->orderBy('id'),
            ])
            ->notSettlementArchived()
            ->whereHas('settlements')
            ->get()
            ->map(function (Contact $contact): Contact {
                $balance = $this->settlementBalanceCalculator->calculate($contact->settlements);

                $contact->to_receive = $balance['toReceive'];
                $contact->to_pay = $balance['toPay'];
                $contact->net_balance = $balance['netBalance'];

                return $contact;
            });

        $pendingContacts = $contacts
            ->filter(fn (Contact $contact): bool => $contact->net_balance !== 0.0)
            ->sortByDesc(fn (Contact $contact): float => abs($contact->net_balance));
        $toReceive = round((float) $contacts->sum(fn (Contact $contact): float => max(0, $contact->net_balance)), 2);
        $toPay = round((float) $contacts->sum(fn (Contact $contact): float => max(0, -$contact->net_balance)), 2);

        return [
            'summary' => [
                'toReceive' => $toReceive,
                'toPay' => $toPay,
                'netBalance' => round($toReceive - $toPay, 2),
                'pendingCount' => $pendingContacts->count(),
            ],
            'items' => $pendingContacts->take(4)->values(),
        ];
    }
}
