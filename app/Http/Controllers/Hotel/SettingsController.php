<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelPmsCategory;
use App\Models\HotelPmsService;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Services\ChannelManager\ChannelManagerPushService;
use App\Services\HotelAmenityService;
use App\Services\HotelSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private HotelSettingsService $settingsService,
        private HotelAmenityService $amenityService,
        private ChannelManagerPushService $cmPush,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()
            ->with(['rooms.ratePlans', 'pmsServices', 'pmsCategories', 'settings', 'plan'])
            ->firstOrFail();

        $settings = $this->settingsService->ensureDefaults($hotel);
        $tabs = $this->settingsService->tabs();
        $tab = $request->query('tab', 'hotel');

        if (! array_key_exists($tab, $tabs)) {
            $tab = 'hotel';
        }

        $amenities = $this->amenityService->allForHotel($hotel);

        $beRoomId = (int) $request->query('be_room_id', $hotel->rooms->first()?->id ?? 0);
        $beRateplanId = (int) $request->query('be_rateplan_id', $hotel->ratePlans->first()?->id ?? 0);

        $beRoom = $hotel->rooms->firstWhere('id', $beRoomId) ?? $hotel->rooms->first();
        $beRateplan = $hotel->ratePlans->firstWhere('id', $beRateplanId) ?? $hotel->ratePlans->first();

        return view('hotel.settings.index', compact(
            'hotel', 'settings', 'tabs', 'tab', 'amenities', 'beRoom', 'beRateplan'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $settings = $this->settingsService->ensureDefaults($hotel);
        $tab = $request->input('tab', 'hotel');

        match ($tab) {
            'hotel' => $this->updateHotel($request, $hotel, $settings),
            'rooms' => $this->updateRooms($request, $hotel),
            'rateplan' => $this->updateRateplans($request, $hotel),
            'pms' => $this->updatePms($request, $settings, $hotel),
            'laundry' => $this->updateLaundry($request, $settings),
            'pms-services' => $this->updatePmsServices($request, $hotel),
            'pms-category' => $this->updatePmsCategories($request, $hotel),
            'be' => $this->updateBe($request, $settings),
            'amenities' => $this->updateAmenities($request, $settings),
            'whatsapp' => $this->updateWhatsapp($request, $settings),
            default => null,
        };

        $successMessage = $tab === 'rateplan' ? 'Prices saved successfully.' : 'Settings saved successfully.';
        $flashKey = 'success';

        if ($tab === 'rateplan') {
            $flash = $this->cmPush->flashForSaveResult(
                $successMessage,
                $this->cmPush->pushAfterRateSave($hotel->fresh())
            );
            $successMessage = $flash['message'];
            $flashKey = $flash['flash_key'];
        }

        $redirectParams = ['tab' => $tab];

        if ($tab === 'be') {
            if ($request->filled('room_amenities_room_id')) {
                $redirectParams['be_room_id'] = $request->input('room_amenities_room_id');
            }
            if ($request->filled('rateplan_amenities_plan_id')) {
                $redirectParams['be_rateplan_id'] = $request->input('rateplan_amenities_plan_id');
            }
        }

        return redirect()
            ->route('hotel.settings.index', $redirectParams)
            ->with($flashKey, $successMessage);
    }

    private function updateHotel(Request $request, $hotel, $settings): void
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:255'],
            'pin_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'google_maps_url' => ['nullable', 'string', 'max:500'],
            'currency' => ['nullable', 'string', 'max:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'google_review_link' => ['nullable', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:64'],
            'bank_ifsc' => ['nullable', 'string', 'max:32'],
        ]);

        $hotel->update($validated);
    }

    private function updateRooms(Request $request, $hotel): void
    {
        $hotel->loadMissing('plan');
        $roomsInput = $request->input('rooms', []);

        foreach ($roomsInput as $roomData) {
            if (! empty($roomData['_delete']) && ! empty($roomData['id'])) {
                $hotel->rooms()->where('id', $roomData['id'])->delete();
            }
        }

        $newRoomCount = collect($roomsInput)->filter(function ($roomData) {
            return empty($roomData['_delete']) && empty($roomData['id']) && ! empty($roomData['name']);
        })->count();

        if ($newRoomCount > 0) {
            $maxRooms = $hotel->maxRooms();
            $remaining = $maxRooms === 0 ? PHP_INT_MAX : max(0, $maxRooms - $hotel->rooms()->count());

            if ($newRoomCount > $remaining) {
                throw ValidationException::withMessages([
                    'rooms' => 'You have reached the maximum number of room types allowed on your plan.',
                ]);
            }
        }

        foreach ($roomsInput as $index => $roomData) {
            if (! empty($roomData['_delete']) || empty($roomData['name'])) {
                continue;
            }

            $attrs = [
                'is_enabled' => $request->boolean("rooms.$index.is_enabled"),
                'rank' => (int) ($roomData['rank'] ?? 0),
                'name' => $roomData['name'],
                'display_name' => $roomData['display_name'] ?? $roomData['name'],
                'description' => $roomData['description'] ?? null,
                'room_count' => (int) ($roomData['room_count'] ?? 1),
                'min_occupancy' => (int) ($roomData['min_occupancy'] ?? 1),
                'max_occupancy' => (int) ($roomData['max_occupancy'] ?? 2),
                'show_ota_breakup' => $request->boolean("rooms.$index.show_ota_breakup"),
            ];

            if (! empty($roomData['id'])) {
                $hotel->rooms()->where('id', $roomData['id'])->update($attrs);
            } else {
                $hotel->rooms()->create($attrs);
            }
        }
    }

    private function updateRateplans(Request $request, $hotel): void
    {
        $data = $request->validate([
            'rateplans' => ['nullable', 'array'],
            'rateplans.*.id' => ['nullable', 'integer'],
            'rateplans.*._delete' => ['nullable', 'boolean'],
            'rateplans.*.hotel_room_id' => ['required', 'integer'],
            'rateplans.*.meal_plan' => ['nullable', 'string', Rule::in(HotelRatePlan::mealPlanCodes())],
            'rateplans.*.base_rate' => ['nullable', 'numeric', 'min:0'],
            'rateplans.*.local_base_rate' => ['nullable', 'numeric', 'min:0'],
            'rateplans.*.local_currency' => ['nullable', 'string', 'size:3'],
            'rateplans.*.international_currency' => ['nullable', 'string', 'size:3'],
        ]);

        foreach ($data['rateplans'] ?? [] as $rp) {
            if (! empty($rp['_delete']) && ! empty($rp['id'])) {
                $hotel->ratePlans()->where('id', $rp['id'])->delete();

                continue;
            }

            if (! empty($rp['_delete'])) {
                continue;
            }

            if (! $hotel->rooms()->where('id', $rp['hotel_room_id'])->exists()) {
                continue;
            }

            $mealPlan = strtoupper($rp['meal_plan'] ?? 'EP');
            $planId = $rp['id'] ?? null;
            $roomName = $hotel->rooms()->where('id', $rp['hotel_room_id'])->value('name');

            $attrs = [
                'hotel_room_id' => $rp['hotel_room_id'],
                'code' => $this->ratePlanCode($hotel, (int) $rp['hotel_room_id'], $mealPlan, $planId ? (int) $planId : null),
                'occupancy' => 'Standard',
                'meal_plan' => $mealPlan,
                'colour_code' => '#940000',
                'meals' => HotelRatePlan::mealsForPlan($mealPlan),
                'is_master' => true,
                'base_rate' => $rp['base_rate'] ?? 0,
                'local_base_rate' => isset($rp['local_base_rate']) && $rp['local_base_rate'] !== '' ? $rp['local_base_rate'] : null,
                'local_currency' => $rp['local_currency'] ?? $hotel->currency ?? 'TZS',
                'international_currency' => $rp['international_currency'] ?? 'USD',
                'ratio' => 1,
            ];

            if ($planId) {
                $hotel->ratePlans()->where('id', $planId)->update($attrs);
            } else {
                $hotel->ratePlans()->create(array_merge($attrs, [
                    'be_ratio' => 0.85,
                    'description' => $roomName,
                ]));
            }
        }
    }

    private function ratePlanCode($hotel, int $roomId, string $mealPlan, ?int $planId): string
    {
        if ($planId) {
            $existing = $hotel->ratePlans()->where('id', $planId)->value('code');
            if ($existing) {
                return $existing;
            }
        }

        $roomName = $hotel->rooms()->where('id', $roomId)->value('name');

        return Str::slug($roomName ?: 'room').'-'.strtolower($mealPlan);
    }

    private function updatePms(Request $request, $settings, $hotel): void
    {
        $pms = array_merge($settings->pms ?? [], $request->validate([
            'invoice_heading' => ['nullable', 'string', 'max:255'],
            'invoice_name' => ['nullable', 'string', 'max:255'],
            'invoice_address' => ['nullable', 'string', 'max:500'],
            'folio_prefix' => ['nullable', 'string', 'max:50'],
            'invoice_prefix' => ['nullable', 'string', 'max:50'],
            'receipt_prefix' => ['nullable', 'string', 'max:50'],
            'night_audit_time' => ['nullable', 'string', 'max:10'],
            'report_time' => ['nullable', 'string', 'max:10'],
            'allow_overbooking' => ['nullable', 'boolean'],
            'overbooking_count' => ['nullable', 'integer', 'min:0'],
            'hide_rate_in_grc' => ['nullable', 'boolean'],
            'separate_items_per_date' => ['nullable', 'boolean'],
            'balance_payable' => ['nullable', 'boolean'],
            'hide_tax_blurb' => ['nullable', 'boolean'],
            'invoice_total_text' => ['nullable', 'string', 'max:255'],
            'tax_text' => ['nullable', 'string', 'max:255'],
            'booking_confirmation_email' => ['nullable', 'email', 'max:255'],
            'segments' => ['nullable', 'array'],
            'payment_modes' => ['nullable', 'array'],
            'identity_types' => ['nullable', 'array'],
            'expense_categories' => ['nullable', 'array'],
        ]));

        $pms['allow_overbooking'] = $request->boolean('allow_overbooking');
        $pms['hide_rate_in_grc'] = $request->boolean('hide_rate_in_grc');
        $pms['separate_items_per_date'] = $request->boolean('separate_items_per_date');
        $pms['balance_payable'] = $request->boolean('balance_payable');
        $pms['hide_tax_blurb'] = $request->boolean('hide_tax_blurb');

        $reservation = $settings->reservation ?? [];
        $reservation['segments'] = array_values(array_filter($request->input('segments', [])));
        $reservation['payment_modes'] = array_values(array_filter($request->input('payment_modes', [])));
        $reservation['identity_types'] = array_values(array_filter($request->input('identity_types', [])));
        $reservation['expense_categories'] = array_values(array_filter($request->input('expense_categories', [])));

        $settings->update(['pms' => $pms, 'reservation' => $reservation]);
    }

    private function updateLaundry(Request $request, $settings): void
    {
        $laundry = $settings->laundry ?? [];
        $laundry['items'] = collect($request->input('laundry_items', []))
            ->filter(fn ($item) => ! empty($item['name']))
            ->values()
            ->all();
        $laundry['stock'] = [
            'item_name' => $request->input('stock_item_name', ''),
            'total_items' => (int) $request->input('stock_total_items', 1),
            'current_balance' => (int) $request->input('stock_current_balance', 1),
        ];
        $settings->update(['laundry' => $laundry]);
    }

    private function updatePmsServices(Request $request, $hotel): void
    {
        foreach ($request->input('services', []) as $svc) {
            if (empty($svc['name'])) {
                continue;
            }
            $attrs = [
                'name' => $svc['name'],
                'amount' => $svc['amount'] ?? 0,
                'tax_category' => $svc['tax_category'] ?? 'No Tax',
                'hsn_code' => $svc['hsn_code'] ?? null,
                'tax_inclusive' => ! empty($svc['tax_inclusive']),
                'visible_on_be' => ! empty($svc['visible_on_be']),
                'amount_editable' => ! empty($svc['amount_editable']),
                'comments' => $svc['comments'] ?? null,
            ];
            if (! empty($svc['id'])) {
                $hotel->pmsServices()->where('id', $svc['id'])->update($attrs);
            } else {
                $hotel->pmsServices()->create($attrs);
            }
        }
    }

    private function updatePmsCategories(Request $request, $hotel): void
    {
        foreach ($request->input('categories', []) as $cat) {
            if (empty($cat['name'])) {
                continue;
            }
            $attrs = [
                'name' => $cat['name'],
                'service_names' => array_values(array_filter(explode(',', $cat['services'] ?? ''))),
                'comments' => $cat['comments'] ?? null,
            ];
            if (! empty($cat['id'])) {
                $hotel->pmsCategories()->where('id', $cat['id'])->update($attrs);
            } else {
                $hotel->pmsCategories()->create($attrs);
            }
        }
    }

    private function updateAmenities(Request $request, $settings): void
    {
        $validated = $request->validate([
            'custom_amenities' => ['nullable', 'array'],
            'custom_amenities.*.key' => ['required', 'string', 'max:80'],
            'custom_amenities.*.label' => ['required', 'string', 'max:100'],
            'custom_amenities.*.icon' => ['nullable', 'string', 'max:80'],
            'custom_amenities.*._delete' => ['nullable', 'boolean'],
            'new_amenity_label' => ['nullable', 'string', 'max:100'],
            'new_amenity_icon' => ['nullable', 'string', 'max:80'],
        ]);

        $kept = [];

        foreach ($validated['custom_amenities'] ?? [] as $item) {
            if (! empty($item['_delete'])) {
                continue;
            }

            $kept[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'icon' => $item['icon'] ?? 'fa fa-star',
            ];
        }

        if (! empty($validated['new_amenity_label'])) {
            $kept[] = $this->amenityService->makeCustomEntry(
                $validated['new_amenity_label'],
                $validated['new_amenity_icon'] ?? null
            );
        }

        $settings->update(['custom_amenities' => array_values($kept)]);
    }

    private function updateBe(Request $request, $settings): void
    {
        $be = array_merge($settings->be ?? [], $request->validate([
            'default_occupancy' => ['nullable', 'integer', 'min:1'],
            'gtm_id' => ['nullable', 'string', 'max:100'],
            'facebook_pixel_id' => ['nullable', 'string', 'max:100'],
            'gtag' => ['nullable', 'string', 'max:100'],
            'gha_conversion_tag' => ['nullable', 'string', 'max:100'],
            'tripadvisor_hotel_id' => ['nullable', 'string', 'max:100'],
            'checkin_time' => ['nullable', 'string', 'max:20'],
            'checkout_time' => ['nullable', 'string', 'max:20'],
            'early_checkin_policy' => ['nullable', 'string'],
            'cancellation_policy' => ['nullable', 'string'],
            'room_amenities' => ['nullable', 'array'],
            'rateplan_amenities' => ['nullable', 'array'],
            'be_rateplans' => ['nullable', 'array'],
        ]));

        $be['add_children'] = $request->boolean('add_children');
        $be['hide_unavailable_room'] = $request->boolean('hide_unavailable_room');
        $be['show_arrival_departure_time'] = $request->boolean('show_arrival_departure_time');
        $be['tripadvisor_connect'] = $request->boolean('tripadvisor_connect');

        if ($request->has('be_rateplans')) {
            foreach ($request->input('be_rateplans', []) as $rpId => $rpData) {
                HotelRatePlan::where('id', $rpId)->update([
                    'be_ratio' => $rpData['be_ratio'] ?? 1,
                    'extra_adult' => $rpData['extra_adult'] ?? 0,
                    'extra_child' => $rpData['extra_child'] ?? 0,
                    'description' => $rpData['description'] ?? null,
                    'policy' => $rpData['policy'] ?? null,
                ]);
            }
        }

        foreach ($request->input('room_descriptions', []) as $roomId => $description) {
            HotelRoom::where('id', $roomId)
                ->where('hotel_id', auth()->user()->hotel_id)
                ->update(['description' => $description]);
        }

        if ($request->has('room_amenities_room_id')) {
            HotelRoom::where('id', $request->input('room_amenities_room_id'))->update([
                'amenities' => array_values($request->input('room_amenities', [])),
            ]);
        }

        if ($request->has('rateplan_amenities_plan_id')) {
            HotelRatePlan::where('id', $request->input('rateplan_amenities_plan_id'))->update([
                'amenities' => array_values(array_slice($request->input('rateplan_amenities', []), 0, 3)),
            ]);
        }

        $settings->update(['be' => $be]);
    }

    private function updateWhatsapp(Request $request, $settings): void
    {
        $whatsapp = array_merge($settings->whatsapp ?? [], $request->validate([
            'api_url' => ['nullable', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'sender_number' => ['nullable', 'string', 'max:30'],
            'template_booking' => ['nullable', 'string'],
            'template_checkin' => ['nullable', 'string'],
        ]));
        $whatsapp['enabled'] = $request->boolean('enabled');
        $whatsapp['booking_confirmation'] = $request->boolean('booking_confirmation');
        $whatsapp['checkin_reminder'] = $request->boolean('checkin_reminder');
        $whatsapp['checkout_reminder'] = $request->boolean('checkout_reminder');
        $settings->update(['whatsapp' => $whatsapp]);
    }

    public function connectWhatsapp(): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $settings = $this->settingsService->ensureDefaults($hotel);
        $whatsapp = $settings->whatsapp ?? [];

        $whatsapp['facebook_connected'] = true;
        $whatsapp['enabled'] = true;
        $whatsapp['facebook_page_name'] = $hotel->name.' WhatsApp';
        $whatsapp['connected_at'] = now()->toIso8601String();

        $settings->update(['whatsapp' => $whatsapp]);

        return redirect()
            ->route('hotel.settings.index', ['tab' => 'whatsapp'])
            ->with('success', 'WhatsApp connected with Facebook successfully.');
    }

    public function disconnectWhatsapp(): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $settings = $this->settingsService->ensureDefaults($hotel);
        $whatsapp = $settings->whatsapp ?? [];

        $whatsapp['facebook_connected'] = false;
        $whatsapp['enabled'] = false;
        $whatsapp['facebook_page_name'] = null;
        $whatsapp['connected_at'] = null;

        $settings->update(['whatsapp' => $whatsapp]);

        return redirect()
            ->route('hotel.settings.index', ['tab' => 'whatsapp'])
            ->with('success', 'WhatsApp disconnected from Facebook.');
    }
}
