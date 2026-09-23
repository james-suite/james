<?php

namespace App\View\Components\Finance;

use App\Models\FinancialAccount;
use App\Models\FinancialTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use Illuminate\View\View;

class RecentTransactions extends Component
{
    public $model;

    public $recentTransactions;

    public $viewAllUrl;

    /**
     * Create a new component instance.
     */
    public function __construct(Model $model, ?string $viewAllUrl = null, int $limit = 10)
    {
        $this->model = $model;

        $this->recentTransactions = $model->transactions()
            ->with(['invoice.creditCard', 'account', 'media', 'tags'])
            ->latest('date')
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        if ($viewAllUrl) {
            $this->viewAllUrl = $viewAllUrl;
        } else {
            if ($model instanceof FinancialAccount) {
                $this->viewAllUrl = route('financial.transactions.index', ['account_id' => $model->id]);
            } elseif ($model instanceof FinancialTag) {
                $this->viewAllUrl = route('financial.transactions.index', ['tag_id' => $model->id]);
            } else {
                $this->viewAllUrl = route('financial.transactions.index');
            }
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.finance.recent-transactions');
    }
}
