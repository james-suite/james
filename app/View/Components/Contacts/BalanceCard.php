<?php

namespace App\View\Components\Contacts;

use App\Models\Contact;
use App\Services\SettlementBalanceCalculator;
use Illuminate\View\Component;
use Illuminate\View\View;

class BalanceCard extends Component
{
    public $contact;

    public $netBalance;

    /**
     * Create a new component instance.
     */
    public function __construct(SettlementBalanceCalculator $settlementBalanceCalculator, Contact $contact)
    {
        $this->contact = $contact;
        $this->netBalance = $settlementBalanceCalculator->forContact($contact)['netBalance'];
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.contacts.balance-card');
    }
}
