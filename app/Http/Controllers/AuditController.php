<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\FinancialAccount;
use App\Models\FinancialCreditCard;
use App\Models\FinancialRecurrence;
use App\Models\FinancialTag;
use App\Models\FinancialTransaction;
use App\Models\Settlement;
use App\Models\SettlementGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    /**
     * Allowlist of simple, safe fields eligible for selective revert per model.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const REVERTIBLE_FIELDS = [
        Contact::class => ['name', 'relationship_category', 'birthdate', 'notes', 'emails', 'phones'],
        ContactGroup::class => ['name', 'description'],
        FinancialAccount::class => ['name', 'type', 'pix_keys', 'archived_at'],
        FinancialCreditCard::class => ['name', 'credit_limit', 'archived_at'],
        FinancialTag::class => ['name', 'icon', 'color_hex'],
        FinancialTransaction::class => ['description'],
        FinancialRecurrence::class => ['title', 'is_active', 'end_date'],
        Settlement::class => ['description'],
        SettlementGroup::class => ['description'],
    ];

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

        $changesArray = $this->extractChangesArray($activity);

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
        $subject = $activity->subject;
        $canRevertSubject = $this->canRevertSubject($activity, $subject);
        $allowedFields = self::REVERTIBLE_FIELDS[$activity->subject_type] ?? [];

        $parsedChanges = $keys->map(function ($key) use ($old, $attributes, $subject, $canRevertSubject, $allowedFields) {
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

            $hasOldValue = array_key_exists($key, $old);
            $isAllowedField = $canRevertSubject && $hasOldValue && in_array($key, $allowedFields, true);
            $currentRaw = $isAllowedField && $subject ? $subject->getAttribute($key) : null;
            $alreadyReverted = $isAllowedField && $this->valuesMatch($currentRaw, $old[$key]);
            $hasConflict = $isAllowedField
                && array_key_exists($key, $attributes)
                && ! $this->valuesMatch($currentRaw, $attributes[$key]);

            return [
                'key' => $key,
                'old' => $hasOldValue ? $formatValue($old[$key]) : '-',
                'new' => array_key_exists($key, $attributes) ? $formatValue($attributes[$key]) : '-',
                'current' => $isAllowedField ? $formatValue($currentRaw) : null,
                'revertible' => $isAllowedField && ! $alreadyReverted,
                'already_reverted' => $alreadyReverted,
                'has_conflict' => $hasConflict,
            ];
        });

        $hasRevertibleFields = $this->isUpdatedEvent($activity) && $hasOld;
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

        if ($isDeleted) {
            $gridClass = 'grid-cols-[1fr_2fr]';
        } elseif ($hasRevertibleFields) {
            $gridClass = $hasOld ? 'grid-cols-[1fr_1.4fr_1.4fr_110px]' : 'grid-cols-[1fr_1.5fr_110px]';
        } else {
            $gridClass = $hasOld ? 'grid-cols-[1fr_1.5fr_1.5fr]' : 'grid-cols-[1fr_1.5fr]';
        }

        return view('audit.show', compact('activity', 'parsedChanges', 'hasOld', 'gridClass', 'subjectUrl', 'isDeleted', 'hasRevertibleFields'));
    }

    /**
     * Selectively revert one or more allowlisted fields from an updated audit event.
     */
    public function revert(Request $request, Activity $activity): RedirectResponse
    {
        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string'],
        ]);

        $subject = $activity->subject;

        if (! $this->canRevertSubject($activity, $subject)) {
            return redirect()
                ->route('audit.show', $activity)
                ->with('error', 'Este registro não é elegível para reversão seletiva.');
        }

        $changesArray = $this->extractChangesArray($activity);
        $old = $changesArray['old'] ?? [];
        $old = is_object($old) ? (array) $old : $old;

        $allowedFields = self::REVERTIBLE_FIELDS[$activity->subject_type] ?? [];
        $requestedFields = array_values(array_unique($validated['fields']));

        foreach ($requestedFields as $field) {
            if (! in_array($field, $allowedFields, true) || ! array_key_exists($field, $old)) {
                return redirect()
                    ->route('audit.show', $activity)
                    ->with('error', "O campo '{$field}' não pode ser revertido por esta ação.");
            }
        }

        $revertedFields = [];

        DB::transaction(function () use ($subject, $requestedFields, $old, &$revertedFields): void {
            foreach ($requestedFields as $field) {
                if (! $this->valuesMatch($subject->getAttribute($field), $old[$field])) {
                    $subject->setAttribute($field, $old[$field]);
                    $revertedFields[] = $field;
                }
            }

            if ($revertedFields !== []) {
                $subject->save();
            }
        });

        if ($revertedFields === []) {
            return redirect()
                ->route('audit.show', $activity)
                ->with('info', 'O campo selecionado já está com o valor anterior.');
        }

        return redirect()
            ->route('audit.show', $activity)
            ->with('success', 'Campo revertido com sucesso: '.implode(', ', $revertedFields).'.');
    }

    private function canRevertSubject(Activity $activity, ?Model $subject): bool
    {
        if (! $this->isUpdatedEvent($activity) || ! $subject) {
            return false;
        }

        if (method_exists($subject, 'trashed') && $subject->trashed()) {
            return false;
        }

        if ($subject instanceof FinancialTag && $subject->is_protected) {
            return false;
        }

        return array_key_exists($activity->subject_type, self::REVERTIBLE_FIELDS);
    }

    private function isUpdatedEvent(Activity $activity): bool
    {
        $event = $activity->event instanceof \BackedEnum ? $activity->event->value : $activity->event;

        return $activity->description === AuditAction::Updated->value
            || $event === AuditAction::Updated->value;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractChangesArray(Activity $activity): array
    {
        $attributeChanges = $activity->attribute_changes;
        $changes = is_object($attributeChanges) && method_exists($attributeChanges, 'toArray')
            ? $attributeChanges->toArray()
            : (array) $attributeChanges;

        if ($changes !== []) {
            return $changes;
        }

        $properties = $activity->properties;

        return is_object($properties) && method_exists($properties, 'toArray')
            ? $properties->toArray()
            : (array) $properties;
    }

    private function valuesMatch(mixed $left, mixed $right): bool
    {
        if ($left instanceof \BackedEnum) {
            $left = $left->value;
        }

        if ($right instanceof \BackedEnum) {
            $right = $right->value;
        }

        if ($left instanceof \DateTimeInterface) {
            $left = $left->format('Y-m-d');
        }

        if (is_array($left) || is_array($right)) {
            return json_encode($left) === json_encode($right);
        }

        if ($left === null || $right === null) {
            return $left === $right;
        }

        return (string) $left === (string) $right;
    }
}
