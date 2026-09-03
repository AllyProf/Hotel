<?php

namespace App\Services\ChannelManager;

use App\Models\Hotel;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomInventory;
use App\Services\HotelSettingsService;
use App\Services\OtaConnectionService;
use App\Services\RateInventoryService;
use App\Services\RoomInventoryService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class ChannelManagerPushService
{
    public function __construct(
        private ChannelManagerClient $client,
        private ChannelManagerCodeResolver $codes,
        private ChannelManagerPropertyService $propertyService,
        private RoomInventoryService $inventoryService,
        private RateInventoryService $rateInventory,
        private HotelSettingsService $settingsService,
        private OtaConnectionService $otaConnections,
    ) {}

    public function canPush(): bool
    {
        return $this->client->isConfigured();
    }

    /** @return array{attempted: bool, inventory: array<string, mixed>|null, rates: array<string, mixed>|null} */
    public function pushAfterInventorySave(Hotel $hotel, Carbon $startDate): array
    {
        if (! $this->canPush()) {
            return ['attempted' => false, 'inventory' => null, 'rates' => null];
        }

        if (! $this->prepareSandboxMapping($hotel)) {
            return [
                'attempted' => true,
                'inventory' => $this->mappingFailureResult(),
                'rates' => null,
            ];
        }

        $endDate = $startDate->copy()->addDays(RoomInventoryService::WINDOW_DAYS - 1);

        return [
            'attempted' => true,
            'inventory' => $this->pushInventory($hotel, $startDate, $endDate, skipMapping: true),
            'rates' => null,
        ];
    }

    /** @return array{attempted: bool, inventory: array<string, mixed>|null, rates: array<string, mixed>|null} */
    public function pushBulk(Hotel $hotel, Carbon $from, Carbon $to, bool $pushInventory, bool $pushRates): array
    {
        if (! $this->canPush()) {
            return ['attempted' => false, 'inventory' => null, 'rates' => null];
        }

        if (! $this->prepareSandboxMapping($hotel)) {
            return [
                'attempted' => true,
                'inventory' => $pushInventory ? $this->mappingFailureResult() : null,
                'rates' => $pushRates ? $this->mappingFailureResult() : null,
            ];
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $inventoryResult = null;
        $ratesResult = null;
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $chunkEnd = $cursor->copy()->addDays(RoomInventoryService::WINDOW_DAYS - 1);
            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy();
            }

            if ($pushInventory) {
                $chunkInventory = $this->pushInventory($hotel, $cursor, $chunkEnd, skipMapping: true);
                $inventoryResult = $this->mergePushResults($inventoryResult, $chunkInventory);
            }

            if ($pushRates) {
                $chunkRates = $this->pushRates($hotel, $cursor, $chunkEnd, skipMapping: true);
                $ratesResult = $this->mergePushResults($ratesResult, $chunkRates);
            }

            $cursor = $chunkEnd->copy()->addDay();
        }

        return [
            'attempted' => true,
            'inventory' => $inventoryResult,
            'rates' => $ratesResult,
        ];
    }

    /** @param array{success: bool, http_code: int|null, message: string, response: mixed}|null $current @param array{success: bool, http_code: int|null, message: string, response: mixed} $next @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    private function mergePushResults(?array $current, array $next): array
    {
        if ($current === null) {
            return $next;
        }

        if (($current['success'] ?? false) && ($next['success'] ?? false)) {
            return $current;
        }

        return [
            'success' => false,
            'http_code' => $next['http_code'] ?? $current['http_code'],
            'message' => trim(($current['success'] ?? false ? '' : ($current['message'] ?? '')).' '.($next['message'] ?? '')),
            'response' => $next['response'] ?? $current['response'],
        ];
    }

    /** @return array{attempted: bool, inventory: array<string, mixed>|null, rates: array<string, mixed>|null} */
    public function pushAfterRateSave(Hotel $hotel, ?Carbon $startDate = null): array
    {
        if (! $this->canPush()) {
            return ['attempted' => false, 'inventory' => null, 'rates' => null];
        }

        if (! $this->prepareSandboxMapping($hotel)) {
            return [
                'attempted' => true,
                'inventory' => null,
                'rates' => $this->mappingFailureResult(),
            ];
        }

        $start = ($startDate ?? now())->copy()->startOfDay();
        $end = $start->copy()->addDays(RoomInventoryService::WINDOW_DAYS - 1);

        return [
            'attempted' => true,
            'inventory' => null,
            'rates' => $this->pushRates($hotel, $start, $end, skipMapping: true),
        ];
    }

    /** @return array{attempted: bool, inventory: array<string, mixed>|null, rates: array<string, mixed>|null} */
    public function pushAfterRoomSave(Hotel $hotel, Carbon $startDate): array
    {
        if (! $this->canPush()) {
            return ['attempted' => false, 'inventory' => null, 'rates' => null];
        }

        if (! $this->prepareSandboxMapping($hotel)) {
            return [
                'attempted' => true,
                'inventory' => $this->mappingFailureResult(),
                'rates' => $this->mappingFailureResult(),
            ];
        }

        $endDate = $startDate->copy()->addDays(RoomInventoryService::WINDOW_DAYS - 1);

        return [
            'attempted' => true,
            'inventory' => $this->pushInventory($hotel, $startDate, $endDate, skipMapping: true),
            'rates' => $this->pushRates($hotel, $startDate, $endDate, skipMapping: true),
        ];
    }

    public function flashSuffix(array $result): string
    {
        if (! ($result['attempted'] ?? false)) {
            return '';
        }

        $parts = [];

        if ($result['inventory'] !== null) {
            $parts[] = ($result['inventory']['success'] ?? false)
                ? 'Inventory synced to Channel Manager.'
                : 'Inventory sync failed: '.$this->friendlyMessage((string) ($result['inventory']['message'] ?? ''));
        }

        if ($result['rates'] !== null) {
            $parts[] = ($result['rates']['success'] ?? false)
                ? 'Rates synced to Channel Manager.'
                : 'Rate sync failed: '.$this->friendlyMessage((string) ($result['rates']['message'] ?? ''));
        }

        if ($result['inventory_restrictions'] ?? null) {
            $parts[] = ($result['inventory_restrictions']['success'] ?? false)
                ? 'Inventory restrictions synced to Channel Manager.'
                : 'Inventory restriction sync failed: '.$this->friendlyMessage((string) ($result['inventory_restrictions']['message'] ?? ''));
        }

        if ($result['rate_restrictions'] ?? null) {
            $parts[] = ($result['rate_restrictions']['success'] ?? false)
                ? 'Rate restrictions synced to Channel Manager.'
                : 'Rate restriction sync failed: '.$this->friendlyMessage((string) ($result['rate_restrictions']['message'] ?? ''));
        }

        if ($parts === []) {
            return '';
        }

        return ' '.implode(' ', $parts);
    }

    /** @return array{flash_key: string, message: string} */
    public function flashForSaveResult(string $baseMessage, array $result): array
    {
        if (! ($result['attempted'] ?? false)) {
            return ['flash_key' => 'success', 'message' => $baseMessage];
        }

        $inventoryOk = $result['inventory'] === null || ($result['inventory']['success'] ?? false);
        $ratesOk = $result['rates'] === null || ($result['rates']['success'] ?? false);
        $inventoryRestrictionsOk = ($result['inventory_restrictions'] ?? null) === null || ($result['inventory_restrictions']['success'] ?? false);
        $rateRestrictionsOk = ($result['rate_restrictions'] ?? null) === null || ($result['rate_restrictions']['success'] ?? false);

        if ($inventoryOk && $ratesOk && $inventoryRestrictionsOk && $rateRestrictionsOk) {
            $note = trim($this->flashSuffix($result));

            return [
                'flash_key' => 'success',
                'message' => $note !== '' ? $baseMessage.$note : $baseMessage.' Synced to Channel Manager.',
            ];
        }

        $note = trim($this->flashSuffix($result));

        return [
            'flash_key' => 'warning',
            'message' => $note !== '' ? $baseMessage.$note : $baseMessage.' Channel Manager sync could not be completed.',
        ];
    }

    private function friendlyMessage(string $message): string
    {
        $lower = strtolower($message);

        if ($message === '' || str_contains($lower, 'timed out') || str_contains($lower, 'timeout') || str_contains($lower, 'ssl connection')) {
            return 'Channel Manager did not respond in time. Your changes were saved.';
        }

        return $message;
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function pushInventory(Hotel $hotel, Carbon $startDate, Carbon $endDate, bool $skipMapping = false): array
    {
        if (! $skipMapping && ! $this->prepareSandboxMapping($hotel)) {
            return $this->mappingFailureResult();
        }

        $hotel->load(['rooms' => fn ($q) => $q->where('is_enabled', true)->orderBy('rank')->orderBy('id')]);

        if ($hotel->rooms->isEmpty()) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'No active room types to push.',
                'response' => null,
            ];
        }

        $dateKeys = collect(CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()))
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->values()
            ->all();

        if ($dateKeys === []) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'No dates in range to push.',
                'response' => null,
            ];
        }

        $roomIds = $hotel->rooms->pluck('id')->all();

        $inventory = HotelRoomInventory::query()
            ->where('hotel_id', $hotel->id)
            ->whereIn('hotel_room_id', $roomIds)
            ->whereBetween('date', [$dateKeys[0], end($dateKeys)])
            ->get()
            ->groupBy('hotel_room_id');

        $dailyPayloads = [];

        foreach ($dateKeys as $dateKey) {
            $roomsPayload = [];

            foreach ($hotel->rooms as $room) {
                $roomInventory = $inventory->get($room->id, collect())
                    ->keyBy(fn (HotelRoomInventory $row) => $row->date->format('Y-m-d'));

                $available = isset($roomInventory[$dateKey])
                    ? (int) $roomInventory[$dateKey]->available_count
                    : (int) $room->room_count;

                $roomsPayload[] = [
                    'available' => $available,
                    'roomCode' => $this->codes->roomCode($hotel, $room),
                ];
            }

            usort($roomsPayload, fn ($a, $b) => strcmp($a['roomCode'], $b['roomCode']));
            $dailyPayloads[$dateKey] = $roomsPayload;
        }

        $updates = $this->mergeInventoryUpdates($dailyPayloads);

        $payload = [
            'hotelCode' => $this->codes->hotelCode($hotel),
            'updates' => $updates,
        ];

        $result = $this->client->pushInventory($this->codes->hotelCode($hotel), $payload);

        if (! $result['success']) {
            Log::warning('Channel Manager inventory push failed', [
                'hotel_id' => $hotel->id,
                'message' => $result['message'],
                'http_code' => $result['http_code'],
            ]);
        }

        return $result;
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    public function pushRates(Hotel $hotel, Carbon $startDate, Carbon $endDate, bool $skipMapping = false): array
    {
        if (! $skipMapping && ! $this->prepareSandboxMapping($hotel)) {
            return $this->mappingFailureResult();
        }

        $plans = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->with('room')
            ->get()
            ->filter(fn (HotelRatePlan $plan) => $plan->room !== null);

        if ($plans->isEmpty()) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'No rate plans to push.',
                'response' => null,
            ];
        }

        $dateKeys = collect(CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()))
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->values()
            ->all();

        if ($dateKeys === []) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'No dates in range to push.',
                'response' => null,
            ];
        }

        $dailyPayloads = [];

        foreach ($dateKeys as $dateKey) {
            $rates = [];

            foreach ($plans as $plan) {
                $rate = $this->rateInventory->cmRateForPlan($plan, $dateKey);

                if ($rate <= 0) {
                    continue;
                }

                $rates[] = [
                    'roomCode' => $this->codes->roomCode($hotel, $plan->room),
                    'rate' => $rate,
                    'rateplanCode' => $this->codes->rateplanCode($hotel, $plan),
                ];
            }

            usort($rates, fn ($a, $b) => strcmp($a['rateplanCode'], $b['rateplanCode']));
            $dailyPayloads[$dateKey] = $rates;
        }

        $updates = $this->mergeRateUpdates($dailyPayloads);

        if ($updates === []) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'No rates with a price greater than zero.',
                'response' => null,
            ];
        }

        $payload = [
            'hotelCode' => $this->codes->hotelCode($hotel),
            'updates' => $updates,
        ];

        $result = $this->client->pushRates($this->codes->hotelCode($hotel), $payload);

        if (! $result['success']) {
            Log::warning('Channel Manager rate push failed', [
                'hotel_id' => $hotel->id,
                'message' => $result['message'],
                'http_code' => $result['http_code'],
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, list<array{available: int, roomCode: string}>>  $dailyPayloads
     * @return list<array{startDate: string, endDate: string, rooms: list<array{available: int, roomCode: string}>}>
     */
    private function mergeInventoryUpdates(array $dailyPayloads): array
    {
        if ($dailyPayloads === []) {
            return [];
        }

        $dates = array_keys($dailyPayloads);
        sort($dates);

        $updates = [];
        $rangeStart = $dates[0];
        $rangeEnd = $dates[0];
        $rangeSignature = json_encode($dailyPayloads[$dates[0]]);

        for ($index = 1; $index < count($dates); $index++) {
            $dateKey = $dates[$index];
            $signature = json_encode($dailyPayloads[$dateKey]);

            if ($signature === $rangeSignature) {
                $rangeEnd = $dateKey;

                continue;
            }

            $updates[] = [
                'startDate' => $rangeStart,
                'endDate' => $rangeEnd,
                'rooms' => $dailyPayloads[$rangeStart],
            ];

            $rangeStart = $dateKey;
            $rangeEnd = $dateKey;
            $rangeSignature = $signature;
        }

        $updates[] = [
            'startDate' => $rangeStart,
            'endDate' => $rangeEnd,
            'rooms' => $dailyPayloads[$rangeStart],
        ];

        return $updates;
    }

    /**
     * @param  array<string, list<array{roomCode: string, rate: float|int, rateplanCode: string}>>  $dailyPayloads
     * @return list<array{startDate: string, endDate: string, rates: list<array{roomCode: string, rate: float|int, rateplanCode: string}>}>
     */
    private function mergeRateUpdates(array $dailyPayloads): array
    {
        if ($dailyPayloads === []) {
            return [];
        }

        $dates = array_keys($dailyPayloads);
        sort($dates);

        $updates = [];
        $rangeStart = $dates[0];
        $rangeEnd = $dates[0];
        $rangeSignature = json_encode($dailyPayloads[$dates[0]]);

        for ($index = 1; $index < count($dates); $index++) {
            $dateKey = $dates[$index];
            $signature = json_encode($dailyPayloads[$dateKey]);

            if ($signature === $rangeSignature && $dailyPayloads[$dateKey] !== []) {
                $rangeEnd = $dateKey;

                continue;
            }

            if ($dailyPayloads[$rangeStart] !== []) {
                $updates[] = [
                    'startDate' => $rangeStart,
                    'endDate' => $rangeEnd,
                    'rates' => $dailyPayloads[$rangeStart],
                ];
            }

            $rangeStart = $dateKey;
            $rangeEnd = $dateKey;
            $rangeSignature = $signature;
        }

        if ($dailyPayloads[$rangeStart] !== []) {
            $updates[] = [
                'startDate' => $rangeStart,
                'endDate' => $rangeEnd,
                'rates' => $dailyPayloads[$rangeStart],
            ];
        }

        return $updates;
    }

    /**
     * @param  list<string>  $channels
     * @return array{success: bool, http_code: int|null, message: string, response: mixed}
     */
    public function pushInventoryRestrictions(Hotel $hotel, Carbon $startDate, Carbon $endDate, array $channels = ['all'], bool $skipMapping = false): array
    {
        if (! $skipMapping && ! $this->prepareSandboxMapping($hotel)) {
            return $this->mappingFailureResult();
        }

        $settings = $this->settingsService->ensureDefaults($hotel);
        $rateplan = is_array($settings->rateplan) ? $settings->rateplan : [];
        $stored = is_array($rateplan['inventory_restrictions'] ?? null) ? $rateplan['inventory_restrictions'] : [];
        $dateKeys = collect(CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()))
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->values()
            ->all();

        $hotel->load(['rooms' => fn ($q) => $q->where('is_enabled', true)->orderBy('rank')->orderBy('id')]);
        $dailyPayloads = [];

        foreach ($dateKeys as $dateKey) {
            $roomsPayload = [];

            foreach ($hotel->rooms as $room) {
                $restrictionRow = $stored[(string) $room->id][$dateKey] ?? null;

                if (! is_array($restrictionRow)) {
                    continue;
                }

                $roomsPayload[] = [
                    'roomCode' => $this->codes->roomCode($hotel, $room),
                    'restrictions' => AiosellPayloadBuilder::fromInternalRestriction($restrictionRow),
                ];
            }

            if ($roomsPayload !== []) {
                usort($roomsPayload, fn ($a, $b) => strcmp($a['roomCode'], $b['roomCode']));
                $dailyPayloads[$dateKey] = $roomsPayload;
            }
        }

        if ($dailyPayloads === []) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'No inventory restrictions to push for the selected range.',
                'response' => null,
            ];
        }

        $payload = [
            'hotelCode' => $this->codes->hotelCode($hotel),
            'toChannels' => AiosellPayloadBuilder::toAiosellChannels($channels),
            'updates' => $this->mergeRestrictionUpdates($dailyPayloads, 'rooms'),
        ];

        return $this->client->pushInventoryRestrictions($payload);
    }

    /**
     * @param  list<string>  $channels
     * @return array{success: bool, http_code: int|null, message: string, response: mixed}
     */
    public function pushRateRestrictions(Hotel $hotel, Carbon $startDate, Carbon $endDate, array $channels = ['all'], bool $skipMapping = false): array
    {
        if (! $skipMapping && ! $this->prepareSandboxMapping($hotel)) {
            return $this->mappingFailureResult();
        }

        $settings = $this->settingsService->ensureDefaults($hotel);
        $rateplan = is_array($settings->rateplan) ? $settings->rateplan : [];
        $stored = is_array($rateplan['rate_restrictions'] ?? null) ? $rateplan['rate_restrictions'] : [];
        $dateKeys = collect(CarbonPeriod::create($startDate->copy()->startOfDay(), $endDate->copy()->startOfDay()))
            ->map(fn (Carbon $d) => $d->format('Y-m-d'))
            ->values()
            ->all();

        $plans = HotelRatePlan::query()
            ->where('hotel_id', $hotel->id)
            ->with('room')
            ->get()
            ->filter(fn (HotelRatePlan $plan) => $plan->room !== null);

        $dailyPayloads = [];

        foreach ($dateKeys as $dateKey) {
            $ratesPayload = [];

            foreach ($plans as $plan) {
                $restrictionRow = $stored[(string) $plan->id][$dateKey] ?? null;

                if (! is_array($restrictionRow)) {
                    continue;
                }

                $ratesPayload[] = [
                    'roomCode' => $this->codes->roomCode($hotel, $plan->room),
                    'rateplanCode' => $this->codes->rateplanCode($hotel, $plan),
                    'restrictions' => AiosellPayloadBuilder::fromInternalRestriction($restrictionRow),
                ];
            }

            if ($ratesPayload !== []) {
                usort($ratesPayload, fn ($a, $b) => strcmp($a['rateplanCode'], $b['rateplanCode']));
                $dailyPayloads[$dateKey] = $ratesPayload;
            }
        }

        if ($dailyPayloads === []) {
            return [
                'success' => false,
                'http_code' => null,
                'message' => 'No rate restrictions to push for the selected range.',
                'response' => null,
            ];
        }

        $payload = [
            'hotelCode' => $this->codes->hotelCode($hotel),
            'toChannels' => AiosellPayloadBuilder::toAiosellChannels($channels),
            'updates' => $this->mergeRestrictionUpdates($dailyPayloads, 'rates'),
        ];

        return $this->client->pushRateRestrictions($payload);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $dailyPayloads
     * @return list<array<string, mixed>>
     */
    private function mergeRestrictionUpdates(array $dailyPayloads, string $key): array
    {
        $dates = array_keys($dailyPayloads);
        sort($dates);

        if ($dates === []) {
            return [];
        }

        $updates = [];
        $rangeStart = $dates[0];
        $rangeEnd = $dates[0];
        $signature = json_encode($dailyPayloads[$dates[0]]);

        for ($index = 1; $index < count($dates); $index++) {
            $dateKey = $dates[$index];
            $nextSignature = json_encode($dailyPayloads[$dateKey]);

            if ($nextSignature === $signature) {
                $rangeEnd = $dateKey;

                continue;
            }

            $updates[] = [
                'startDate' => $rangeStart,
                'endDate' => $rangeEnd,
                $key => $dailyPayloads[$rangeStart],
            ];

            $rangeStart = $dateKey;
            $rangeEnd = $dateKey;
            $signature = $nextSignature;
        }

        $updates[] = [
            'startDate' => $rangeStart,
            'endDate' => $rangeEnd,
            $key => $dailyPayloads[$rangeStart],
        ];

        return $updates;
    }

    /** @return array{success: bool, message: string} */
    public function pushOtaRateMultiplier(Hotel $hotel, string $slug, float $multiplier): array
    {
        if (! $this->canPush()) {
            return [
                'success' => false,
                'message' => 'Channel Manager is not configured.',
            ];
        }

        $result = $this->client->channelMultiplier(
            $this->codes->hotelCode($hotel),
            max(0.1, $multiplier),
            [AiosellPayloadBuilder::toAiosellChannel($slug)]
        );

        return [
            'success' => (bool) $result['success'],
            'message' => $result['message'] ?: ($result['success'] ? 'Rate multiplier pushed.' : 'Could not push rate multiplier.'),
        ];
    }

    /**
     * @return array{
     *     attempted: bool,
     *     success: bool,
     *     message: string,
     *     details: list<array{slug: string, name: string, multiplier: float, success: bool, message: string}>
     * }
     */
    public function pushAllOtaRateMultipliers(Hotel $hotel): array
    {
        $configured = $this->otaConnections->configured($hotel);

        if ($configured === []) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'No connected OTAs to sync.',
                'details' => [],
            ];
        }

        if (! $this->canPush()) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'Channel Manager is not configured.',
                'details' => [],
            ];
        }

        $details = [];
        $failures = 0;

        foreach ($configured as $ota) {
            $slug = (string) ($ota['slug'] ?? '');
            $connection = $this->otaConnections->connection($hotel, $slug);
            $multiplier = max(0.1, (float) ($connection['rate_multiplier'] ?? 1));
            $push = $this->pushOtaRateMultiplier($hotel, $slug, $multiplier);

            if (! $push['success']) {
                $failures++;
            }

            $details[] = [
                'slug' => $slug,
                'name' => (string) ($ota['name'] ?? $slug),
                'multiplier' => $multiplier,
                'success' => $push['success'],
                'message' => $push['message'],
            ];
        }

        $total = count($details);
        $successes = $total - $failures;

        return [
            'attempted' => true,
            'success' => $failures === 0,
            'message' => $failures === 0
                ? "Rate multipliers pushed to {$total} OTA".($total === 1 ? '' : 's').'.'
                : "Pushed {$successes} of {$total} rate multipliers. {$failures} failed.",
            'details' => $details,
        ];
    }

    /** @return array{success: bool, http_code: int|null, message: string, response: mixed} */
    private function mappingFailureResult(): array
    {
        return [
            'success' => false,
            'http_code' => null,
            'message' => 'Could not load sandbox property mapping from Channel Manager.',
            'response' => null,
        ];
    }

    private function prepareSandboxMapping(Hotel $hotel): bool
    {
        if (! $this->propertyService->isSandbox()) {
            return true;
        }

        return $this->propertyService->ensureHotelMapping($hotel);
    }
}
