<?php

namespace App\Http\Controllers;

use App\Enums\SettlementType;
use App\Models\Contact;
use App\Services\FinanceDashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private FinanceDashboardService $financeDashboardService) {}

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
            ->with('media')
            ->notSettlementArchived()
            ->whereHas('settlements')
            ->withSum(['settlements as they_owe' => fn (Builder $query) => $query->where('type', SettlementType::TheyOwe->value)], 'amount')
            ->withSum(['settlements as they_paid' => fn (Builder $query) => $query->where('type', SettlementType::TheyPaid->value)], 'amount')
            ->withSum(['settlements as i_owe' => fn (Builder $query) => $query->where('type', SettlementType::IOwe->value)], 'amount')
            ->withSum(['settlements as i_paid' => fn (Builder $query) => $query->where('type', SettlementType::IPaid->value)], 'amount')
            ->get()
            ->map(function (Contact $contact): Contact {
                $contact->to_receive = max(0, round((float) $contact->they_owe - (float) $contact->they_paid, 2));
                $contact->to_pay = max(0, round((float) $contact->i_owe - (float) $contact->i_paid, 2));
                $contact->net_balance = round($contact->to_receive - $contact->to_pay, 2);

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
