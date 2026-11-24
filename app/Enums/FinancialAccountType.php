<?php

namespace App\Enums;

enum FinancialAccountType: string
{
    case Checking = 'checking';
    case Investment = 'investment';
    case Wallet = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Conta Corrente',
            self::Investment => 'Investimentos',
            self::Wallet => 'Dinheiro físico',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Checking => 'heroicon-o-building-library',
            self::Investment => 'heroicon-o-chart-bar',
            self::Wallet => 'heroicon-o-wallet',
        };
    }
}
