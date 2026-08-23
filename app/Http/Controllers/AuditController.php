<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    /**
     * Display a listing of the audit logs.
     */
    public function index(Request $request): View
    {
        $query = Activity::with('causer')->latest();

        if ($request->filled('module')) {
            $query->where('subject_type', $request->module);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('action')) {
            $query->where('description', $request->action);
        }

        if ($request->filled('user')) {
            if ($request->user === 'system') {
                $query->whereNull('causer_id');
            } else {
                $query->where('causer_id', $request->user);
            }
        }

        if ($request->filled('date_start')) {
            $query->whereDate('created_at', '>=', $request->date_start);
        }

        if ($request->filled('date_end')) {
            $query->whereDate('created_at', '<=', $request->date_end);
        }

        if ($request->filled('sort')) {
            if ($request->sort === 'oldest') {
                $query->reorder()->oldest();
            }
        }

        $activities = $query->paginate(100)->withQueryString();

        $modules = Activity::select('subject_type')->distinct()->pluck('subject_type');
        $actions = Activity::select('description')->distinct()->pluck('description');
        $users = User::whereIn('id', Activity::select('causer_id')->whereNotNull('causer_id')->distinct())->get();

        return view('audit.index', compact('activities', 'modules', 'actions', 'users'));
    }

    /**
     * Display the specified audit log.
     */
    public function show(Activity $activity): View
    {
        $activity->load('causer');

        $changes = $activity->attribute_changes ?? $activity->properties;
        $changesArray = is_object($changes) && method_exists($changes, 'toArray') ? $changes->toArray() : (array) $changes;

        $old = $changesArray['old'] ?? [];
        $attributes = $changesArray['attributes'] ?? [];

        $old = is_object($old) ? (array) $old : $old;
        $attributes = is_object($attributes) ? (array) $attributes : $attributes;

        $isDeleted = in_array($activity->description, [
            AuditAction::Deleted->value,
            AuditAction::ItemDeleted->value,
            AuditAction::ForceDeleted->value,
        ]);

        // Spatie activitylog stores deleted data in 'old' for 'deleted' events,
        // but in 'attributes' for 'forceDeleted' events. Normalize this.
        if ($isDeleted) {
            $deletedData = ! empty($old) ? $old : $attributes;
            $old = $deletedData;
            $attributes = [];
        }

        $keys = collect(array_keys($old))->merge(array_keys($attributes))->unique();
        $hasOld = count($old) > 0;

        $parsedChanges = $keys->map(function ($key) use ($old, $attributes) {
            $isDate = str_ends_with($key, '_at') || str_ends_with($key, '_date') || $key === 'date';

            $formatValue = function ($val) use ($isDate) {
                if (is_array($val) || is_object($val)) {
                    return json_encode($val);
                }

                if ($isDate && ! empty($val)) {
                    try {
                        $carbon = Carbon::parse($val)->timezone(config('app.timezone'));
                        if ($carbon->format('H:i:s') === '00:00:00') {
                            return formatShort($val);
                        }

                        return formatDateTime($val);
                    } catch (\Exception $e) {
                        return $val;
                    }
                }

                if (is_null($val)) {
                    return 'null';
                }

                if (is_bool($val)) {
                    return $val ? 'true' : 'false';
                }

                return (string) $val;
            };

            return [
                'key' => $key,
                'old' => array_key_exists($key, $old) ? $formatValue($old[$key]) : '-',
                'new' => array_key_exists($key, $attributes) ? $formatValue($attributes[$key]) : '-',
            ];
        });

        $subject = $activity->subject;
        $subjectUrl = null;

        if (($subject && ! method_exists($subject, 'trashed')) || ($subject && method_exists($subject, 'trashed') && ! $subject->trashed())) {
            try {
                $routeMap = [
                    'FinancialAccount' => 'financial.accounts.show',
                    'FinancialTransaction' => 'financial.transactions.show',
                    'FinancialCreditCard' => 'financial.cards.show',
                    'FinancialCreditCardInvoice' => 'financial.cards.invoices.show',
                    'FinancialTag' => 'financial.tags.show',
                    'SettlementGroup' => 'settlements.groups.show',
                ];

                $basename = class_basename($activity->subject_type);
                $routeName = $routeMap[$basename] ?? str($basename)->plural()->kebab().'.show';

                if ($basename === 'FinancialCreditCardInvoice') {
                    $subjectUrl = route($routeName, [$subject->financial_credit_card_id, $subject->id]);
                } else {
                    $subjectUrl = route($routeName, $subject);
                }
            } catch (\Exception $e) {
                // If route doesn't exist, ignore
            }
        }

        $isDeleted = in_array($activity->description, [
            AuditAction::Deleted->value,
            AuditAction::ItemDeleted->value,
            AuditAction::ForceDeleted->value,
        ]);

        if ($isDeleted) {
            $gridClass = 'grid-cols-[1fr_2fr]';
        } else {
            $gridClass = $hasOld ? 'grid-cols-[1fr_1.5fr_1.5fr]' : 'grid-cols-[1fr_1.5fr]';
        }

        return view('audit.show', compact('activity', 'parsedChanges', 'hasOld', 'gridClass', 'subjectUrl', 'isDeleted'));
    }
}
