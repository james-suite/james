<?php

namespace App\Services;

use App\Enums\SettlementType;
use App\Models\Contact;
use App\Models\Settlement;

class SettlementBalanceCalculator
{
    /**
     * @param  iterable<int, Settlement>  $settlements
     * @return array{toReceive: float, toPay: float, netBalance: float}
     */
    public function calculate(iterable $settlements): array
    {
        $toReceiveInCents = 0;
        $toPayInCents = 0;

        $orderedSettlements = collect($settlements)->sort(function (Settlement $left, Settlement $right): int {
            $dateComparison = $left->date->toDateString() <=> $right->date->toDateString();

            return $dateComparison !== 0
                ? $dateComparison
                : $left->getKey() <=> $right->getKey();
        });

        foreach ($orderedSettlements as $settlement) {
            $amountInCents = (int) round($settlement->amount * 100);

            match ($settlement->type) {
                SettlementType::TheyOwe => $toReceiveInCents += $amountInCents,
                SettlementType::TheyPaid => $toReceiveInCents = max(0, $toReceiveInCents - $amountInCents),
                SettlementType::IOwe => $toPayInCents += $amountInCents,
                SettlementType::IPaid => $toPayInCents = max(0, $toPayInCents - $amountInCents),
            };
        }

        return [
            'toReceive' => (float) ($toReceiveInCents / 100),
            'toPay' => (float) ($toPayInCents / 100),
            'netBalance' => (float) (($toReceiveInCents - $toPayInCents) / 100),
        ];
    }

    /**
     * @return array{toReceive: float, toPay: float, netBalance: float}
     */
    public function forContact(Contact $contact): array
    {
        return $this->calculate(
            $contact->settlements()
                ->select(['id', 'contact_id', 'type', 'amount', 'date'])
                ->orderBy('date')
                ->orderBy('id')
                ->get()
        );
    }
}
