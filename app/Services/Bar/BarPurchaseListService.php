<?php

namespace App\Services\Bar;

use App\Mail\BarPurchaseListMail;
use App\Models\Hotel;
use App\Models\HotelBarPurchaseList;
use App\Models\HotelDepartment;
use App\Models\HotelPmsService;
use App\Models\HotelPmsServiceItem;
use App\Models\HotelStoreMasterItem;
use App\Models\HotelStorePackage;
use App\Models\HotelVendor;
use App\Models\User;
use App\Services\AccountsDataService;
use App\Services\HotelSettingsService;
use App\Support\DepartmentProcurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class BarPurchaseListService
{
    public function __construct(
        private HotelSettingsService $settingsService,
        private AccountsDataService $accounts,
    ) {}

    /** @return array<string, mixed> */
    public function purchaseListSettings(Hotel $hotel, string $workflowKey = 'bar'): array
    {
        $defaults = $this->settingsService->defaultDepartmentProcurement()['purchase_list'];
        $workflow = DepartmentProcurement::workflow($workflowKey);
        $settings = $this->settingsService->ensureDefaults($hotel);
        $settingsKey = (string) $workflow['settings_key'];

        if (! empty($workflow['settings_nested'])) {
            $root = is_array($settings->{$settingsKey}) ? $settings->{$settingsKey} : $this->settingsService->defaultBar();

            return is_array($root['purchase_list'] ?? null)
                ? array_merge($defaults, $root['purchase_list'])
                : $defaults;
        }

        $root = is_array($settings->{$settingsKey})
            ? $settings->{$settingsKey}
            : $this->settingsService->defaultDepartmentProcurement();

        return is_array($root['purchase_list'] ?? null)
            ? array_merge($defaults, $root['purchase_list'])
            : $defaults;
    }

    /** @return list<string> */
    public function resolveRecipientEmails(Hotel $hotel, string $workflowKey = 'bar'): array
    {
        $config = $this->purchaseListSettings($hotel, $workflowKey);
        $emails = [];

        foreach ($config['recipient_emails'] ?? [] as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }

        $staffIds = collect($config['recipient_staff_user_ids'] ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($staffIds->isNotEmpty()) {
            User::query()
                ->where('hotel_id', $hotel->id)
                ->whereIn('id', $staffIds)
                ->whereNotNull('email')
                ->pluck('email')
                ->each(function ($email) use (&$emails) {
                    $email = trim((string) $email);
                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = strtolower($email);
                    }
                });
        }

        if (! empty($config['include_hotel_email']) && $hotel->email) {
            $hotelEmail = trim((string) $hotel->email);
            if (filter_var($hotelEmail, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($hotelEmail);
            }
        }

        return array_values(array_unique($emails));
    }

    public function hasRecipients(Hotel $hotel, string $workflowKey = 'bar'): bool
    {
        return $this->resolveRecipientEmails($hotel, $workflowKey) !== [];
    }

    public function budgetCurrencyForHotel(Hotel $hotel, string $workflowKey = 'bar'): string
    {
        $settings = $this->purchaseListSettings($hotel, $workflowKey);
        $configured = strtoupper(trim((string) ($settings['budget_currency'] ?? '')));
        if ($configured !== '') {
            return $configured;
        }

        $templateKey = DepartmentProcurement::workflow($workflowKey)['template_key'] ?? null;
        $service = $templateKey
            ? HotelPmsService::query()
                ->where('hotel_id', $hotel->id)
                ->where('template_key', $templateKey)
                ->where('is_active', true)
                ->first()
            : null;

        if ($service && filled($service->currency)) {
            return strtoupper(trim((string) $service->currency));
        }

        $localFromRates = $hotel->ratePlans()
            ->whereNotNull('local_currency')
            ->where('local_currency', '!=', '')
            ->distinct()
            ->pluck('local_currency')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->first();
        if ($localFromRates) {
            return $localFromRates;
        }

        if ($service) {
            return $service->displayCurrency($hotel);
        }

        $hotelCurrency = strtoupper(trim((string) ($hotel->currency ?? '')));
        if ($hotelCurrency !== '') {
            return $hotelCurrency;
        }

        $fallback = strtoupper(trim((string) config('hotel_bar.fallback_budget_currency', '')));

        return $fallback !== '' ? $fallback : 'TZS';
    }

    public function resolvedBudgetCurrency(HotelBarPurchaseList $list): string
    {
        if ($list->recipient_status === HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED) {
            $stored = strtoupper(trim((string) ($list->budget_currency ?? '')));
            if ($stored !== '') {
                return $stored;
            }
        }

        return $this->budgetCurrencyForHotel($list->hotel, $list->workflowKey());
    }

    public function syncBudgetCurrencyIfEditable(HotelBarPurchaseList $list): HotelBarPurchaseList
    {
        if ($list->recipientBudgetLocked()) {
            return $list;
        }

        $currency = $this->budgetCurrencyForHotel($list->hotel, $list->workflowKey());
        if (strtoupper((string) ($list->budget_currency ?? '')) !== $currency) {
            $list->update(['budget_currency' => $currency]);

            return $list->fresh(['lines', 'creator', 'hotel', 'department']);
        }

        return $list;
    }

    /** @param  list<array{item_name: string, quantity: float, unit?: ?string, line_notes?: ?string}>  $lines */
    public function createAndSend(
        Hotel $hotel,
        User $author,
        ?string $notes,
        array $lines,
        ?int $departmentId = null,
        string $workflowKey = 'bar',
    ): HotelBarPurchaseList {
        $label = DepartmentProcurement::label($workflowKey);
        $recipients = $this->resolveRecipientEmails($hotel, $workflowKey);
        if ($recipients === []) {
            throw new InvalidArgumentException('No purchase list recipients configured. Set them under Settings → '.$label.'.');
        }

        $list = DB::transaction(function () use ($hotel, $author, $notes, $lines, $recipients, $departmentId, $workflowKey) {
            $reference = $this->nextReference($hotel, $workflowKey);

            $list = HotelBarPurchaseList::query()->create([
                'hotel_id' => $hotel->id,
                'workflow_key' => $workflowKey,
                'hotel_department_id' => $departmentId,
                'reference_no' => $reference,
                'status' => HotelBarPurchaseList::STATUS_SENT,
                'recipient_status' => HotelBarPurchaseList::RECIPIENT_AWAITING,
                'notes' => $notes,
                'budget_currency' => $this->budgetCurrencyForHotel($hotel, $workflowKey),
                'sent_to' => $recipients,
                'created_by_user_id' => $author->id,
                'sent_at' => now(),
            ]);

            foreach ($lines as $index => $line) {
                $list->lines()->create([
                    'item_name' => $line['item_name'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'] ?? null,
                    'line_notes' => $line['line_notes'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return $list->load(['lines', 'creator', 'hotel']);
        });

        try {
            Mail::to($recipients)->send(new BarPurchaseListMail($list));
        } catch (\Throwable $e) {
            report($e);
            $list->update(['status' => HotelBarPurchaseList::STATUS_FAILED]);

            return $list->fresh(['lines', 'creator', 'hotel']);
        }

        return $list;
    }

    public function sharedViewUrl(HotelBarPurchaseList $list, int $daysValid = 90): string
    {
        return URL::temporarySignedRoute(
            DepartmentProcurement::signedSharedRouteName($list->workflowKey()),
            now()->addDays($daysValid),
            ['purchaseList' => $list->id]
        );
    }

    public function sharedSubmitUrl(HotelBarPurchaseList $list, int $daysValid = 90): string
    {
        return URL::temporarySignedRoute(
            DepartmentProcurement::signedSharedSubmitRouteName($list->workflowKey()),
            now()->addDays($daysValid),
            ['purchaseList' => $list->id]
        );
    }

    public function markReceived(HotelBarPurchaseList $list): HotelBarPurchaseList
    {
        if ($list->received_at) {
            return $list;
        }

        $updates = ['received_at' => now()];
        if ($list->recipient_status === HotelBarPurchaseList::RECIPIENT_AWAITING) {
            $updates['recipient_status'] = HotelBarPurchaseList::RECIPIENT_IN_PROGRESS;
        }

        $list->update($updates);

        return $list->fresh(['lines', 'creator', 'hotel']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $lines
     */
    public function saveRecipientResponse(
        HotelBarPurchaseList $list,
        array $payload,
        array $lines,
        bool $submitBudget,
        ?string $responderName = null,
        ?string $responderEmail = null,
    ): HotelBarPurchaseList {
        return DB::transaction(function () use ($list, $payload, $lines, $submitBudget, $responderName, $responderEmail) {
            $currency = $this->budgetCurrencyForHotel($list->hotel);
            $total = 0.0;
            $keepIds = [];

            foreach ($lines as $index => $row) {
                $name = trim((string) ($row['item_name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $qty = max(1, (int) round((float) ($row['quantity'] ?? 1)));
                $unitCostRaw = $row['unit_cost'] ?? null;
                $unitCost = ($unitCostRaw !== null && $unitCostRaw !== '')
                    ? max(0, (float) $unitCostRaw)
                    : null;
                $lineBudget = $unitCost !== null ? round($qty * $unitCost, 2) : null;
                if ($lineBudget !== null) {
                    $total += $lineBudget;
                }

                $attrs = [
                    'item_name' => $name,
                    'quantity' => $qty,
                    'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
                    'line_notes' => trim((string) ($row['line_notes'] ?? '')) ?: null,
                    'unit_cost' => $unitCost,
                    'line_budget' => $lineBudget,
                    'sort_order' => $index,
                ];

                if (! empty($row['id'])) {
                    $line = $list->lines()->whereKey((int) $row['id'])->first();
                    if ($line) {
                        $line->update($attrs);
                        $keepIds[] = $line->id;

                        continue;
                    }
                }

                $line = $list->lines()->create($attrs);
                $keepIds[] = $line->id;
            }

            if ($keepIds === []) {
                throw new InvalidArgumentException('Add at least one line item.');
            }

            $list->lines()->whereNotIn('id', $keepIds)->delete();

            $vendorId = (int) ($payload['hotel_vendor_id'] ?? 0);
            if ($vendorId <= 0) {
                $vendorId = (int) ($list->hotel_vendor_id ?? 0);
            }
            if ($vendorId <= 0) {
                throw new InvalidArgumentException('Select a supplier.');
            }

            $vendorExists = HotelVendor::query()
                ->where('hotel_id', $list->hotel_id)
                ->whereKey($vendorId)
                ->exists();
            if (! $vendorExists) {
                throw new InvalidArgumentException('Select a valid supplier.');
            }

            $listUpdates = [
                'hotel_vendor_id' => $vendorId,
                'recipient_notes' => trim((string) ($payload['recipient_notes'] ?? '')) ?: null,
                'budget_currency' => $currency,
                'budget_total' => $total > 0 ? round($total, 2) : null,
                'recipient_status' => $submitBudget
                    ? HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED
                    : HotelBarPurchaseList::RECIPIENT_IN_PROGRESS,
            ];

            if ($submitBudget) {
                $listUpdates['budget_prepared_at'] = now();
                $listUpdates['budget_prepared_by_name'] = $responderName;
                $listUpdates['budget_prepared_by_email'] = $responderEmail;
                $listUpdates['accounting_status'] = HotelBarPurchaseList::ACCOUNTING_PENDING;
                $listUpdates['accounting_notes'] = null;
                $listUpdates['accounting_reviewed_at'] = null;
                $listUpdates['accounting_reviewed_by_user_id'] = null;
                $listUpdates['accounting_reviewed_by_name'] = null;
                $listUpdates['market_status'] = HotelBarPurchaseList::MARKET_WAITING;
                $listUpdates['market_released_at'] = null;
                $listUpdates['market_completed_at'] = null;
                $listUpdates['market_completed_by_user_id'] = null;
            }

            if (! $list->received_at) {
                $listUpdates['received_at'] = now();
            }

            $list->update($listUpdates);

            return $list->fresh(['lines', 'creator', 'hotel']);
        });
    }

    public function returnPurchaseListToDepartment(HotelBarPurchaseList $list, User $user, ?string $notes = null): HotelBarPurchaseList
    {
        if ($list->recipientBudgetLocked()) {
            throw new InvalidArgumentException('This list was already submitted to Accounts.');
        }

        $message = trim((string) $notes);
        if ($message !== '') {
            $message = '[Returned by stores] '.$message;
        }

        $list->lines()->update([
            'unit_cost' => null,
            'line_budget' => null,
        ]);

        $list->update([
            'recipient_status' => HotelBarPurchaseList::RECIPIENT_RETURNED,
            'recipient_notes' => $message !== '' ? $message : $list->recipient_notes,
            'budget_total' => null,
            'budget_prepared_at' => null,
            'budget_prepared_by_name' => null,
            'budget_prepared_by_email' => null,
            'accounting_status' => null,
            'accounting_notes' => null,
            'accounting_reviewed_at' => null,
            'accounting_reviewed_by_user_id' => null,
            'accounting_reviewed_by_name' => null,
            'received_at' => null,
        ]);

        return $list->fresh(['lines', 'creator', 'hotel', 'department']);
    }

    /**
     * @param  list<array{item_name: string, quantity: float, unit?: ?string, line_notes?: ?string}>|null  $lines
     */
    public function resubmitReturnedListToStores(
        HotelBarPurchaseList $list,
        ?string $notes = null,
        ?array $lines = null,
    ): HotelBarPurchaseList {
        if (! $list->isReturnedToDepartment()) {
            throw new InvalidArgumentException('This list is not with the department for revision.');
        }

        if ($lines !== null && $lines !== []) {
            $this->replaceReturnedListLines($list, $lines, $notes);
            $list = $list->fresh(['lines', 'creator', 'hotel', 'department']);
        } elseif ($notes !== null) {
            $list->update(['notes' => trim($notes) !== '' ? trim($notes) : null]);
        }

        $workflowKey = $list->workflowKey();
        $recipients = $this->resolveRecipientEmails($list->hotel, $workflowKey);
        if ($recipients === []) {
            throw new InvalidArgumentException('No purchase list recipients configured.');
        }

        $list->update([
            'recipient_status' => HotelBarPurchaseList::RECIPIENT_AWAITING,
            'recipient_notes' => null,
            'received_at' => null,
            'sent_at' => now(),
            'status' => HotelBarPurchaseList::STATUS_SENT,
            'sent_to' => $recipients,
        ]);

        $list = $list->fresh(['lines', 'creator', 'hotel', 'department']);

        try {
            Mail::to($recipients)->send(new BarPurchaseListMail($list));
        } catch (\Throwable $e) {
            report($e);
            $list->update(['status' => HotelBarPurchaseList::STATUS_FAILED]);
        }

        return $list->fresh(['lines', 'creator', 'hotel', 'department']);
    }

    /**
     * @param  list<array{item_name: string, quantity: float, unit?: ?string, line_notes?: ?string}>  $lines
     */
    public function replaceReturnedListLines(HotelBarPurchaseList $list, array $lines, ?string $notes = null): void
    {
        if (! $list->isReturnedToDepartment()) {
            throw new InvalidArgumentException('This list is not with the department for revision.');
        }

        DB::transaction(function () use ($list, $lines, $notes) {
            $list->lines()->delete();

            foreach ($lines as $index => $line) {
                $name = trim((string) ($line['item_name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $list->lines()->create([
                    'item_name' => $name,
                    'quantity' => (float) ($line['quantity'] ?? 1),
                    'unit' => trim((string) ($line['unit'] ?? '')) ?: null,
                    'line_notes' => trim((string) ($line['line_notes'] ?? '')) ?: null,
                    'sort_order' => $index,
                ]);
            }

            if ($list->lines()->count() === 0) {
                throw new InvalidArgumentException('Add at least one item to the purchase list.');
            }

            if ($notes !== null) {
                $list->update(['notes' => trim($notes) !== '' ? trim($notes) : null]);
            }
        });
    }

    public function quickSubmitBudgetToAccounts(HotelBarPurchaseList $list, User $user): HotelBarPurchaseList
    {
        if ((int) ($list->hotel_vendor_id ?? 0) <= 0) {
            throw new InvalidArgumentException('Select a supplier on the list before submitting to Accounts.');
        }

        if (! $list->canQuickSubmitBudgetToAccounts()) {
            throw new InvalidArgumentException('Enter unit costs for every line before submitting to Accounts.');
        }

        $list->load('lines');
        $lines = $list->lines->map(fn ($line) => [
            'id' => $line->id,
            'item_name' => $line->item_name,
            'quantity' => $line->quantity,
            'unit' => $line->unit,
            'line_notes' => $line->line_notes,
            'unit_cost' => $line->unit_cost,
        ])->all();

        return $this->saveRecipientResponse(
            $list,
            [
                'recipient_notes' => $list->recipient_notes,
                'hotel_vendor_id' => $list->hotel_vendor_id,
            ],
            $lines,
            true,
            $user->name,
            $user->email,
        );
    }

    /**
     * @return array{submitted: list<string>, skipped: list<string>}
     */
    public function batchQuickSubmitBudgetsToAccounts(Hotel $hotel, User $user, ?int $departmentId = null): array
    {
        $lists = $this->allReceivedListsForUser($hotel, $user, null, $departmentId, 500)
            ->filter(fn (HotelBarPurchaseList $list) => $list->canQuickSubmitBudgetToAccounts());

        $submitted = [];
        $skipped = [];

        foreach ($lists as $list) {
            try {
                $this->quickSubmitBudgetToAccounts($list, $user);
                $submitted[] = $list->reference_no;
            } catch (InvalidArgumentException) {
                $skipped[] = $list->reference_no;
            }
        }

        return ['submitted' => $submitted, 'skipped' => $skipped];
    }

    /** @return Collection<int, HotelBarPurchaseList> */
    public function recentLists(Hotel $hotel, int $limit = 30, string $workflowKey = 'bar'): Collection
    {
        $this->backfillReturnedRecipientStatuses($hotel);

        return HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->with(['creator', 'lines', 'department'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function departmentReturnedPurchaseListsCount(Hotel $hotel, string $workflowKey = 'bar'): int
    {
        $this->backfillReturnedRecipientStatuses($hotel);

        return (int) HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->whereNotNull('sent_at')
            ->where(function (Builder $q) {
                $q->where('recipient_status', HotelBarPurchaseList::RECIPIENT_RETURNED)
                    ->orWhere(function (Builder $inner) {
                        $inner->where('recipient_status', HotelBarPurchaseList::RECIPIENT_AWAITING)
                            ->where('recipient_notes', 'like', '[Returned by stores]%');
                    });
            })
            ->count();
    }

    public function receivedListsNeedingActionCount(Hotel $hotel, User $user, string $workflowKey = 'bar'): int
    {
        if (! $this->userShouldSeeDepartmentReceivedTab($hotel, $user, $workflowKey)) {
            return 0;
        }

        if (! $this->userIsPurchaseListRecipient($hotel, $user, $workflowKey)) {
            return 0;
        }

        return (int) $this->receivedListsQueryForUser($hotel, $user, $workflowKey)
            ->where(function (Builder $q) {
                $q->whereIn('recipient_status', [
                    HotelBarPurchaseList::RECIPIENT_AWAITING,
                    HotelBarPurchaseList::RECIPIENT_IN_PROGRESS,
                ])->orWhere(function (Builder $inner) {
                    $inner->where('recipient_status', HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
                        ->where('accounting_status', HotelBarPurchaseList::ACCOUNTING_REJECTED);
                });
            })
            ->count();
    }

    public function marketListsPendingPurchaseCount(Hotel $hotel, string $workflowKey = 'bar'): int
    {
        return (int) HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->where('market_status', HotelBarPurchaseList::MARKET_RELEASED)
            ->count();
    }

    public function userHasStoresProcurementDeskAccess(Hotel $hotel, User $user): bool
    {
        return $user->hasPermission('stores.view');
    }

    public function userCanAccessStoresDepartmentLists(Hotel $hotel, User $user): bool
    {
        if ($user->hasFullHotelAccess() || $this->userHasStoresProcurementDeskAccess($hotel, $user)) {
            return true;
        }

        foreach (config('hotel_stores.po_permission_keys', []) as $permissionKey) {
            if (is_string($permissionKey) && $permissionKey !== '' && $user->hasPermission($permissionKey)) {
                return true;
            }
        }

        foreach (DepartmentProcurement::workflowKeys() as $workflowKey) {
            if ($this->userIsConfiguredPurchaseListRecipient($hotel, $user, $workflowKey)) {
                return true;
            }
        }

        return false;
    }

    public function pendingStoreReviewListsCount(Hotel $hotel, User $user): int
    {
        if (! $this->userCanAccessStoresDepartmentLists($hotel, $user)) {
            return 0;
        }

        $this->backfillAllSentListDepartments($hotel);

        return (int) $this->sentListsInboxQuery($hotel, null, null)
            ->where(function (Builder $q) {
                $q->whereIn('recipient_status', [
                    HotelBarPurchaseList::RECIPIENT_AWAITING,
                    HotelBarPurchaseList::RECIPIENT_IN_PROGRESS,
                ])->orWhere(function (Builder $inner) {
                    $inner->where('recipient_status', HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
                        ->where('accounting_status', HotelBarPurchaseList::ACCOUNTING_REJECTED);
                });
            })
            ->count();
    }

    private function userShowsAllSentDepartmentLists(Hotel $hotel, User $user): bool
    {
        return $user->hasFullHotelAccess() || $this->userHasStoresProcurementDeskAccess($hotel, $user);
    }

    public function userIsPurchaseListRecipient(Hotel $hotel, User $user, string $workflowKey = 'bar'): bool
    {
        if ($this->userShowsAllSentDepartmentLists($hotel, $user)) {
            return true;
        }

        return $this->userIsConfiguredPurchaseListRecipient($hotel, $user, $workflowKey);
    }

    /** Store verification queue on a department module (Bar, HK, …). Bar uses Stores → Purchase orders instead. */
    public function userShouldSeeDepartmentReceivedTab(Hotel $hotel, User $user, string $workflowKey): bool
    {
        if ($workflowKey === 'bar') {
            return false;
        }

        if ($this->userHasStoresProcurementDeskAccess($hotel, $user)) {
            return true;
        }

        return $this->userIsConfiguredPurchaseListRecipient($hotel, $user, $workflowKey);
    }

    /** Department staff must not verify lists they sent; store desk users can. */
    public function userCanVerifyPurchaseList(Hotel $hotel, User $user, HotelBarPurchaseList $list): bool
    {
        if ((int) $list->hotel_id !== (int) $hotel->id || $list->sent_at === null) {
            return false;
        }

        if ((int) $list->created_by_user_id === (int) $user->id
            && ! $this->userHasStoresProcurementDeskAccess($hotel, $user)) {
            return false;
        }

        if ($this->userHasStoresProcurementDeskAccess($hotel, $user) || $user->hasFullHotelAccess()) {
            return true;
        }

        return $this->userIsConfiguredPurchaseListRecipient($hotel, $user, $list->workflowKey())
            && $this->receivedListsQueryForUser($hotel, $user, $list->workflowKey())
                ->whereKey($list->id)
                ->exists();
    }

    private function userIsConfiguredPurchaseListRecipient(Hotel $hotel, User $user, string $workflowKey = 'bar'): bool
    {

        $email = strtolower(trim((string) ($user->email ?? '')));
        $config = $this->purchaseListSettings($hotel, $workflowKey);
        $staffIds = collect($config['recipient_staff_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        if ($staffIds->contains((int) $user->id)) {
            return true;
        }

        if ($email === '') {
            return false;
        }

        foreach ($config['recipient_emails'] ?? [] as $configured) {
            if (strtolower(trim((string) $configured)) === $email) {
                return true;
            }
        }

        if (! empty($config['include_hotel_email']) && $hotel->email) {
            if (strtolower(trim((string) $hotel->email)) === $email) {
                return true;
            }
        }

        return false;
    }

    /** @return Builder<HotelBarPurchaseList> */
    public function receivedListsQueryForUser(Hotel $hotel, User $user, string $workflowKey = 'bar'): Builder
    {
        $query = HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->whereNotNull('sent_at')
            ->where('recipient_status', '!=', HotelBarPurchaseList::RECIPIENT_RETURNED);

        if (! $this->userIsPurchaseListRecipient($hotel, $user, $workflowKey)) {
            return $query->whereRaw('1 = 0');
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        $config = $this->purchaseListSettings($hotel, $workflowKey);
        $staffIds = collect($config['recipient_staff_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        if ($this->userHasStoresProcurementDeskAccess($hotel, $user) || $user->hasFullHotelAccess()) {
            return $this->applyReceivedVisibilityRules($query, $hotel, $user);
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('created_by_user_id', '!=', $user->id)
                ->orWhereNull('created_by_user_id');
        });

        if (! $staffIds->contains((int) $user->id)) {
            if ($email === '') {
                return $query->whereRaw('1 = 0');
            }
            $query->whereJsonContains('sent_to', $email);
        } elseif ($email !== '') {
            $query->whereJsonContains('sent_to', $email);
        }

        return $this->applyReceivedVisibilityRules($query, $hotel, $user);
    }

    /** @param  Builder<HotelBarPurchaseList>  $query */
    private function applyReceivedVisibilityRules(Builder $query, Hotel $hotel, User $user): Builder
    {
        $query->where(function (Builder $q) {
            $q->where('accounting_status', '!=', HotelBarPurchaseList::ACCOUNTING_APPROVED)
                ->orWhereNull('accounting_status');
        });

        $email = strtolower(trim((string) ($user->email ?? '')));
        $fullAccess = $user->hasFullHotelAccess();

        $query->where(function (Builder $q) use ($email, $fullAccess) {
            $q->where('accounting_status', '!=', HotelBarPurchaseList::ACCOUNTING_REJECTED)
                ->orWhereNull('accounting_status');

            if ($fullAccess) {
                $q->orWhere('accounting_status', HotelBarPurchaseList::ACCOUNTING_REJECTED);
            } elseif ($email !== '') {
                $q->orWhere(function (Builder $inner) use ($email) {
                    $inner->where('accounting_status', HotelBarPurchaseList::ACCOUNTING_REJECTED)
                        ->whereRaw('LOWER(TRIM(COALESCE(budget_prepared_by_email, ""))) = ?', [$email]);
                });
            }
        });

        return $query;
    }

    public function userCanViewReceivedPurchaseList(Hotel $hotel, User $user, HotelBarPurchaseList $list): bool
    {
        if ((int) $list->hotel_id !== (int) $hotel->id || $list->sent_at === null) {
            return false;
        }

        if ($this->userHasStoresProcurementDeskAccess($hotel, $user) || $user->hasFullHotelAccess()) {
            return $this->receivedListsQueryForUser($hotel, $user, $list->workflowKey())
                ->whereKey($list->id)
                ->exists();
        }

        if ((int) $list->created_by_user_id === (int) $user->id) {
            return false;
        }

        if (! $this->userIsConfiguredPurchaseListRecipient($hotel, $user, $list->workflowKey())) {
            return false;
        }

        return $this->receivedListsQueryForUser($hotel, $user, $list->workflowKey())
            ->whereKey($list->id)
            ->exists();
    }

    /** @return Collection<int, HotelBarPurchaseList> */
    public function receivedListsForUser(
        Hotel $hotel,
        User $user,
        ?int $departmentId = null,
        int $limit = 50,
        string $workflowKey = 'bar',
    ): Collection {
        $query = $this->receivedListsQueryForUser($hotel, $user, $workflowKey);

        if ($departmentId) {
            $defaultDeptId = $this->resolveDepartmentId($hotel, null, $workflowKey);
            $query->where(function (Builder $q) use ($departmentId, $defaultDeptId) {
                $q->where('hotel_department_id', $departmentId);
                if ($defaultDeptId && $departmentId === (int) $defaultDeptId) {
                    $q->orWhereNull('hotel_department_id');
                }
            });
        }

        return $query
            ->with(['creator', 'lines.receivingPackage', 'department'])
            ->latest('sent_at')
            ->limit($limit)
            ->get();
    }

    public function userIsAnyPurchaseListRecipient(Hotel $hotel, User $user): bool
    {
        return $this->userCanAccessStoresDepartmentLists($hotel, $user);
    }

    /** @return Collection<int, string> */
    public function workflowLabelsForReceivedLists(Hotel $hotel, User $user): Collection
    {
        $keys = $this->userShowsAllSentDepartmentLists($hotel, $user)
            ? DepartmentProcurement::workflowKeys()
            : collect(DepartmentProcurement::workflowKeys())
                ->filter(fn (string $workflowKey) => $this->userIsConfiguredPurchaseListRecipient($hotel, $user, $workflowKey))
                ->all();

        return collect($keys)->mapWithKeys(fn (string $workflowKey) => [
            $workflowKey => DepartmentProcurement::workflow($workflowKey)['label'],
        ]);
    }

    /** @return Collection<int, HotelDepartment> */
    public function departmentsForAllReceivedLists(Hotel $hotel, User $user, ?string $workflowFilter = null): Collection
    {
        if ($this->userShowsAllSentDepartmentLists($hotel, $user)) {
            $this->backfillAllSentListDepartments($hotel);

            return $this->departmentsWithActiveSentLists($hotel);
        }

        $departmentIds = collect();

        foreach (DepartmentProcurement::workflowKeys() as $workflowKey) {
            if ($workflowFilter !== null && $workflowFilter !== '' && $workflowKey !== $workflowFilter) {
                continue;
            }
            if (! $this->userIsConfiguredPurchaseListRecipient($hotel, $user, $workflowKey)) {
                continue;
            }
            $departmentIds = $departmentIds->merge(
                $this->departmentsWithSentListsForUser($hotel, $user, $workflowKey)->pluck('id')
            );
        }

        $departmentIds = $departmentIds->unique()->filter()->values();
        if ($departmentIds->isEmpty()) {
            return collect();
        }

        return HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $departmentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Department lists on Stores → Purchase orders (includes accountant-approved / paid-in-progress).
     *
     * @return Collection<int, HotelBarPurchaseList>
     */
    public function storePurchaseQueueListsForUser(
        Hotel $hotel,
        User $user,
        ?string $workflowFilter = null,
        ?int $departmentId = null,
        int $limit = 100,
    ): Collection {
        if (! $this->userCanAccessStoresDepartmentLists($hotel, $user)) {
            return collect();
        }

        $this->backfillAllSentListDepartments($hotel);

        return $this->sentListsInboxQuery($hotel, $workflowFilter, $departmentId)
            ->with(['creator', 'lines.receivingPackage', 'department', 'purchaseOrder'])
            ->latest('sent_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, HotelBarPurchaseList> */
    public function allReceivedListsForUser(
        Hotel $hotel,
        User $user,
        ?string $workflowFilter = null,
        ?int $departmentId = null,
        int $limit = 100,
    ): Collection {
        if ($this->userShowsAllSentDepartmentLists($hotel, $user)) {
            $this->backfillAllSentListDepartments($hotel);

            return $this->sentListsInboxQuery($hotel, $workflowFilter, $departmentId)
                ->with(['creator', 'lines.receivingPackage', 'department'])
                ->latest('sent_at')
                ->limit($limit)
                ->get();
        }

        $merged = collect();

        foreach (DepartmentProcurement::workflowKeys() as $workflowKey) {
            if ($workflowFilter !== null && $workflowFilter !== '' && $workflowKey !== $workflowFilter) {
                continue;
            }
            if (! $this->userIsConfiguredPurchaseListRecipient($hotel, $user, $workflowKey)) {
                continue;
            }

            $merged = $merged->merge(
                $this->receivedListsForUser($hotel, $user, $departmentId, $limit, $workflowKey)
            );
        }

        return $merged
            ->unique('id')
            ->sortByDesc(fn (HotelBarPurchaseList $list) => $list->sent_at?->getTimestamp() ?? 0)
            ->take($limit)
            ->values();
    }

    public function backfillAllSentListDepartments(Hotel $hotel): void
    {
        $this->backfillReturnedRecipientStatuses($hotel);

        foreach (DepartmentProcurement::workflowKeys() as $workflowKey) {
            $this->backfillSentListDepartments($hotel, $workflowKey);
        }
    }

    public function backfillReturnedRecipientStatuses(Hotel $hotel): void
    {
        HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('recipient_status', HotelBarPurchaseList::RECIPIENT_AWAITING)
            ->where('recipient_notes', 'like', '[Returned by stores]%')
            ->update([
                'recipient_status' => HotelBarPurchaseList::RECIPIENT_RETURNED,
                'received_at' => null,
            ]);
    }

    /** @return Builder<HotelBarPurchaseList> */
    private function sentListsInboxQuery(Hotel $hotel, ?string $workflowFilter, ?int $departmentId): Builder
    {
        $query = HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('sent_at')
            ->where('recipient_status', '!=', HotelBarPurchaseList::RECIPIENT_RETURNED);

        if ($workflowFilter !== null && $workflowFilter !== '') {
            $query->where('workflow_key', $workflowFilter);
        }

        if ($departmentId) {
            $query->where('hotel_department_id', $departmentId);
        }

        return $query;
    }

    /**
     * Departments that have at least one sent purchase list (hotel-wide, for Received filters).
     *
     * @return Collection<int, HotelDepartment>
     */
    public function departmentsWithActiveSentLists(Hotel $hotel): Collection
    {
        $this->backfillSentListDepartments($hotel);

        $departmentIds = HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('sent_at')
            ->whereNotNull('hotel_department_id')
            ->distinct()
            ->pluck('hotel_department_id');

        if ($departmentIds->isEmpty()) {
            return collect();
        }

        return HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $departmentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, HotelDepartment> */
    public function departmentsWithSentListsForUser(Hotel $hotel, User $user, string $workflowKey = 'bar'): Collection
    {
        if (! $this->userIsPurchaseListRecipient($hotel, $user, $workflowKey)) {
            return collect();
        }

        $this->backfillSentListDepartments($hotel, $workflowKey);
        $defaultDeptId = $this->resolveDepartmentId($hotel, null, $workflowKey);

        $departmentIds = $this->receivedListsQueryForUser($hotel, $user, $workflowKey)
            ->select('hotel_department_id')
            ->distinct()
            ->pluck('hotel_department_id')
            ->map(fn ($id) => $id ?: $defaultDeptId)
            ->filter()
            ->unique()
            ->values();

        if ($departmentIds->isEmpty()) {
            return collect();
        }

        return HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('id', $departmentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function backfillSentListDepartments(Hotel $hotel, string $workflowKey = 'bar'): void
    {
        $defaultId = $this->resolveDepartmentId($hotel, null, $workflowKey);
        if (! $defaultId) {
            return;
        }

        HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->whereNotNull('sent_at')
            ->whereNull('hotel_department_id')
            ->update(['hotel_department_id' => $defaultId]);
    }

    public function ensureBarDepartment(Hotel $hotel): void
    {
        $this->ensureProcurementDepartments($hotel, 'bar');
    }

    public function ensureProcurementDepartments(Hotel $hotel, string $workflowKey): void
    {
        $names = DepartmentProcurement::workflow($workflowKey)['department_seed_names'] ?? [];
        foreach ($names as $name) {
            HotelDepartment::query()->firstOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $name],
                ['is_active' => true]
            );
        }
    }

    public function resolveDepartmentId(Hotel $hotel, ?int $departmentId, string $workflowKey = 'bar'): ?int
    {
        if ($departmentId) {
            $exists = HotelDepartment::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($departmentId)
                ->exists();
            if ($exists) {
                return $departmentId;
            }
        }

        $patterns = DepartmentProcurement::workflow($workflowKey)['department_name_patterns'] ?? [];
        $query = HotelDepartment::query()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true);

        if ($patterns !== []) {
            $query->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('name', 'like', $pattern);
                }
            });
        }

        return $query->orderBy('name')->value('id');
    }

    /** @return array<string, float|null> item_name => expected_unit_price */
    public function expectedPricesByItemName(Hotel $hotel, string $workflowKey = 'bar'): array
    {
        $templateKey = DepartmentProcurement::workflow($workflowKey)['template_key'] ?? null;
        if (! $templateKey) {
            return [];
        }

        $service = HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $templateKey)
            ->first();
        if (! $service) {
            return [];
        }

        $map = [];
        foreach ($service->catalogItems()->get(['item_name', 'expected_unit_price']) as $item) {
            $key = strtolower(trim($item->item_name));
            if ($key !== '') {
                $map[$key] = $item->expected_unit_price !== null ? (float) $item->expected_unit_price : null;
            }
        }

        foreach (HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->where('rate', '>', 0)
            ->get(['name', 'rate']) as $masterItem) {
            $key = strtolower(trim($masterItem->name));
            if ($key !== '') {
                $map[$key] = (float) $masterItem->rate;
            }
        }

        return $map;
    }

    public function estimatedLineBudgetTotal(HotelBarPurchaseList $list): float
    {
        if ($list->budget_total !== null) {
            return round((float) $list->budget_total, 2);
        }

        $list->loadMissing('lines');
        $prices = $this->expectedPricesByItemName($list->hotel, $list->workflowKey());
        $total = 0.0;
        $matched = false;

        foreach ($list->lines as $line) {
            $key = strtolower(trim((string) $line->item_name));
            $rate = $prices[$key] ?? null;
            if ($rate === null || $rate <= 0) {
                continue;
            }
            $matched = true;
            $total += (float) $line->quantity * (float) $rate;
        }

        return $matched ? round($total, 2) : 0.0;
    }

    /**
     * @return array<string, HotelPmsServiceItem> normalized item_name => catalog row
     */
    public function catalogItemsByItemName(Hotel $hotel, string $workflowKey = 'bar'): array
    {
        $templateKey = DepartmentProcurement::workflow($workflowKey)['template_key'] ?? null;
        if (! $templateKey) {
            return [];
        }

        $service = HotelPmsService::query()
            ->where('hotel_id', $hotel->id)
            ->where('template_key', $templateKey)
            ->first();
        if (! $service) {
            return [];
        }

        $map = [];
        foreach ($service->catalogItems()->with(['category', 'sellOptions.package'])->get() as $item) {
            $key = strtolower(trim($item->item_name));
            if ($key !== '') {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**
     * @return array<string, HotelStoreMasterItem> normalized item_name => store master row
     */
    public function storeMasterItemsByItemName(Hotel $hotel): array
    {
        $map = [];
        foreach (HotelStoreMasterItem::query()
            ->where('hotel_id', $hotel->id)
            ->with(['storePackage', 'sellOptions.storePackage'])
            ->get() as $item) {
            $key = strtolower(trim($item->name));
            if ($key !== '') {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    public function pmsPackageIdForStorePackage(HotelStorePackage $storePackage, Collection $pmsPackages): ?int
    {
        $linked = (int) ($storePackage->hotel_pms_service_package_id ?? 0);
        if ($linked > 0) {
            return $linked;
        }

        $needleAbbr = strtoupper(trim((string) ($storePackage->abbreviation ?: '')));
        $needleName = strtoupper(trim((string) $storePackage->name));
        foreach ($pmsPackages as $pkg) {
            $abbr = strtoupper(trim((string) ($pkg->abbreviation ?: '')));
            $name = strtoupper(trim((string) $pkg->name));
            if ($needleAbbr !== '' && ($needleAbbr === $abbr || $needleAbbr === $name)) {
                return (int) $pkg->id;
            }
            if ($needleName !== '' && ($needleName === $name || $needleName === $abbr)) {
                return (int) $pkg->id;
            }
        }

        return null;
    }

    /**
     * Sale units for market entry — store master item sell options take precedence over bar catalog.
     *
     * @return list<array{package_id: int, unit_label: string, pieces_per_sell_unit: float, unit_price: ?float}>
     */
    public function marketSellMethodsForLine(
        ?HotelPmsServiceItem $catalog,
        ?HotelStoreMasterItem $master,
        string $lineUnit,
        Collection $pmsPackages,
    ): array {
        $catalog?->loadMissing('sellOptions.package');
        $master?->loadMissing(['sellOptions.storePackage', 'storePackage']);

        $catalogPricesByPkg = [];
        if ($catalog) {
            foreach ($catalog->sellOptions as $opt) {
                $pkgId = (int) $opt->hotel_pms_service_package_id;
                if ($pkgId > 0) {
                    $catalogPricesByPkg[$pkgId] = $opt->unit_price !== null ? (float) $opt->unit_price : null;
                }
            }
        }

        if ($master && $master->sellOptions->isNotEmpty()) {
            $methods = [];
            foreach ($master->sellOptions as $opt) {
                $storePkg = $opt->storePackage;
                if (! $storePkg) {
                    continue;
                }
                $pmsPkgId = $this->pmsPackageIdForStorePackage($storePkg, $pmsPackages);
                if (! $pmsPkgId) {
                    continue;
                }
                $pmsPkg = $pmsPackages->firstWhere('id', $pmsPkgId);
                $methods[] = [
                    'package_id' => $pmsPkgId,
                    'unit_label' => $this->pmsPackageUnitLabel($pmsPkg, $storePkg),
                    'pieces_per_sell_unit' => $opt->pieces_per_sell_unit !== null
                        ? (float) $opt->pieces_per_sell_unit
                        : 1.0,
                    'unit_price' => $catalogPricesByPkg[$pmsPkgId]
                        ?? ($catalog && (int) ($catalog->selling_hotel_pms_service_package_id ?: 0) === $pmsPkgId
                            ? ($catalog->unit_price !== null ? (float) $catalog->unit_price : null)
                            : null),
                ];
            }
            if ($methods !== []) {
                return $methods;
            }
        }

        if ($catalog && $catalog->sellOptions->isNotEmpty()) {
            $methods = [];
            foreach ($catalog->sellOptions as $opt) {
                $pmsPkg = $opt->package;
                $name = trim((string) ($pmsPkg?->name ?? ''));
                $abbr = trim((string) ($pmsPkg?->abbreviation ?? ''));
                $unitLabel = $name;
                if ($name !== '' && $abbr !== '' && strcasecmp($name, $abbr) !== 0) {
                    $unitLabel = $name.' ('.$abbr.')';
                } elseif ($unitLabel === '') {
                    $unitLabel = $abbr !== '' ? $abbr : '—';
                }
                $methods[] = [
                    'package_id' => (int) $opt->hotel_pms_service_package_id,
                    'unit_label' => $unitLabel,
                    'pieces_per_sell_unit' => $opt->pieces_per_sell_unit !== null ? (float) $opt->pieces_per_sell_unit : 1.0,
                    'unit_price' => $opt->unit_price !== null ? (float) $opt->unit_price : null,
                ];
            }

            return $methods;
        }

        $recvPkgId = $this->marketReceivingPackageIdForLine($catalog, $master, $lineUnit, $pmsPackages);
        $fallbackPkgId = $recvPkgId > 0 ? $recvPkgId : null;
        if (! $fallbackPkgId) {
            foreach ($pmsPackages as $pkg) {
                $abbr = strtoupper(trim((string) ($pkg->abbreviation ?: '')));
                $name = strtoupper(trim((string) $pkg->name));
                $needle = strtoupper(trim($lineUnit));
                if ($needle !== '' && ($needle === $abbr || $needle === $name)) {
                    $fallbackPkgId = (int) $pkg->id;
                    break;
                }
            }
        }
        $fallbackPkg = $fallbackPkgId ? $pmsPackages->firstWhere('id', $fallbackPkgId) : null;
        $catalogSellRef = $catalog && $catalog->unit_price !== null ? (float) $catalog->unit_price : null;

        return [[
            'package_id' => (int) ($fallbackPkgId ?: 0),
            'unit_label' => $this->pmsPackageUnitLabel($fallbackPkg, null, $lineUnit ?: 'Sale unit'),
            'pieces_per_sell_unit' => 1.0,
            'unit_price' => $catalogSellRef,
        ]];
    }

    public function marketReceivingPackageIdForLine(
        ?HotelPmsServiceItem $catalog,
        ?HotelStoreMasterItem $master,
        string $lineUnit,
        Collection $pmsPackages,
    ): int {
        $master?->loadMissing('storePackage');
        if ($master?->storePackage) {
            $fromMaster = $this->pmsPackageIdForStorePackage($master->storePackage, $pmsPackages);
            if ($fromMaster) {
                return $fromMaster;
            }
        }

        if ($catalog && $catalog->hotel_pms_service_package_id) {
            return (int) $catalog->hotel_pms_service_package_id;
        }

        $needle = strtoupper(trim($lineUnit));
        if ($needle === '') {
            return 0;
        }
        foreach ($pmsPackages as $pkg) {
            $abbr = strtoupper(trim((string) ($pkg->abbreviation ?: '')));
            $name = strtoupper(trim((string) $pkg->name));
            if ($needle === $abbr || $needle === $name) {
                return (int) $pkg->id;
            }
        }

        return 0;
    }

    public function marketPrimarySellingPackageId(
        ?HotelPmsServiceItem $catalog,
        ?HotelStoreMasterItem $master,
        Collection $pmsPackages,
        array $sellMethods,
    ): int {
        if ($sellMethods !== []) {
            $sorted = $sellMethods;
            usort($sorted, fn (array $a, array $b) => ($a['pieces_per_sell_unit'] ?? 1) <=> ($b['pieces_per_sell_unit'] ?? 1));

            return (int) ($sorted[0]['package_id'] ?? 0);
        }

        $master?->loadMissing(['sellOptions.storePackage']);
        foreach ($master?->sellOptions ?? [] as $opt) {
            if ($storePkg = $opt->storePackage) {
                $pmsId = $this->pmsPackageIdForStorePackage($storePkg, $pmsPackages);
                if ($pmsId) {
                    return $pmsId;
                }
            }
        }

        if ($catalog?->selling_hotel_pms_service_package_id) {
            return (int) $catalog->selling_hotel_pms_service_package_id;
        }

        if ($catalog?->hotel_pms_service_package_id) {
            return (int) $catalog->hotel_pms_service_package_id;
        }

        return 0;
    }

    private function pmsPackageUnitLabel(?object $pmsPkg, ?HotelStorePackage $storePkg = null, string $fallback = '—'): string
    {
        if ($pmsPkg) {
            $name = trim((string) ($pmsPkg->name ?? ''));
            $abbr = trim((string) ($pmsPkg->abbreviation ?? ''));
            if ($name !== '' && $abbr !== '' && strcasecmp($name, $abbr) !== 0) {
                return $name.' ('.$abbr.')';
            }

            return $name !== '' ? $name : ($abbr !== '' ? $abbr : $fallback);
        }
        if ($storePkg) {
            return $storePkg->displayLabel();
        }

        return $fallback;
    }

    public function userIsBarAccountant(Hotel $hotel, User $user): bool
    {
        return $this->userIsProcurementAccountant($hotel, $user, 'bar');
    }

    public function userIsProcurementAccountant(Hotel $hotel, User $user, string $workflowKey): bool
    {
        if ($user->hasFullHotelAccess()) {
            return true;
        }

        $staffIds = collect($this->purchaseListSettings($hotel, $workflowKey)['accountant_staff_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0);

        return $staffIds->contains((int) $user->id);
    }

    /** @return Collection<int, HotelBarPurchaseList> */
    public function listsPendingAccounting(Hotel $hotel, int $limit = 50, ?string $workflowKey = null): Collection
    {
        $query = HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('recipient_status', HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED);
        if ($workflowKey) {
            $query->where('workflow_key', $workflowKey);
        }

        return $query
            ->where(function (Builder $q) {
                $q->where('accounting_status', HotelBarPurchaseList::ACCOUNTING_PENDING)
                    ->orWhereNull('accounting_status');
            })
            ->with(['creator', 'lines', 'department', 'vendor'])
            ->latest('budget_prepared_at')
            ->limit($limit)
            ->get();
    }

    public function pendingAccountingCount(Hotel $hotel): int
    {
        return (int) HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('recipient_status', HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
            ->where(function (Builder $q) {
                $q->where('accounting_status', HotelBarPurchaseList::ACCOUNTING_PENDING)
                    ->orWhereNull('accounting_status');
            })
            ->count();
    }

    /** @return Collection<int, HotelBarPurchaseList> */
    public function listsRejectedAccounting(Hotel $hotel, int $limit = 50, ?string $workflowKey = null): Collection
    {
        $query = HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('recipient_status', HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED)
            ->where('accounting_status', HotelBarPurchaseList::ACCOUNTING_REJECTED);
        if ($workflowKey) {
            $query->where('workflow_key', $workflowKey);
        }

        return $query
            ->with(['creator', 'lines', 'department'])
            ->latest('accounting_reviewed_at')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, HotelBarPurchaseList> */
    public function listsPendingMarketStockReceive(
        Hotel $hotel,
        bool $departmentLinesOnly = false,
        int $limit = 100,
        string $workflowKey = 'bar',
    ): Collection {
        $deptDest = DepartmentProcurement::departmentStockDestKey($workflowKey);

        $query = HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->where('market_status', HotelBarPurchaseList::MARKET_COMPLETED)
            ->whereNull('market_stock_posted_at')
            ->whereHas('lines', function (Builder $q) {
                $q->where(function (Builder $found) {
                    $found->whereNull('market_item_found')->orWhere('market_item_found', true);
                })->where('quantity_purchased', '>', 0);
            });

        if ($departmentLinesOnly) {
            $query->whereHas('lines', function (Builder $q) use ($deptDest) {
                $q->where('market_stock_destination', $deptDest)
                    ->where(function (Builder $found) {
                        $found->whereNull('market_item_found')->orWhere('market_item_found', true);
                    })
                    ->where('quantity_purchased', '>', 0);
            });
        }

        return $query
            ->with(['lines.receivingPackage', 'department', 'marketCompletedBy'])
            ->orderByDesc('market_completed_at')
            ->limit($limit)
            ->get();
    }

    public function pendingMarketStockReceiveCount(Hotel $hotel, bool $departmentLinesOnly = true, string $workflowKey = 'bar'): int
    {
        return $this->listsPendingMarketStockReceive($hotel, $departmentLinesOnly, 100, $workflowKey)->count();
    }

    /** @return Collection<int, HotelBarPurchaseList> */
    public function listsForMarket(Hotel $hotel, int $limit = 100, string $workflowKey = 'bar'): Collection
    {
        return HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->where('accounting_status', HotelBarPurchaseList::ACCOUNTING_APPROVED)
            ->whereIn('market_status', [
                HotelBarPurchaseList::MARKET_RELEASED,
                HotelBarPurchaseList::MARKET_COMPLETED,
            ])
            ->with(['creator', 'lines', 'department', 'purchaseOrder'])
            ->orderByRaw('CASE WHEN market_status = ? THEN 0 ELSE 1 END ASC', [HotelBarPurchaseList::MARKET_RELEASED])
            ->orderByDesc('market_released_at')
            ->limit($limit)
            ->get();
    }

    public function approveAccounting(
        HotelBarPurchaseList $list,
        User $user,
        ?string $notes = null,
        ?int $vendorId = null,
    ): HotelBarPurchaseList {
        if ($list->recipient_status !== HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED) {
            throw new InvalidArgumentException('Budget must be submitted before accountant approval.');
        }

        $status = $list->effectiveAccountingStatus();
        if ($status === HotelBarPurchaseList::ACCOUNTING_APPROVED) {
            return $list->load(['lines', 'creator', 'hotel', 'department', 'purchaseOrder']);
        }
        if ($status === HotelBarPurchaseList::ACCOUNTING_REJECTED) {
            throw new InvalidArgumentException('This budget was rejected. Ask procurement to revise and resubmit.');
        }

        return DB::transaction(function () use ($list, $user, $notes, $vendorId) {
            $list->update([
                'accounting_status' => HotelBarPurchaseList::ACCOUNTING_APPROVED,
                'accounting_notes' => trim((string) $notes) ?: null,
                'accounting_reviewed_at' => now(),
                'accounting_reviewed_by_user_id' => $user->id,
                'accounting_reviewed_by_name' => $user->name,
                'market_status' => HotelBarPurchaseList::MARKET_RELEASED,
                'market_released_at' => now(),
            ]);

            $list = $list->fresh(['lines', 'creator', 'hotel', 'department']);

            if (! $list->hotel_purchase_order_id) {
                $preferredVendor = $vendorId ?: (int) ($list->hotel_vendor_id ?? 0);
                $resolvedVendorId = $this->resolvePurchaseVendorId($list->hotel, $preferredVendor ?: null, $list->workflowKey());
                $this->accounts->createPurchaseOrderFromBarList($list->hotel, $list, $resolvedVendorId);
            }

            return $list->fresh(['lines', 'creator', 'hotel', 'department', 'purchaseOrder']);
        });
    }

    private function resolvePurchaseVendorId(Hotel $hotel, ?int $vendorId, string $workflowKey = 'bar'): int
    {
        if ($vendorId) {
            $exists = HotelVendor::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($vendorId)
                ->exists();
            if ($exists) {
                return $vendorId;
            }
        }

        $configured = (int) ($this->purchaseListSettings($hotel, $workflowKey)['default_vendor_id'] ?? 0);
        if ($configured > 0) {
            $exists = HotelVendor::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($configured)
                ->exists();
            if ($exists) {
                return $configured;
            }
        }

        return (int) $this->accounts->ensureDefaultMarketVendor($hotel)->id;
    }

    public function rejectAccounting(HotelBarPurchaseList $list, User $user, ?string $notes = null): HotelBarPurchaseList
    {
        if ($list->recipient_status !== HotelBarPurchaseList::RECIPIENT_BUDGET_PREPARED) {
            throw new InvalidArgumentException('Budget must be submitted before accountant review.');
        }

        if (trim((string) $notes) === '') {
            throw new InvalidArgumentException('Add a short reason when rejecting a budget.');
        }

        $list->update([
            'accounting_status' => HotelBarPurchaseList::ACCOUNTING_REJECTED,
            'accounting_notes' => trim($notes),
            'accounting_reviewed_at' => now(),
            'accounting_reviewed_by_user_id' => $user->id,
            'accounting_reviewed_by_name' => $user->name,
            'market_status' => HotelBarPurchaseList::MARKET_WAITING,
            'market_released_at' => null,
        ]);

        return $list->fresh(['lines', 'creator', 'hotel', 'department']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $lines
     */
    public function assertLinkedPurchaseOrderPaidForMarket(HotelBarPurchaseList $list): void
    {
        $list->loadMissing('purchaseOrder');
        if (! $list->purchaseOrder) {
            throw new InvalidArgumentException('No purchase order is linked to this list yet.');
        }
        if (! $list->purchaseOrder->isPaid()) {
            throw new InvalidArgumentException(
                'Pay '.$list->purchaseOrder->po_number.' in Accounts → Payables before receiving on Stores.'
            );
        }
    }

    public function saveMarketDraft(
        HotelBarPurchaseList $list,
        array $payload,
        array $lines,
    ): HotelBarPurchaseList {
        if ($list->market_status !== HotelBarPurchaseList::MARKET_RELEASED) {
            throw new InvalidArgumentException('This list is not open for market entry.');
        }

        $this->assertLinkedPurchaseOrderPaidForMarket($list);

        return DB::transaction(function () use ($list, $payload, $lines) {
            $this->applyMarketLineUpdates($list, $lines, applyCatalog: false);

            $budgetRaw = $payload['market_budget_amount'] ?? null;
            $list->update([
                'market_budget_amount' => ($budgetRaw !== null && $budgetRaw !== '')
                    ? round(max(0, (float) $budgetRaw), 2)
                    : null,
                'market_payment_type' => $payload['market_payment_type'] ?? $list->market_payment_type,
                'market_completion_notes' => trim((string) ($payload['market_completion_notes'] ?? '')) ?: null,
            ]);

            return $list->fresh(['lines', 'creator', 'hotel', 'department']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $lines
     */
    public function completeMarketRun(
        HotelBarPurchaseList $list,
        User $user,
        array $payload,
        array $lines,
    ): HotelBarPurchaseList {
        if ($list->market_status !== HotelBarPurchaseList::MARKET_RELEASED) {
            throw new InvalidArgumentException('This list is not approved for market yet.');
        }

        $this->assertLinkedPurchaseOrderPaidForMarket($list);

        $list->loadMissing('purchaseOrder.paymentExpense');
        $poPaidFromAccounts = $list->purchaseOrder?->isPaid() ?? false;

        $paymentType = trim((string) ($payload['market_payment_type'] ?? ''));
        if ($paymentType === '' && $poPaidFromAccounts) {
            $paymentType = (string) ($list->purchaseOrder?->paymentExpense?->payment_type ?? 'cash');
        }
        if ($paymentType === '') {
            throw new InvalidArgumentException('Choose how the market purchase was paid.');
        }

        $approvedBudget = $this->approvedMarketBudgetAmount($list, $payload);
        $actualSpend = $this->sumMarketActualFromLinePayload($lines);
        if ($approvedBudget > 0 && $actualSpend > $approvedBudget + 0.009) {
            $over = round($actualSpend - $approvedBudget, 2);
            throw new InvalidArgumentException(
                'Actual spend exceeds the approved budget by '.number_format($over, 2)
                .'. Reduce line totals or ask Accounts to revise the budget.'
            );
        }

        $list = DB::transaction(function () use ($list, $user, $payload, $lines, $paymentType, $approvedBudget) {
            $this->applyMarketLineUpdates($list, $lines, applyCatalog: true, applyStock: false);

            $list = $list->fresh(['lines', 'hotel', 'purchaseOrder']);
            $this->accounts->syncBarListPurchaseOrderToActualSpend($list->hotel, $list);

            $budgetRaw = $payload['market_budget_amount'] ?? null;
            $list->update([
                'market_status' => HotelBarPurchaseList::MARKET_COMPLETED,
                'market_completed_at' => now(),
                'market_completed_by_user_id' => $user->id,
                'market_payment_type' => $paymentType,
                'market_completion_notes' => trim((string) ($payload['market_completion_notes'] ?? '')) ?: null,
                'market_budget_amount' => $approvedBudget > 0
                    ? $approvedBudget
                    : (($budgetRaw !== null && $budgetRaw !== '')
                        ? round(max(0, (float) $budgetRaw), 2)
                        : $list->market_budget_amount),
                'market_stock_destination' => null,
                'market_stock_posted_at' => null,
                'market_stock_posted_by_user_id' => null,
            ]);

            return $list->fresh(['lines', 'creator', 'hotel', 'department', 'purchaseOrder']);
        });

        if ($list->isMarketStockPending()) {
            try {
                $list = $this->postMarketStock($list, $user);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(
                    $e->getMessage().' Open Receive stock for this list to finish updating inventory.'
                );
            }
        }

        $list = $this->accounts->postBarListMarketBudgetReturn($list->hotel, $list, $actualSpend);

        return $list->fresh(['lines', 'creator', 'hotel', 'department', 'purchaseOrder', 'marketStockPostedBy', 'marketBudgetReturnExpense']);
    }

    public function postMarketStock(HotelBarPurchaseList $list, User $user): HotelBarPurchaseList
    {
        if ($list->market_status !== HotelBarPurchaseList::MARKET_COMPLETED) {
            throw new InvalidArgumentException('This purchase is not completed yet.');
        }

        if ($list->market_stock_posted_at) {
            throw new InvalidArgumentException('Stock for this purchase was already received.');
        }

        return DB::transaction(function () use ($list, $user) {
            $list->load(['lines', 'hotel']);
            $catalogByName = $this->catalogItemsByItemName($list->hotel, $list->workflowKey());
            $allowedDest = DepartmentProcurement::allowedStockDestinationKeys($list->workflowKey());
            $deptDest = DepartmentProcurement::departmentStockDestKey($list->workflowKey());

            foreach ($list->lines as $line) {
                $found = $line->market_item_found !== false;
                $qtyPurchased = $found ? max(0, (float) ($line->quantity_purchased ?? 0)) : 0.0;
                if ($qtyPurchased <= 0) {
                    continue;
                }

                $destination = $line->market_stock_destination ?: $list->market_stock_destination;
                if (! in_array($destination, $allowedDest, true)) {
                    throw new InvalidArgumentException('Set stock destination for each purchased item before receiving stock.');
                }

                $catalogKey = strtolower(trim($line->item_name));
                $catalogItem = $catalogByName[$catalogKey] ?? null;
                if (! $catalogItem) {
                    continue;
                }

                if ($destination === HotelBarPurchaseList::STOCK_DEST_STORE) {
                    $current = $catalogItem->store_stock_on_hand !== null ? (float) $catalogItem->store_stock_on_hand : 0.0;
                    $catalogItem->update([
                        'store_stock_on_hand' => round($current + $qtyPurchased, 2),
                    ]);
                } elseif (in_array($destination, [HotelBarPurchaseList::STOCK_DEST_BAR, HotelBarPurchaseList::STOCK_DEST_DEPARTMENT, $deptDest], true)) {
                    $current = $catalogItem->stock_on_hand !== null ? (float) $catalogItem->stock_on_hand : 0.0;
                    $catalogItem->update([
                        'stock_on_hand' => round($current + $qtyPurchased, 2),
                    ]);
                }
            }

            $list->update([
                'market_stock_posted_at' => now(),
                'market_stock_posted_by_user_id' => $user->id,
            ]);

            return $list->fresh(['lines', 'creator', 'hotel', 'department', 'marketStockPostedBy']);
        });
    }

    /** @param  array<string, mixed>  $payload */
    public function approvedMarketBudgetAmount(HotelBarPurchaseList $list, array $payload = []): float
    {
        $raw = $payload['market_budget_amount'] ?? null;
        if ($raw !== null && $raw !== '') {
            return round(max(0, (float) $raw), 2);
        }
        if ($list->market_budget_amount !== null) {
            return round(max(0, (float) $list->market_budget_amount), 2);
        }
        if ($list->budget_total !== null) {
            return round(max(0, (float) $list->budget_total), 2);
        }

        return 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function sumMarketActualFromLinePayload(array $lines): float
    {
        $total = 0.0;
        foreach ($lines as $row) {
            $found = filter_var($row['market_item_found'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if (! $found) {
                continue;
            }
            if (($row['purchase_line_total'] ?? '') === '') {
                continue;
            }
            $total += max(0, (float) $row['purchase_line_total']);
        }

        return round($total, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function applyMarketLineUpdates(
        HotelBarPurchaseList $list,
        array $lines,
        bool $applyCatalog,
        bool $applyStock = false,
    ): void {
        $list->load(['lines', 'hotel']);
        $catalogByName = $this->catalogItemsByItemName($list->hotel, $list->workflowKey());
        $allowedDest = DepartmentProcurement::allowedStockDestinationKeys($list->workflowKey());

        foreach ($lines as $row) {
            $lineId = (int) ($row['id'] ?? 0);
            $line = $list->lines->firstWhere('id', $lineId);
            if (! $line) {
                continue;
            }

            $found = filter_var($row['market_item_found'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $qtyPurchased = $found ? max(0, (float) ($row['quantity_purchased'] ?? 0)) : 0.0;

            $lineTotal = ($row['purchase_line_total'] ?? '') !== ''
                ? max(0, (float) $row['purchase_line_total'])
                : null;

            $purchasePrice = null;
            if ($lineTotal !== null && $qtyPurchased > 0) {
                $purchasePrice = round($lineTotal / $qtyPurchased, 2);
            } elseif (($row['purchase_unit_price'] ?? '') !== '') {
                $purchasePrice = max(0, (float) $row['purchase_unit_price']);
                if ($lineTotal === null && $qtyPurchased > 0) {
                    $lineTotal = round($qtyPurchased * $purchasePrice, 2);
                }
            }

            $sellOptionPricesRaw = $row['sell_option_prices'] ?? null;
            $sellOptionPrices = $this->normalizeMarketSellOptionPrices($sellOptionPricesRaw);

            $sellingPrice = ($row['selling_unit_price'] ?? '') !== ''
                ? max(0, (float) $row['selling_unit_price'])
                : null;
            $lineNotes = trim((string) ($row['market_line_notes'] ?? '')) ?: null;
            $expiry = trim((string) ($row['expiry_date'] ?? '')) ?: null;
            $receivingPackageId = ($row['receiving_package_id'] ?? '') !== ''
                ? (int) $row['receiving_package_id']
                : null;
            $sellingPackageId = ($row['selling_package_id'] ?? '') !== ''
                ? (int) $row['selling_package_id']
                : null;

            $lineUnit = $line->unit;
            if ($receivingPackageId) {
                $recvPackage = \App\Models\HotelPmsServicePackage::query()->find($receivingPackageId);
                if ($recvPackage) {
                    $lineUnit = trim((string) ($recvPackage->abbreviation ?: $recvPackage->name)) ?: $lineUnit;
                }
            }

            if ($sellOptionPrices !== [] && $catalogItem = ($catalogByName[strtolower(trim($line->item_name))] ?? null)) {
                $catalogItem->loadMissing('sellOptions');
                if ($catalogItem->sellOptions->count() > 1) {
                    $primary = $this->primarySellOptionFromPrices($catalogItem, $sellOptionPrices);
                    if ($primary !== null) {
                        $sellingPackageId = $primary['package_id'];
                        $sellingPrice = $primary['unit_price'];
                    }
                }
            }

            $stockDestRaw = trim((string) ($row['market_stock_destination'] ?? ''));
            $stockDest = in_array($stockDestRaw, $allowedDest, true) ? $stockDestRaw : null;
            if ($applyCatalog && $found && $qtyPurchased > 0 && ! $stockDest) {
                throw new InvalidArgumentException('Choose stock destination for each purchased item ('.$line->item_name.').');
            }

            $line->update([
                'market_item_found' => $found,
                'quantity_purchased' => $qtyPurchased,
                'purchase_unit_price' => $purchasePrice,
                'purchase_line_total' => $lineTotal,
                'selling_unit_price' => $sellingPrice,
                'market_line_notes' => $lineNotes,
                'expiry_date' => $expiry,
                'receiving_package_id' => $receivingPackageId,
                'selling_package_id' => $sellingPackageId,
                'market_sell_option_prices' => $sellOptionPrices !== [] ? $sellOptionPrices : null,
                'market_stock_destination' => $stockDest ?? $line->market_stock_destination,
                'unit' => $lineUnit,
            ]);

            if (! $applyCatalog || ! $found || $qtyPurchased <= 0) {
                continue;
            }

            $catalogKey = strtolower(trim($line->item_name));
            $catalogItem = $catalogByName[$catalogKey] ?? null;
            if (! $catalogItem) {
                continue;
            }

            $catalogUpdates = [];
            if ($purchasePrice !== null) {
                $catalogUpdates['expected_unit_price'] = $purchasePrice;
            }
            if ($sellingPrice !== null) {
                $catalogUpdates['unit_price'] = $sellingPrice;
            }
            if ($receivingPackageId) {
                $catalogUpdates['hotel_pms_service_package_id'] = $receivingPackageId;
            }
            if ($sellingPackageId) {
                $catalogUpdates['selling_hotel_pms_service_package_id'] = $sellingPackageId;
            }
            if ($applyStock) {
                $currentStock = $catalogItem->stock_on_hand !== null ? (float) $catalogItem->stock_on_hand : 0.0;
                $catalogUpdates['stock_on_hand'] = round($currentStock + $qtyPurchased, 2);
            }
            if ($catalogUpdates !== []) {
                $catalogItem->update($catalogUpdates);
            }

            if ($sellOptionPrices !== []) {
                $catalogItem->loadMissing('sellOptions');
                foreach ($catalogItem->sellOptions as $sellOption) {
                    $pkgId = (int) $sellOption->hotel_pms_service_package_id;
                    if (! array_key_exists($pkgId, $sellOptionPrices)) {
                        continue;
                    }
                    $sellOption->update(['unit_price' => $sellOptionPrices[$pkgId]]);
                }
            }
        }
    }

    /**
     * @return array<int, float>
     */
    private function normalizeMarketSellOptionPrices(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $packageId => $price) {
            if ($price === '' || $price === null) {
                continue;
            }
            $out[(int) $packageId] = round(max(0, (float) $price), 2);
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $sellOptionPrices
     * @return array{package_id: int, unit_price: float}|null
     */
    private function primarySellOptionFromPrices(HotelPmsServiceItem $catalogItem, array $sellOptionPrices): ?array
    {
        $catalogItem->loadMissing('sellOptions');
        $best = null;
        $bestPieces = null;
        foreach ($catalogItem->sellOptions as $option) {
            $pkgId = (int) $option->hotel_pms_service_package_id;
            if (! array_key_exists($pkgId, $sellOptionPrices)) {
                continue;
            }
            $pieces = $option->pieces_per_sell_unit !== null ? (float) $option->pieces_per_sell_unit : 1.0;
            if ($best === null || $pieces < $bestPieces) {
                $bestPieces = $pieces;
                $best = ['package_id' => $pkgId, 'unit_price' => $sellOptionPrices[$pkgId]];
            }
        }

        return $best;
    }

    private function nextReference(Hotel $hotel, string $workflowKey = 'bar'): string
    {
        $date = now()->format('Ymd');
        $refPrefix = DepartmentProcurement::workflow($workflowKey)['reference_prefix'] ?? 'PL';
        $prefix = $refPrefix.'-'.$date.'-';
        $last = HotelBarPurchaseList::query()
            ->where('hotel_id', $hotel->id)
            ->where('workflow_key', $workflowKey)
            ->where('reference_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
