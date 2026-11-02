<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelPmsCategory;
use App\Models\HotelPmsService;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Models\HotelSetting;

class HotelSettingsService
{
    public function ensureDefaults(Hotel $hotel): HotelSetting
    {
        $settings = $hotel->settings()->firstOrCreate([], [
            'pms' => $this->defaultPms(),
            'be' => $this->defaultBe(),
            'rateplan' => $this->defaultRateplan(),
            'whatsapp' => $this->defaultWhatsapp(),
            'laundry' => $this->defaultLaundry(),
            'reservation' => $this->defaultReservation(),
            'hr' => $this->defaultHr(),
            'bar' => $this->defaultBar(),
            'hk_procurement' => $this->defaultDepartmentProcurement(),
            'laundry_procurement' => $this->defaultDepartmentProcurement(),
            'reception_procurement' => $this->defaultDepartmentProcurement(),
        ]);

        if (! is_array($settings->hr)) {
            $settings->update(['hr' => $this->defaultHr()]);
            $settings = $settings->fresh();
        }

        if (! is_array($settings->bar)) {
            $settings->update(['bar' => $this->defaultBar()]);
            $settings = $settings->fresh();
        }

        foreach (['hk_procurement', 'laundry_procurement', 'reception_procurement'] as $procKey) {
            if (! is_array($settings->{$procKey})) {
                $settings->update([$procKey => $this->defaultDepartmentProcurement()]);
                $settings = $settings->fresh();
            }
        }

        if (! is_array($settings->rateplan)) {
            $settings->update(['rateplan' => $this->defaultRateplan()]);
            $settings = $settings->fresh();
        }

        if ($hotel->rooms()->count() === 0) {
            $room = $hotel->rooms()->create([
                'is_enabled' => true,
                'rank' => 0,
                'name' => 'Single room',
                'display_name' => 'Single room',
                'description' => 'Single room',
                'room_count' => 5,
                'min_occupancy' => 1,
                'max_occupancy' => 2,
                'amenities' => ['wifi', 'king_bed', 'breakfast', 'ac', 'tv'],
            ]);

            $hotel->ratePlans()->create([
                'hotel_room_id' => $room->id,
                'code' => 'S',
                'occupancy' => 'Single (S)',
                'meal_plan' => 'CP',
                'colour_code' => '#940000',
                'meals' => 1,
                'is_master' => true,
                'base_rate' => 50,
                'ratio' => 1,
                'be_ratio' => 0.85,
                'extra_adult' => 500,
                'extra_child' => 0,
                'description' => 'Rooms + Breakfast',
                'amenities' => ['wifi', 'breakfast', 'ac', 'tv'],
            ]);
        }

        if ($hotel->pmsServices()->count() === 0) {
            $hotel->pmsServices()->create([
                'name' => 'Extra Charges',
                'category' => 'other',
                'amount' => 100,
                'pricing_unit' => 'fixed',
                'tax_category' => 'No Tax',
                'hsn_code' => '996311',
                'tax_inclusive' => true,
                'visible_on_be' => false,
                'amount_editable' => true,
                'is_active' => true,
                'comments' => 'Extra Charges',
            ]);
        }

        if ($hotel->pmsCategories()->count() === 0) {
            $hotel->pmsCategories()->create([
                'name' => 'Extra Charges',
                'service_names' => ['Extra Charges'],
                'comments' => 'Extra Charges',
            ]);
            $hotel->pmsCategories()->create([
                'name' => 'Rooms',
                'service_names' => ['Room Charges', 'RoundOff'],
                'comments' => 'Room Charges',
            ]);
        }

        return $settings->fresh();
    }

    /** @return array<string, mixed> */
    public function defaultPms(): array
    {
        return [
            'invoice_heading' => 'Tax Invoice',
            'invoice_name' => '',
            'invoice_address' => '',
            'folio_prefix' => date('Y').'-MG-FOL-',
            'invoice_prefix' => date('Y').'-MG-',
            'receipt_prefix' => date('Y').'-MG-R-',
            'night_audit_time' => '05:00',
            'report_time' => '11:00',
            'allow_overbooking' => false,
            'overbooking_count' => 0,
            'hide_rate_in_grc' => false,
            'separate_items_per_date' => false,
            'balance_payable' => false,
            'hide_tax_blurb' => false,
            'invoice_total_text' => '',
            'tax_text' => '',
            'booking_confirmation_email' => '',
            'auto_send_payment_links' => false,
            'front_desk_checkin_time' => '14:00',
            'front_desk_checkout_time' => '11:00',
            'late_checkout_amount' => 50,
            'vat_rate' => 18,
        ];
    }

    public function vatRate(Hotel $hotel): float
    {
        $this->ensureDefaults($hotel);
        $pms = is_array($hotel->settings?->pms) ? $hotel->settings->pms : [];
        $rate = (float) ($pms['vat_rate'] ?? 18);

        return max(0, min(100, round($rate, 2)));
    }

    /** @return array{bank_providers: list<string>, mobile_payment_providers: list<string>} */
    public function paymentProviderConfig(Hotel $hotel): array
    {
        $this->ensureDefaults($hotel);
        $reservation = is_array($hotel->settings?->reservation) ? $hotel->settings->reservation : [];
        $defaults = $this->defaultReservation();

        return [
            'bank_providers' => array_values($reservation['bank_providers'] ?? $defaults['bank_providers'] ?? []),
            'mobile_payment_providers' => array_values($reservation['mobile_payment_providers'] ?? $defaults['mobile_payment_providers'] ?? []),
        ];
    }

