<?php

use App\Enums\SettlementType;
use App\Models\Settlement;
use App\Services\SettlementBalanceCalculator;

function settlementForBalance(int $id, SettlementType $type, float $amount, string $date): Settlement
{
    $settlement = new Settlement([
        'type' => $type,
        'amount' => $amount,
        'description' => 'Teste',
        'date' => $date,
    ]);
    $settlement->id = $id;

    return $settlement;
}

it('does not carry an excess received payment into a future debt', function () {
    $balance = (new SettlementBalanceCalculator)->calculate([
        settlementForBalance(1, SettlementType::TheyOwe, 70, '2026-09-01'),
        settlementForBalance(2, SettlementType::TheyPaid, 80, '2026-09-02'),
        settlementForBalance(3, SettlementType::TheyOwe, 20, '2026-09-03'),
    ]);

    expect($balance)->toBe([
        'toReceive' => 20.0,
        'toPay' => 0.0,
        'netBalance' => 20.0,
    ]);
});

it('does not carry an excess sent payment into a future debt', function () {
    $balance = (new SettlementBalanceCalculator)->calculate([
        settlementForBalance(1, SettlementType::IOwe, 70, '2026-09-01'),
        settlementForBalance(2, SettlementType::IPaid, 80, '2026-09-02'),
        settlementForBalance(3, SettlementType::IOwe, 20, '2026-09-03'),
    ]);

    expect($balance)->toBe([
        'toReceive' => 0.0,
        'toPay' => 20.0,
        'netBalance' => -20.0,
    ]);
});

it('uses the record id to order movements from the same date', function () {
    $balance = (new SettlementBalanceCalculator)->calculate([
        settlementForBalance(2, SettlementType::TheyOwe, 20, '2026-09-01'),
        settlementForBalance(1, SettlementType::TheyPaid, 80, '2026-09-01'),
    ]);

    expect($balance['toReceive'])->toBe(20.0);
});
