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

        $settlementsByDate = collect($settlements)
            ->groupBy(fn (Settlement $settlement): string => $settlement->date->toDateString())
            ->sortKeys();

        foreach ($settlementsByDate as $dailySettlements) {
            $toReceiveChangeInCents = 0;
            $toPayChangeInCents = 0;

            foreach ($dailySettlements as $settlement) {
                $amountInCents = (int) round($settlement->amount * 100);

                match ($settlement->type) {
                    SettlementType::TheyOwe => $toReceiveChangeInCents += $amountInCents,
                    SettlementType::TheyPaid => $toReceiveChangeInCents -= $amountInCents,
                    SettlementType::IOwe => $toPayChangeInCents += $amountInCents,
                    SettlementType::IPaid => $toPayChangeInCents -= $amountInCents,
                };
            }

            $toReceiveInCents = max(0, $toReceiveInCents + $toReceiveChangeInCents);
            $toPayInCents = max(0, $toPayInCents + $toPayChangeInCents);
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