    /** @return list<string> */
    public function paymentProviderOptions(Hotel $hotel, string $paymentMode): array
    {
        $mode = strtolower(trim($paymentMode));
        $config = $this->paymentProviderConfig($hotel);

        if ($mode === '') {
            return [];
        }

        if (str_contains($mode, 'bank')) {
            return $config['bank_providers'];
        }

        if (preg_match('/mobile|m-pesa|mpesa|wallet|tigo|airtel|halopesa|upi/', $mode)) {
            return $config['mobile_payment_providers'];
        }

        return [];
    }

    public function paymentProviderLabel(string $paymentMode): string
    {
        return str_contains(strtolower($paymentMode), 'bank') ? 'Bank' : 'Mobile Payment Provider';
    }

    /** @return array<string, mixed> */
    public function defaultRateplan(): array
    {
        return [
            'delink_from_base_rate' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function defaultBe(): array
    {
        return [
            'default_occupancy' => 2,
            'add_children' => false,
            'hide_unavailable_room' => false,
            'show_arrival_departure_time' => false,
            'gtm_id' => '',
            'facebook_pixel_id' => '',
            'gtag' => '',
            'gha_conversion_tag' => '',
            'tripadvisor_connect' => false,
            'tripadvisor_hotel_id' => '',
            'checkin_time' => '1PM',
            'checkout_time' => '11AM',
            'early_checkin_policy' => 'Early Checkin and Late Checkout will be charged Extra.',
            'cancellation_policy' => 'Cancellation can be done upto 24 hours before checkin using the link on email. No Cancellation or refund will be processed.',
        ];
    }

    /** @return array<string, mixed> */
    public function defaultWhatsapp(): array
    {
        return [
            'enabled' => false,
            'facebook_connected' => false,
            'facebook_page_name' => null,
            'connected_at' => null,
            'api_url' => '',
            'api_key' => '',
            'sender_number' => '',
            'booking_confirmation' => true,
            'checkin_reminder' => true,
            'checkout_reminder' => false,
            'template_booking' => 'Hello {guest_name}, your booking at {hotel_name} is confirmed.',
            'template_checkin' => 'Reminder: Check-in at {hotel_name} is today.',
        ];
    }

    /** @return array<string, mixed> */
    public function defaultLaundry(): array
    {
        return [
            'items' => [
                ['name' => 'Bath Towel'],
                ['name' => 'Hand Towel'],
                ['name' => 'Double Bedsheet'],
                ['name' => 'Single Bedsheet'],
                ['name' => 'Pillow Cover'],
                ['name' => 'Duvet'],
                ['name' => 'Bath Mat'],
                ['name' => 'Bed Runner'],
                ['name' => 'Cushion Cover'],
            ],
            'stock' => [
                'item_name' => '',
                'total_items' => 1,
                'current_balance' => 1,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function defaultReservation(): array
    {
        return [
            'segments' => ['Leisure', 'Corporate', 'Event', 'Walkin', 'OTA'],
            'payment_modes' => ['Cash', 'Credit Card', 'UPI', 'Bank Transfer', 'Mobile Payment', 'Bill to Company', 'Prepaid'],
            'bank_providers' => ['CRDB Bank', 'NMB Bank', 'NBC Bank', 'Stanbic Bank', 'Equity Bank'],
            'mobile_payment_providers' => ['M-Pesa', 'Tigo Pesa', 'Airtel Money', 'Halopesa'],
            'identity_types' => ['Passport', 'Drivers License', 'VoterID'],
            'expense_categories' => [
                'Electricity Expenses', 'Staff Expenses', 'Purchases',
                'Maintenance Expenses', 'Housekeeping Expenses',
            ],
            'purposes' => [],
            'floors' => [],
            'channels' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function defaultBar(): array
    {
        return [
            'purchase_list' => $this->defaultDepartmentProcurement()['purchase_list'],
        ];
    }

    /** @return array<string, mixed> */
    public function defaultDepartmentProcurement(): array
    {
        return [
            'purchase_list' => [
                'recipient_emails' => [],
                'recipient_staff_user_ids' => [],
                'include_hotel_email' => true,
                'budget_currency' => null,
                'accountant_staff_user_ids' => [],
                'default_vendor_id' => null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function defaultHr(): array
    {
        return [
            'min_password_length' => 8,
            'require_strong_password' => true,
            'email_domain_hints' => [],
            'staff_document_types' => [
                ['key' => 'national_id', 'label' => 'National ID / Passport', 'required' => true, 'enabled' => true],
                ['key' => 'employment_contract', 'label' => 'Employment contract', 'required' => false, 'enabled' => true],
                ['key' => 'certificates', 'label' => 'Training certificates', 'required' => false, 'enabled' => false],
            ],
        ];
    }

    /** @return list<string> */
    public function tabs(): array
    {
        return [
            'hotel' => 'Hotel',
            'rooms' => 'Rooms',
            'rateplan' => 'Prices',
            'amenities' => 'Amenities',
            'pms' => 'PMS Setting',
            'laundry' => 'Laundry',
            'pms-services' => 'PMS Service',
            'pms-category' => 'PMS Category',
            'store-categories' => 'Store categories',
            'hr' => 'HR & Staff',
            'bar' => 'Bar',
            'hk_procurement' => 'HK procurement',
            'laundry_procurement' => 'Laundry procurement',
            'reception_procurement' => 'Reception procurement',
            'be' => 'BE',
            'whatsapp' => 'WhatsApp',
        ];
    }
}
