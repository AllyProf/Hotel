<?php

namespace App\Services\Reports;

use App\Models\CmReservation;
use App\Models\Hotel;
use App\Models\HotelCmReview;
use App\Models\HotelCompany;
use App\Models\HotelConferenceBooking;
use App\Models\HotelExpense;
use App\Models\HotelLaundryOrder;
use App\Models\HotelLog;
use App\Models\HotelSwimmingVisit;
use App\Models\HotelPaymentLink;
use App\Models\HotelRatePlan;
use App\Models\HotelRoom;
use App\Models\HotelRoomUnit;
use App\Services\CompanyDataService;
use App\Services\ExpenseDataService;
use App\Services\FrontDeskService;
use App\Services\OtaLogoService;
use App\Services\Pos\PosOrderService;
use App\Services\RoomViewService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PmsReportGenerator
{
    public function __construct(
        private OtaLogoService $otaLogos,
        private FrontDeskService $frontDesk,
        private RoomViewService $roomView,
        private ExpenseDataService $expenses,
        private CompanyDataService $companies,
        private PosOrderService $posOrders,
    ) {}

    /** @return array{available: bool, title: string, columns: list<string>, rows: list<array<string, string>>, summary: array<string, mixed>|null} */
    public function generate(Hotel $hotel, string $hotelCode, string $reportKey, array $filters, string $title): array
    {
        $summary = null;
        $rows = match ($reportKey) {
            'arrival_report' => $this->bookingRows($this->bookings($hotel, $hotelCode, $filters, 'checkin')),
            'departure_report' => $this->bookingRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'room_status_report' => $this->roomStatusRows($hotel, $filters),
            'police_enquiry_report' => $this->policeEnquiryRows($this->bookings($hotel, $hotelCode, $filters, 'checkin')),
            'out_of_order_room_report' => $this->outOfOrderRows($hotel, $filters),
            'no_show_report' => $this->noShowRows($hotel, $hotelCode, $filters),
            'reviews_tracking_report' => $this->reviewRows($hotel, $filters),
            'maintenance_report' => $this->maintenanceRows($hotel, $filters),
            'room_wise_revenue_report' => $this->roomWiseRevenueRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'date_wise_revenue_report' => $this->dateWiseRevenueRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'checkout_accounting_report' => $this->checkoutAccountingRows($hotel, $hotelCode, $filters),
            'payments_report' => $this->paymentsRows($hotel, $hotelCode, $filters),
            'expense_report' => $this->expenseRows($hotel, $filters),
            'city_ledger_payment_report' => $this->cityLedgerRows($hotel, $hotelCode, $filters),
            'cancelled_reports' => $this->cancelledRows($hotel, $hotelCode, $filters),
            'discount_reports' => $this->discountRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'hotel_sales_report' => $this->hotelSalesRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'company_performance' => $this->companyPerformanceRows($hotel, $hotelCode, $filters),
            'room_category_sales_report' => $this->roomCategorySalesRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'room_number_sales_report' => $this->roomNumberSalesRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'monthly_performance_report' => $this->monthlyPerformanceRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'room_wise_forecast_report' => $this->roomWiseForecastRows($this->bookings($hotel, $hotelCode, $filters, 'checkin')),
            'daily_forecast_report' => $this->dailyForecastRows($this->bookings($hotel, $hotelCode, $filters, 'checkin')),
            'monthly_pl' => [],
            'contribution_report' => $this->contributionRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'same_day_checkouts_report' => $this->sameDayCheckoutRows($this->bookings($hotel, $hotelCode, $filters, 'checkout')),
            'guest_phone_difference' => $this->guestPhoneDifferenceRows($hotel, $hotelCode, $filters),
            'late_checkout_report' => $this->lateCheckoutRows($hotel, $hotelCode, $filters),
            'early_checkin_report' => $this->earlyCheckinRows($hotel, $hotelCode, $filters),
            'room_upgrade_report' => $this->roomUpgradeRows($this->bookings($hotel, $hotelCode, $filters, 'checkin')),
            'laundry_report' => $this->serviceReportRows($this->laundryReportBundle($hotel, $filters), $summary),
            'swimming_report' => $this->serviceReportRows($this->swimmingReportBundle($hotel, $filters), $summary),
            'conference_report' => $this->serviceReportRows($this->conferenceReportBundle($hotel, $filters), $summary),
            'pos_report' => $this->serviceReportRows($this->posOrders->posReportBundle($hotel, $filters), $summary),
            'item_consumption_report' => $this->serviceReportRows($this->posOrders->itemConsumptionReportBundle($hotel, $filters), $summary),
            default => [],
        };

        if ($reportKey === 'monthly_pl') {
            $matrix = $this->monthlyPlMatrix($hotel, $hotelCode, $filters);

            return [
                'available' => true,
                'title' => $title,
                'columns' => $matrix['columns'],
                'rows' => $matrix['export_rows'],
                'summary' => ['monthly_pl' => $matrix],
            ];
        }

        $columns = $rows !== [] ? array_keys($rows[0]) : $this->defaultColumns($reportKey);

        return [
            'available' => true,
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /** @param  array{rows: list<array<string, string>>, summary: array<string, mixed>}  $bundle
     * @return list<array<string, string>>
     */
    private function serviceReportRows(array $bundle, ?array &$summary): array
    {
        $summary = $bundle['summary'] ?? null;

        return $bundle['rows'];
    }

    /** @return list<string> */
    private function defaultColumns(string $reportKey): array
    {
        return match ($reportKey) {
            'room_status_report' => ['Room No', 'Room Type', 'Status', 'Guest', 'Booking ID'],
            'expense_report' => ['Date', 'Type', 'Category', 'Vendor', 'Amount', 'Payment Type', 'Invoice No'],
            'payments_report' => ['Date', 'Booking ID', 'Guest', 'Amount', 'Mode', 'Source'],
            'laundry_report' => ['Date', 'Ticket No', 'Guest', 'Room', 'Guest Type', 'Status', 'Express', 'Total', 'Payment', 'Folio Posted'],
            'swimming_report' => ['Date', 'Visit No', 'Guest', 'Room', 'Guest Type', 'Rate', 'Qty', 'Total', 'Status', 'Payment', 'Folio Posted'],
            'conference_report' => ['Start', 'Booking No', 'Event', 'Organizer', 'Company', 'Guest Type', 'Delegates', 'Total', 'Status', 'Payment', 'Folio Posted'],
            'pos_report' => ['Date', 'POS Outlet', 'Invoice No', 'Order No', 'Details', 'Subtotal', 'Tax', 'Total', 'Payment', 'Status'],
            'item_consumption_report' => ['POS Outlet', 'Service Name', 'Qty', 'Amount', 'Tax', 'Total'],
            default => ['Booking ID', 'Guest Name', 'Check-In', 'Check-Out', 'Source', 'Room Type', 'Amount'],
        };
    }

    /** @param  array<string, mixed>  $filters */
    private function bookings(Hotel $hotel, string $hotelCode, array $filters, string $dateColumn): Collection
    {
        $query = $this->reservationQuery($hotel, $hotelCode, $filters);

        if (! empty($filters['from_date'])) {
            $query->whereDate($dateColumn, '>=', Carbon::parse($filters['from_date'])->format('Y-m-d'));
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate($dateColumn, '<=', Carbon::parse($filters['to_date'])->format('Y-m-d'));
        }

        return $this->applyRoomAndPlanFilters($hotel, $query->orderBy($dateColumn)->orderBy('booking_id')->get(), $filters);
    }

    /** @param  array<string, mixed>  $filters */
    private function reservationQuery(Hotel $hotel, string $hotelCode, array $filters): Builder
    {
        $query = CmReservation::query()
            ->where(function (Builder $builder) use ($hotel, $hotelCode) {
                $builder->where('hotel_id', $hotel->id)
                    ->orWhere('hotel_code', $hotelCode);
            })
            ->with(['roomUnit.roomType']);

        if ($filters['source'] !== '') {
            $query->where('channel', $filters['source']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['segment'] !== '') {
            $segment = $filters['segment'];
            $query->where(function (Builder $builder) use ($segment) {
                $builder->where('payload->segment', $segment)
                    ->orWhere('payload->category', $segment)
                    ->orWhere('payload->bookingCategory', $segment);
            });
        }

        if ($filters['guest_search'] !== '') {
            $term = '%'.$filters['guest_search'].'%';
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('guest_first_name', 'like', $term)
                    ->orWhere('guest_last_name', 'like', $term)
                    ->orWhere('booking_id', 'like', $term);
            });
        }

        return $query;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CmReservation>
     */
    private function applyRoomAndPlanFilters(Hotel $hotel, Collection $bookings, array $filters): Collection
    {
        if ($filters['room_type'] === '' && $filters['rate_plan'] === '') {
            return $bookings;
        }

        $room = $filters['room_type'] !== ''
            ? HotelRoom::query()->where('hotel_id', $hotel->id)->find($filters['room_type'])
            : null;

        $plan = $filters['rate_plan'] !== ''
            ? HotelRatePlan::query()->where('hotel_id', $hotel->id)->with('room')->find($filters['rate_plan'])
            : null;

        return $bookings->filter(function (CmReservation $booking) use ($room, $plan) {
            if ($room && ! $this->bookingMatchesRoom($booking, $room)) {
                return false;
            }

            if ($plan && ! $this->bookingMatchesRatePlan($booking, $plan)) {
                return false;
            }

            return true;
        })->values();
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function bookingRows(Collection $bookings): array
    {
        return $bookings->map(fn (CmReservation $booking) => [
            'Booking ID' => $booking->booking_id,
            'Guest Name' => $booking->guestName(),
            'Check-In' => $booking->checkinLabel(),
            'Check-Out' => $booking->checkoutLabel(),
            'Source' => $this->otaLogos->presentationForChannel((string) $booking->channel)['name'],
            'Segment' => $booking->categoryLabel() !== '—' ? $booking->categoryLabel() : '',
            'Room Type' => $booking->roomLabel() !== '—' ? $booking->roomLabel() : '',
            'Rate Plan' => $booking->mealPlanLabel() !== '—' ? $booking->mealPlanLabel() : '',
            'Status' => $booking->statusLabel(),
            'Amount' => $booking->priceLabel() !== '—' ? $booking->priceLabel() : '',
        ])->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function roomStatusRows(Hotel $hotel, array $filters): array
    {
        $date = Carbon::parse($filters['from_date'] ?? now()->format('Y-m-d'));
        $snapshot = $this->roomView->build($hotel, $date);
        $rows = [];

        foreach ($snapshot['room_types'] ?? [] as $type) {
            foreach ($type['units'] ?? [] as $unit) {
                $rows[] = [
                    'Room No' => (string) ($unit['label'] ?? '—'),
                    'Room Type' => (string) ($type['name'] ?? '—'),
                    'Status' => (string) ($unit['status_label'] ?? '—'),
                    'Guest' => (string) ($unit['guest'] ?? '—'),
                    'Booking ID' => (string) ($unit['booking_id'] ?? '—'),
                ];
            }
        }

        return $rows;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function policeEnquiryRows(Collection $bookings): array
    {
        return $bookings->map(function (CmReservation $booking) {
            $payload = is_array($booking->payload) ? $booking->payload : [];
            $guest = is_array($payload['guest'] ?? null) ? $payload['guest'] : [];

            return [
                'Booking ID' => $booking->booking_id,
                'Guest Name' => $booking->guestName(),
                'Check-In' => $booking->checkinLabel(),
                'Check-Out' => $booking->checkoutLabel(),
                'Room' => $booking->isRoomAssigned() ? $booking->assignedRoomLabel() : $booking->roomLabel(),
                'ID Type' => (string) ($guest['identity_type'] ?? $payload['identity_type'] ?? '—'),
                'ID Number' => (string) ($guest['identity_detail'] ?? $payload['identity_detail'] ?? '—'),
                'Phone' => $booking->guestPhone() ?? '—',
                'Email' => $booking->guestEmail() ?? '—',
                'Address' => (string) ($guest['address'] ?? $payload['address'] ?? '—'),
                'Country' => (string) ($guest['country'] ?? $payload['country'] ?? '—'),
            ];
        })->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function outOfOrderRows(Hotel $hotel, array $filters): array
    {
        [$from, $to] = $this->parseRange($filters);
        $rows = [];

        HotelRoomUnit::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', HotelRoomUnit::STATUS_MAINTENANCE)
            ->with('roomType')
            ->orderBy('room_number')
            ->get()
            ->each(function (HotelRoomUnit $unit) use (&$rows) {
                $rows[] = [
                    'Room No' => $unit->displayLabel(),
                    'Room Type' => $unit->roomType?->display_name ?: $unit->roomType?->name ?: '—',
                    'Status' => 'Maintenance',
                    'Since' => $unit->updated_at?->format('d/m/Y') ?? '—',
                    'Source' => 'Room unit status',
                ];
            });

        HotelLog::query()
            ->where('hotel_id', $hotel->id)
            ->where('category', HotelLog::CATEGORY_OUT_OF_ORDER)
            ->whereBetween('logged_at', [$from, $to])
            ->orderByDesc('logged_at')
            ->get()
            ->each(function (HotelLog $log) use (&$rows) {
                $rows[] = [
                    'Room No' => $log->room_no ?: '—',
                    'Room Type' => $log->room?->display_name ?: $log->room?->name ?: '—',
                    'Status' => 'Out of order',
                    'Since' => $log->dateLabel(),
                    'Source' => $log->action_type ?: 'Log',
                ];
            });

        return $rows;
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function maintenanceRows(Hotel $hotel, array $filters): array
    {
        return $this->outOfOrderRows($hotel, $filters);
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function noShowRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $today = now()->format('Y-m-d');

        return $this->bookings($hotel, $hotelCode, $filters, 'checkin')
            ->filter(function (CmReservation $booking) use ($today) {
                if ($booking->isCancelled()) {
                    return false;
                }

                if (! in_array($booking->pms_status, [CmReservation::PMS_RESERVED, CmReservation::PMS_ASSIGNED], true)) {
                    return false;
                }

                return $booking->checkin?->format('Y-m-d') <= $today;
            })
            ->map(fn (CmReservation $booking) => [
                'Booking ID' => $booking->booking_id,
                'Guest Name' => $booking->guestName(),
                'Check-In' => $booking->checkinLabel(),
                'Source' => $this->otaLogos->presentationForChannel((string) $booking->channel)['name'],
                'Room Type' => $booking->roomLabel(),
                'Amount' => $booking->priceLabel() !== '—' ? $booking->priceLabel() : '',
                'PMS Status' => $booking->pmsStatusLabel(),
            ])
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function reviewRows(Hotel $hotel, array $filters): array
    {
        [$from, $to] = $this->parseRange($filters);

        return HotelCmReview::query()
            ->where('hotel_id', $hotel->id)
            ->whereBetween('review_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('review_date')
            ->get()
            ->map(fn (HotelCmReview $review) => [
                'Date' => $review->reviewDateLabel(),
                'Channel' => $review->channelLabel(),
                'Booking ID' => $review->booking_id ?: '—',
                'Guest' => $review->guest_name ?: '—',
                'Rating' => $review->ratingStars(),
                'Title' => $review->title ?: '—',
                'Response' => $review->response ? 'Yes' : 'No',
            ])
            ->all();
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function roomWiseRevenueRows(Collection $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if ($booking->isCancelled()) {
                continue;
            }

            $key = $booking->roomLabel() !== '—' ? $booking->roomLabel() : 'Unassigned';
            $groups[$key] ??= ['bookings' => 0, 'revenue' => 0.0, 'currency' => $booking->currency ?? ''];
            $groups[$key]['bookings']++;
            $groups[$key]['revenue'] += (float) ($booking->amount_after_tax ?? 0);
        }

        $rows = [];
        foreach ($groups as $room => $data) {
            $rows[] = [
                'Room Type' => $room,
                'Bookings' => (string) $data['bookings'],
                'Revenue' => number_format($data['revenue'], 2).' '.($data['currency'] ?: ''),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['Room Type'], $b['Room Type']));

        return $rows;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function dateWiseRevenueRows(Collection $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if ($booking->isCancelled() || $booking->checkout === null) {
                continue;
            }

            $key = $booking->checkout->format('Y-m-d');
            $groups[$key] ??= ['bookings' => 0, 'revenue' => 0.0, 'currency' => $booking->currency ?? ''];
            $groups[$key]['bookings']++;
            $groups[$key]['revenue'] += (float) ($booking->amount_after_tax ?? 0);
        }

        ksort($groups);
        $rows = [];

        foreach ($groups as $date => $data) {
            $rows[] = [
                'Date' => Carbon::parse($date)->format('d/m/Y'),
                'Bookings' => (string) $data['bookings'],
                'Revenue' => number_format($data['revenue'], 2).' '.($data['currency'] ?: ''),
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function checkoutAccountingRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        [$from, $to] = $this->parseRange($filters);

        return $this->reservationQuery($hotel, $hotelCode, $filters)
            ->where('pms_status', CmReservation::PMS_CHECKED_OUT)
            ->whereBetween('checked_out_at', [$from, $to])
            ->orderBy('checked_out_at')
            ->get()
            ->map(fn (CmReservation $booking) => [
                'Booking ID' => $booking->booking_id,
                'Guest' => $booking->guestName(),
                'Checked Out' => $booking->checked_out_at?->format('d/m/Y H:i') ?? '—',
                'Room Total' => number_format((float) ($booking->amount_after_tax ?? 0), 2),
                'Paid' => number_format((float) ($booking->amount_paid ?? 0), 2),
                'Late Fee' => number_format((float) ($booking->late_checkout_fee ?? 0), 2),
                'Balance' => number_format($booking->balanceDue(), 2),
                'Currency' => $booking->currency ?? '',
            ])
            ->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function paymentsRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        [$from, $to] = $this->parseRange($filters);
        $rows = [];

        $this->reservationQuery($hotel, $hotelCode, $filters)
            ->where('amount_paid', '>', 0)
            ->get()
            ->each(function (CmReservation $booking) use (&$rows, $from, $to) {
                $payload = is_array($booking->payload) ? $booking->payload : [];
                $payments = is_array($payload['checkout_payments'] ?? null) ? $payload['checkout_payments'] : [];

                foreach ($payments as $payment) {
                    $paidAt = isset($payment['paid_at']) ? Carbon::parse($payment['paid_at']) : null;

                    if ($paidAt && ($paidAt->lt($from) || $paidAt->gt($to))) {
                        continue;
                    }

                    $rows[] = [
                        'Date' => $paidAt?->format('d/m/Y H:i') ?? '—',
                        'Booking ID' => $booking->booking_id,
                        'Guest' => $booking->guestName(),
                        'Amount' => number_format((float) ($payment['amount'] ?? 0), 2),
                        'Mode' => (string) ($payment['mode'] ?? '—'),
                        'Source' => 'Front desk',
                    ];
                }
            });

        HotelPaymentLink::query()
            ->where('hotel_id', $hotel->id)
            ->where('status', HotelPaymentLink::STATUS_PAID)
            ->whereBetween('updated_at', [$from, $to])
            ->orderBy('updated_at')
            ->get()
            ->each(function (HotelPaymentLink $link) use (&$rows) {
                $rows[] = [
                    'Date' => $link->updated_at?->format('d/m/Y H:i') ?? '—',
                    'Booking ID' => $link->invoice_id ?: '—',
                    'Guest' => $link->guest_name ?: '—',
                    'Amount' => number_format((float) $link->amount, 2),
                    'Mode' => 'Payment link',
                    'Source' => $link->email,
                ];
            });

        usort($rows, fn ($a, $b) => strcmp($a['Date'], $b['Date']));

        return $rows;
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function expenseRows(Hotel $hotel, array $filters): array
    {
        $expenseFilters = [
            'from_date' => $filters['from_date'],
            'to_date' => $filters['to_date'],
            'payment_type' => '',
            'paid_type' => '',
        ];

        return $this->expenses->query($hotel->id, $expenseFilters)
            ->orderBy('expense_date')
            ->orderBy('id')
            ->get()
            ->map(fn (HotelExpense $expense) => [
                'Date' => $expense->expense_date?->format('d/m/Y') ?? '—',
                'Type' => $expense->entryTypeLabel(),
                'Category' => $expense->category ?: '—',
                'Vendor' => $expense->vendor ?: '—',
                'Amount' => number_format((float) $expense->amount, 2),
                'Payment Type' => $expense->paymentTypeLabel(),
                'Invoice No' => $expense->invoice_no ?: '—',
            ])
            ->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function cityLedgerRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $companies = HotelCompany::query()->where('hotel_id', $hotel->id)->get()->keyBy('id');

        return $this->bookings($hotel, $hotelCode, $filters, 'checkout')
            ->filter(function (CmReservation $booking) {
                $payload = is_array($booking->payload) ? $booking->payload : [];

                return ! empty($payload['bill_to_company_id']);
            })
            ->map(function (CmReservation $booking) use ($companies) {
                $payload = is_array($booking->payload) ? $booking->payload : [];
                $companyId = (int) ($payload['bill_to_company_id'] ?? 0);
                $company = $companies->get($companyId);

                return [
                    'Company' => $company?->name ?? 'Company #'.$companyId,
                    'Booking ID' => $booking->booking_id,
                    'Guest' => $booking->guestName(),
                    'Check-Out' => $booking->checkoutLabel(),
                    'Billed' => number_format((float) ($booking->amount_after_tax ?? 0), 2),
                    'Paid' => number_format((float) ($booking->amount_paid ?? 0), 2),
                    'Outstanding' => number_format($booking->balanceDue(), 2),
                ];
            })
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function cancelledRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $cancelledFilters = array_merge($filters, ['status' => CmReservation::STATUS_CANCELLED]);

        return $this->bookingRows($this->bookings($hotel, $hotelCode, $cancelledFilters, 'checkin'));
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function discountRows(Collection $bookings): array
    {
        return $bookings->filter(function (CmReservation $booking) {
            $payload = is_array($booking->payload) ? $booking->payload : [];
            $discountPercent = (float) ($payload['discount_percent'] ?? $payload['discount'] ?? 0);
            $discountAmount = (float) ($payload['discount_amount'] ?? 0);

            return $discountPercent > 0 || $discountAmount > 0;
        })->map(function (CmReservation $booking) {
            $payload = is_array($booking->payload) ? $booking->payload : [];
            $discountPercent = (float) ($payload['discount_percent'] ?? $payload['discount'] ?? 0);
            $discountAmount = (float) ($payload['discount_amount'] ?? 0);
            $discountLabel = $discountAmount > 0
                ? number_format($discountAmount, 2)
                : ($discountPercent > 0 ? $discountPercent.'%' : '');

            return [
                'Booking ID' => $booking->booking_id,
                'Guest' => $booking->guestName(),
                'Check-Out' => $booking->checkoutLabel(),
                'Discount' => $discountLabel,
                'Amount' => $booking->priceLabel() !== '—' ? $booking->priceLabel() : '',
                'Source' => $this->otaLogos->presentationForChannel((string) $booking->channel)['name'],
            ];
        })->values()->all();
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function hotelSalesRows(Collection $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if ($booking->isCancelled()) {
                continue;
            }

            $channel = $this->otaLogos->presentationForChannel((string) $booking->channel)['name'];
            $groups[$channel] ??= ['bookings' => 0, 'room_nights' => 0, 'revenue' => 0.0, 'currency' => $booking->currency ?? ''];
            $groups[$channel]['bookings']++;
            $groups[$channel]['room_nights'] += max(1, (int) ($booking->roomNightCount() ?? 1));
            $groups[$channel]['revenue'] += (float) ($booking->amount_after_tax ?? 0);
        }

        $rows = [];
        foreach ($groups as $channel => $data) {
            $rows[] = [
                'Source' => $channel,
                'Bookings' => (string) $data['bookings'],
                'Room Nights' => (string) $data['room_nights'],
                'Revenue' => number_format($data['revenue'], 2).' '.($data['currency'] ?: ''),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['Revenue'], $a['Revenue']));

        return $rows;
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function companyPerformanceRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $stats = $this->companies->billingStatsByCompany($hotel, $hotelCode);
        $companies = HotelCompany::query()->where('hotel_id', $hotel->id)->get()->keyBy('id');
        $rows = [];

        foreach ($stats as $companyId => $data) {
            $company = $companies->get($companyId);

            $rows[] = [
                'Company' => $company?->name ?? 'Company #'.$companyId,
                'Invoices' => (string) ($data['invoices'] ?? 0),
                'Billed' => number_format((float) ($data['billed'] ?? 0), 2),
                'Paid' => number_format((float) ($data['payments'] ?? 0), 2),
                'Outstanding' => number_format((float) ($data['outstanding'] ?? 0), 2),
                'Currency' => $data['currency'] ?? $hotel->currency ?? '',
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['Billed'], $a['Billed']));

        return $rows;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function roomCategorySalesRows(Collection $bookings): array
    {
        return $this->roomWiseRevenueRows($bookings);
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function roomNumberSalesRows(Collection $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if ($booking->isCancelled()) {
                continue;
            }

            $key = $booking->isRoomAssigned() ? $booking->assignedRoomLabel() : 'Unassigned';
            $groups[$key] ??= ['bookings' => 0, 'revenue' => 0.0, 'currency' => $booking->currency ?? ''];
            $groups[$key]['bookings']++;
            $groups[$key]['revenue'] += (float) ($booking->amount_after_tax ?? 0);
        }

        $rows = [];
        foreach ($groups as $roomNo => $data) {
            $rows[] = [
                'Room No' => $roomNo,
                'Bookings' => (string) $data['bookings'],
                'Revenue' => number_format($data['revenue'], 2).' '.($data['currency'] ?: ''),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['Room No'], $b['Room No']));

        return $rows;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function monthlyPerformanceRows(Collection $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if ($booking->isCancelled() || $booking->checkout === null) {
                continue;
            }

            $key = $booking->checkout->format('Y-m');
            $groups[$key] ??= ['bookings' => 0, 'room_nights' => 0, 'revenue' => 0.0, 'currency' => $booking->currency ?? ''];
            $groups[$key]['bookings']++;
            $groups[$key]['room_nights'] += max(1, (int) ($booking->roomNightCount() ?? 1));
            $groups[$key]['revenue'] += (float) ($booking->amount_after_tax ?? 0);
        }

        ksort($groups);
        $rows = [];

        foreach ($groups as $month => $data) {
            $rows[] = [
                'Month' => Carbon::parse($month.'-01')->format('M Y'),
                'Bookings' => (string) $data['bookings'],
                'Room Nights' => (string) $data['room_nights'],
                'Revenue' => number_format($data['revenue'], 2).' '.($data['currency'] ?: ''),
            ];
        }

        return $rows;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function roomWiseForecastRows(Collection $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if ($booking->isCancelled()) {
                continue;
            }

            $key = $booking->roomLabel() !== '—' ? $booking->roomLabel() : 'Unassigned';
            $groups[$key] ??= ['arrivals' => 0, 'room_nights' => 0];
            $groups[$key]['arrivals']++;
            $groups[$key]['room_nights'] += max(1, (int) ($booking->roomNightCount() ?? 1));
        }

        $rows = [];
        foreach ($groups as $room => $data) {
            $rows[] = [
                'Room Type' => $room,
                'Arrivals' => (string) $data['arrivals'],
                'Room Nights' => (string) $data['room_nights'],
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['Room Type'], $b['Room Type']));

        return $rows;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function dailyForecastRows(Collection $bookings): array
    {
        $groups = [];

        foreach ($bookings as $booking) {
            if ($booking->isCancelled() || $booking->checkin === null) {
                continue;
            }

            $key = $booking->checkin->format('Y-m-d');
            $groups[$key] ??= ['arrivals' => 0, 'room_nights' => 0];
            $groups[$key]['arrivals']++;
            $groups[$key]['room_nights'] += max(1, (int) ($booking->roomNightCount() ?? 1));
        }

        ksort($groups);
        $rows = [];

        foreach ($groups as $date => $data) {
            $rows[] = [
                'Date' => Carbon::parse($date)->format('d/m/Y'),
                'Arrivals' => (string) $data['arrivals'],
                'Room Nights' => (string) $data['room_nights'],
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $filters
     * @return array{
     *     columns: list<string>,
     *     month_keys: list<string>,
     *     sections: list<array<string, mixed>>,
     *     export_rows: list<array<string, string>>
     * }
     */
    private function monthlyPlMatrix(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $config = config('hotel_pms.reports.monthly_pl', []);
        $expenseFallback = (string) ($config['expense_fallback_label'] ?? 'Other Expenses');
        $purchaseFallback = (string) ($config['purchase_fallback_label'] ?? 'Other Purchases');
        $settingCategories = $this->expenses->formOptions($hotel)['categories'] ?? [];

        $from = Carbon::parse($filters['from_date'])->startOfMonth();
        $to = Carbon::parse($filters['to_date'])->startOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $monthKeys = [];
        $monthLabels = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $monthKeys[] = $cursor->format('Y-m');
            $monthLabels[] = $cursor->format('M Y');
            $cursor->addMonth();
        }

        if ($monthKeys === []) {
            $monthKeys[] = now()->format('Y-m');
            $monthLabels[] = now()->format('M Y');
        }

        $zeroMonth = array_fill_keys($monthKeys, 0.0);

        $revenueByLine = [
            'rooms' => $zeroMonth,
        ];

        foreach ($this->bookings($hotel, $hotelCode, $filters, 'checkout') as $booking) {
            if ($booking->isCancelled() || $booking->checkout === null) {
                continue;
            }

            $key = $booking->checkout->format('Y-m');
            if (! isset($revenueByLine['rooms'][$key])) {
                continue;
            }

            $revenueByLine['rooms'][$key] += (float) ($booking->amount_after_tax ?? 0);
        }

        $expenseByLabel = [];
        $purchaseByLabel = [];
        $expenseDisplayLabels = [];
        $purchaseDisplayLabels = [];

        $expenseFilters = [
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->copy()->endOfMonth()->format('Y-m-d'),
            'payment_type' => '',
            'paid_type' => '',
        ];

        $this->expenses->query($hotel->id, $expenseFilters)->get()->each(function (HotelExpense $expense) use (
            $monthKeys,
            $expenseFallback,
            $purchaseFallback,
            &$expenseByLabel,
            &$purchaseByLabel,
            &$expenseDisplayLabels,
            &$purchaseDisplayLabels,
        ) {
            if ($expense->expense_date === null || $expense->entry_type !== HotelExpense::ENTRY_EXPENSE) {
                return;
            }

            if ($expense->paid_type === HotelExpense::PAID_IN) {
                return;
            }

            $monthKey = $expense->expense_date->format('Y-m');
            if (! in_array($monthKey, $monthKeys, true)) {
                return;
            }

            $amount = (float) $expense->amount;
            $category = trim((string) $expense->category);
            $isPurchase = $expense->hotel_purchase_order_id !== null;

            if ($isPurchase) {
                $label = $category !== '' ? $category : $purchaseFallback;
                $norm = $this->monthlyPlNormalize($label);
                $purchaseByLabel[$norm] ??= array_fill_keys($monthKeys, 0.0);
                $purchaseByLabel[$norm][$monthKey] += $amount;
                $purchaseDisplayLabels[$norm] ??= $label;

                return;
            }

            $label = $category !== '' ? $category : $expenseFallback;
            $norm = $this->monthlyPlNormalize($label);
            $expenseByLabel[$norm] ??= array_fill_keys($monthKeys, 0.0);
            $expenseByLabel[$norm][$monthKey] += $amount;
            $expenseDisplayLabels[$norm] ??= $label;
        });

        $sections = [];
        $exportRows = [];
        $columns = array_merge([''], $monthLabels, ['Total']);

        $sections[] = ['kind' => 'section_title', 'label' => 'Revenues :'];
        $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, 'Revenues :', null, 'section_title');

        $revenueTotals = $zeroMonth;
        foreach ($config['revenue_lines'] ?? [['key' => 'rooms', 'label' => 'Rooms']] as $line) {
            $lineKey = (string) ($line['key'] ?? 'rooms');
            $lineLabel = (string) ($line['label'] ?? 'Rooms');
            $values = $revenueByLine[$lineKey] ?? $zeroMonth;
            $sections[] = ['kind' => 'data', 'label' => $lineLabel, 'values' => $values];
            $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, $lineLabel, $values, 'data');
            foreach ($monthKeys as $monthKey) {
                $revenueTotals[$monthKey] += $values[$monthKey] ?? 0.0;
            }
        }

        $sections[] = ['kind' => 'subtotal', 'label' => 'Total', 'values' => $revenueTotals];
        $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, 'Total', $revenueTotals, 'subtotal');

        $sections[] = ['kind' => 'section_title', 'label' => 'Expenses :'];
        $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, 'Expenses :', null, 'section_title');

        $expenseTotals = $zeroMonth;
        foreach ($this->monthlyPlActiveCategoryLabels($settingCategories, $expenseByLabel, $expenseDisplayLabels, $expenseFallback) as $label) {
            $norm = $this->monthlyPlNormalize($label);
            $values = $expenseByLabel[$norm] ?? $zeroMonth;

            $sections[] = ['kind' => 'data', 'label' => $label, 'values' => $values];
            $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, $label, $values, 'data');
            foreach ($monthKeys as $monthKey) {
                $expenseTotals[$monthKey] += $values[$monthKey] ?? 0.0;
            }
        }

        $sections[] = ['kind' => 'subtotal', 'label' => 'Total', 'values' => $expenseTotals];
        $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, 'Total', $expenseTotals, 'subtotal');

        $sections[] = ['kind' => 'section_title', 'label' => 'Purchases :'];
        $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, 'Purchases :', null, 'section_title');

        $purchaseTotals = $zeroMonth;
        foreach ($this->monthlyPlActiveCategoryLabels($settingCategories, $purchaseByLabel, $purchaseDisplayLabels, $purchaseFallback) as $label) {
            $norm = $this->monthlyPlNormalize($label);
            $values = $purchaseByLabel[$norm] ?? $zeroMonth;

            $sections[] = ['kind' => 'data', 'label' => $label, 'values' => $values];
            $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, $label, $values, 'data');
            foreach ($monthKeys as $monthKey) {
                $purchaseTotals[$monthKey] += $values[$monthKey] ?? 0.0;
            }
        }

        $sections[] = ['kind' => 'subtotal', 'label' => 'Total', 'values' => $purchaseTotals];
        $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, 'Total', $purchaseTotals, 'subtotal');

        $profit = $zeroMonth;
        foreach ($monthKeys as $monthKey) {
            $profit[$monthKey] = $revenueTotals[$monthKey] - $expenseTotals[$monthKey] - $purchaseTotals[$monthKey];
        }

        $sections[] = ['kind' => 'profit', 'label' => 'Profit :', 'values' => $profit];
        $exportRows[] = $this->monthlyPlExportRow($monthLabels, $monthKeys, 'Profit :', $profit, 'profit');

        return [
            'columns' => $columns,
            'month_keys' => $monthKeys,
            'sections' => $sections,
            'export_rows' => $exportRows,
        ];
    }

    private function monthlyPlNormalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Categories with activity in the period, ordered by PMS settings then alphabetically.
     *
     * @param  list<string>  $settingCategories
     * @param  array<string, array<string, float>>  $byLabel
     * @param  array<string, string>  $displayLabels
     * @return list<string>
     */
    private function monthlyPlActiveCategoryLabels(array $settingCategories, array $byLabel, array $displayLabels, string $fallback): array
    {
        $fallbackNorm = $this->monthlyPlNormalize($fallback);
        $labels = [];
        $seen = [];

        foreach ($settingCategories as $category) {
            $category = trim((string) $category);
            if ($category === '') {
                continue;
            }

            $norm = $this->monthlyPlNormalize($category);
            if (! isset($byLabel[$norm]) || $this->monthlyPlLineIsEmpty($byLabel[$norm])) {
                continue;
            }

            $labels[] = $displayLabels[$norm] ?? $category;
            $seen[$norm] = true;
        }

        $extra = [];
        foreach ($byLabel as $norm => $values) {
            if (isset($seen[$norm]) || $this->monthlyPlLineIsEmpty($values)) {
                continue;
            }

            $extra[] = $displayLabels[$norm] ?? $norm;
        }

        sort($extra, SORT_NATURAL | SORT_FLAG_CASE);
        $labels = array_merge($labels, $extra);

        if (isset($byLabel[$fallbackNorm]) && ! $this->monthlyPlLineIsEmpty($byLabel[$fallbackNorm]) && ! isset($seen[$fallbackNorm])) {
            $labels[] = $displayLabels[$fallbackNorm] ?? $fallback;
        }

        return $labels;
    }

    /** @param  array<string, float>  $values */
    private function monthlyPlLineIsEmpty(array $values): bool
    {
        foreach ($values as $amount) {
            if (abs($amount) > 0.00001) {
                return false;
            }
        }

        return true;
    }

    /** @param  list<string>  $monthLabels
     * @param  list<string>  $monthKeys
     * @param  array<string, float>|null  $values
     * @return array<string, string>
     */
    private function monthlyPlExportRow(array $monthLabels, array $monthKeys, string $label, ?array $values, string $kind): array
    {
        $row = ['' => $label];

        foreach ($monthKeys as $index => $monthKey) {
            $column = $monthLabels[$index] ?? $monthKey;
            if ($values === null) {
                $row[$column] = '';

                continue;
            }

            $row[$column] = $this->monthlyPlFormatAmount($values[$monthKey] ?? 0.0);
        }

        $row['Total'] = $values === null ? '' : $this->monthlyPlFormatAmount(array_sum($values));
        $row['_kind'] = $kind;

        return $row;
    }

    private function monthlyPlFormatAmount(float $amount): string
    {
        return number_format($amount, 0, '.', ',');
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function contributionRows(Collection $bookings): array
    {
        $groups = [];
        $total = 0.0;

        foreach ($bookings as $booking) {
            if ($booking->isCancelled()) {
                continue;
            }

            $channel = $this->otaLogos->presentationForChannel((string) $booking->channel)['name'];
            $amount = (float) ($booking->amount_after_tax ?? 0);
            $groups[$channel] ??= 0.0;
            $groups[$channel] += $amount;
            $total += $amount;
        }

        $rows = [];
        foreach ($groups as $channel => $amount) {
            $pct = $total > 0 ? round(($amount / $total) * 100, 1) : 0;
            $rows[] = [
                'Source' => $channel,
                'Revenue' => number_format($amount, 2),
                'Contribution' => $pct.'%',
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($b['Revenue'], $a['Revenue']));

        return $rows;
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function sameDayCheckoutRows(Collection $bookings): array
    {
        return $bookings->filter(function (CmReservation $booking) {
            return $booking->checkin && $booking->checkout
                && $booking->checkin->format('Y-m-d') === $booking->checkout->format('Y-m-d');
        })->map(fn (CmReservation $booking) => [
            'Booking ID' => $booking->booking_id,
            'Guest' => $booking->guestName(),
            'Date' => $booking->checkinLabel(),
            'Room Type' => $booking->roomLabel(),
            'Amount' => $booking->priceLabel() !== '—' ? $booking->priceLabel() : '',
            'Status' => $booking->statusLabel(),
        ])->values()->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function guestPhoneDifferenceRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $guestsByName = \App\Models\HotelGuest::query()
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->keyBy(fn ($guest) => strtolower(trim($guest->name)));

        return $this->bookings($hotel, $hotelCode, $filters, 'checkin')
            ->filter(function (CmReservation $booking) use ($guestsByName) {
                $resPhone = preg_replace('/\D+/', '', (string) $booking->guestPhone());
                $guest = $guestsByName->get(strtolower(trim($booking->guestName())));
                $profilePhone = preg_replace('/\D+/', '', (string) ($guest?->phone ?? ''));

                return $guest && $resPhone !== '' && $profilePhone !== '' && $resPhone !== $profilePhone;
            })
            ->map(function (CmReservation $booking) use ($guestsByName) {
                $guest = $guestsByName->get(strtolower(trim($booking->guestName())));

                return [
                    'Booking ID' => $booking->booking_id,
                    'Guest' => $booking->guestName(),
                    'Booking Phone' => $booking->guestPhone() ?? '—',
                    'Profile Phone' => $guest?->phone ?? '—',
                    'Check-In' => $booking->checkinLabel(),
                ];
            })
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function lateCheckoutRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        return $this->bookings($hotel, $hotelCode, $filters, 'checkout')
            ->filter(function (CmReservation $booking) use ($hotel) {
                if ((float) ($booking->late_checkout_fee ?? 0) > 0) {
                    return true;
                }

                if ($booking->isCheckedOut() && $booking->checked_out_at) {
                    return $this->frontDesk->isLateCheckout($hotel, $booking, $booking->checked_out_at);
                }

                return $this->frontDesk->isLateCheckout($hotel, $booking);
            })
            ->map(fn (CmReservation $booking) => [
                'Booking ID' => $booking->booking_id,
                'Guest' => $booking->guestName(),
                'Check-Out' => $booking->checkoutLabel(),
                'Checked Out At' => $booking->checked_out_at?->format('d/m/Y H:i') ?? '—',
                'Late Fee' => number_format((float) ($booking->late_checkout_fee ?? 0), 2),
                'Room' => $booking->isRoomAssigned() ? $booking->assignedRoomLabel() : '—',
            ])
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return list<array<string, string>>
     */
    private function earlyCheckinRows(Hotel $hotel, string $hotelCode, array $filters): array
    {
        $settings = $this->frontDesk->settings($hotel);
        $standardCheckin = $this->frontDesk->parseTime($settings['checkin_time']);

        return $this->bookings($hotel, $hotelCode, $filters, 'checkin')
            ->filter(function (CmReservation $booking) use ($standardCheckin) {
                if (! $booking->checked_in_at || ! $booking->checkin || $standardCheckin === null) {
                    return false;
                }

                $deadline = $booking->checkin->copy()->startOfDay()
                    ->setTime((int) $standardCheckin->format('H'), (int) $standardCheckin->format('i'));

                return $booking->checked_in_at->lessThan($deadline);
            })
            ->map(fn (CmReservation $booking) => [
                'Booking ID' => $booking->booking_id,
                'Guest' => $booking->guestName(),
                'Check-In Date' => $booking->checkinLabel(),
                'Checked In At' => $booking->checked_in_at?->format('d/m/Y H:i') ?? '—',
                'Room' => $booking->isRoomAssigned() ? $booking->assignedRoomLabel() : '—',
            ])
            ->values()
            ->all();
    }

    /** @param  Collection<int, CmReservation>  $bookings
     * @return list<array<string, string>>
     */
    private function roomUpgradeRows(Collection $bookings): array
    {
        return $bookings->filter(function (CmReservation $booking) {
            if (! $booking->isRoomAssigned() || ! $booking->roomUnit?->roomType) {
                return false;
            }

            $assignedName = $booking->roomUnit->roomType->display_name ?: $booking->roomUnit->roomType->name;
            $bookedLabel = $booking->roomLabel();

            return $bookedLabel !== '—'
                && strcasecmp($bookedLabel, $assignedName) !== 0
                && ! str_contains(strtolower($bookedLabel), strtolower($assignedName));
        })->map(fn (CmReservation $booking) => [
            'Booking ID' => $booking->booking_id,
            'Guest' => $booking->guestName(),
            'Booked Room Type' => $booking->roomLabel(),
            'Assigned Room' => $booking->assignedRoomLabel(),
            'Assigned Type' => $booking->roomUnit?->roomType?->display_name ?: $booking->roomUnit?->roomType?->name ?: '—',
            'Check-In' => $booking->checkinLabel(),
        ])->values()->all();
    }

    /** @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseRange(array $filters): array
    {
        return [
            Carbon::parse($filters['from_date'] ?? now()->format('Y-m-d'))->startOfDay(),
            Carbon::parse($filters['to_date'] ?? now()->format('Y-m-d'))->endOfDay(),
        ];
    }

    private function bookingMatchesRoom(CmReservation $booking, HotelRoom $room): bool
    {
        $roomName = $room->display_name ?: $room->name;

        foreach ($booking->roomLines() as $line) {
            $code = trim((string) ($line['roomCode'] ?? $line['room_code'] ?? ''));
            $name = trim((string) ($line['roomName'] ?? $line['room_name'] ?? ''));

            if ($code === $room->name || $name === $roomName) {
                return true;
            }
        }

        return false;
    }

    private function bookingMatchesRatePlan(CmReservation $booking, HotelRatePlan $plan): bool
    {
        foreach ($booking->roomLines() as $line) {
            $code = trim((string) ($line['rateplanCode'] ?? $line['rateplan_code'] ?? ''));
            $mealPlan = strtoupper(trim((string) ($line['mealPlan'] ?? $line['meal_plan'] ?? '')));

            if ($code === $plan->code) {
                return true;
            }

            if ($mealPlan !== '' && strtoupper((string) $plan->meal_plan) === $mealPlan) {
                return true;
            }
        }

        $payloadPlan = strtoupper(trim((string) (is_array($booking->payload) ? ($booking->payload['meal_plan'] ?? '') : '')));

        return $payloadPlan !== '' && $payloadPlan === strtoupper((string) $plan->meal_plan);
    }

    /** @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, string>>, summary: array<string, mixed>}
     */
    private function laundryReportBundle(Hotel $hotel, array $filters): array
    {
        $query = HotelLaundryOrder::query()
            ->where('hotel_id', $hotel->id);

        $this->applyDateRange($query, $filters, 'received_at');
        $this->applyServiceGuestSearch($query, $filters, ['ticket_no', 'guest_name', 'room_no']);

        $orders = $query->orderBy('received_at')->orderBy('id')->get();

        $rows = $orders->map(fn (HotelLaundryOrder $order) => [
            'Date' => $order->received_at?->format('d/m/Y H:i') ?? $order->created_at?->format('d/m/Y H:i') ?? '—',
            'Ticket No' => $order->ticket_no ?: '—',
            'Guest' => $order->guest_name ?: '—',
            'Room' => $order->room_no ?: '—',
            'Guest Type' => $this->serviceGuestType($order->cm_reservation_id),
            'Status' => $order->statusLabel(),
            'Express' => $order->is_express ? 'Yes' : 'No',
            'Total' => $order->formattedTotal($hotel),
            'Payment' => $this->servicePaymentCell($order),
            'Folio Posted' => $order->posted_to_folio ? 'Yes' : 'No',
        ])->all();

        return [
            'rows' => $rows,
            'summary' => $this->buildServiceRevenueSummary($orders, $hotel, HotelLaundryOrder::STATUS_CANCELLED),
        ];
    }

    /** @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, string>>, summary: array<string, mixed>}
     */
    private function swimmingReportBundle(Hotel $hotel, array $filters): array
    {
        $query = HotelSwimmingVisit::query()
            ->where('hotel_id', $hotel->id);

        $this->applySwimmingDateRange($query, $filters);
        $this->applyServiceGuestSearch($query, $filters, ['visit_no', 'guest_name', 'room_no', 'guest_phone', 'guest_email']);

        $visits = $query->orderBy('entered_at')->orderBy('id')->get();

        $rows = $visits->map(function (HotelSwimmingVisit $visit) use ($hotel) {
            $date = $visit->entered_at ?? $visit->scheduled_date;
            $qty = (float) ($visit->billing_quantity ?? $visit->persons ?? 0);

            return [
                'Date' => $date instanceof \DateTimeInterface
                    ? Carbon::instance($date)->format($visit->entered_at ? 'd/m/Y H:i' : 'd/m/Y')
                    : '—',
                'Visit No' => $visit->visit_no ?: '—',
                'Guest' => $visit->guest_name ?: '—',
                'Room' => $visit->room_no ?: '—',
                'Guest Type' => $this->serviceGuestType($visit->cm_reservation_id),
                'Rate' => $visit->rate_label ?: '—',
                'Qty' => $qty > 0 ? (string) (fmod($qty, 1.0) === 0.0 ? (int) $qty : number_format($qty, 2)) : '—',
                'Total' => $visit->formattedTotal($hotel),
                'Status' => $visit->statusLabel(),
                'Payment' => $this->servicePaymentCell($visit),
                'Folio Posted' => $visit->posted_to_folio ? 'Yes' : 'No',
            ];
        })->all();

        return [
            'rows' => $rows,
            'summary' => $this->buildServiceRevenueSummary($visits, $hotel, HotelSwimmingVisit::STATUS_CANCELLED),
        ];
    }

    /** @param  array<string, mixed>  $filters
     * @return array{rows: list<array<string, string>>, summary: array<string, mixed>}
     */
    private function conferenceReportBundle(Hotel $hotel, array $filters): array
    {
        $query = HotelConferenceBooking::query()
            ->where('hotel_id', $hotel->id);

        $this->applyDateRange($query, $filters, 'starts_at');
        $this->applyServiceGuestSearch($query, $filters, ['booking_no', 'event_name', 'organizer_name', 'company_name', 'guest_name', 'contact_email', 'contact_phone']);

        $bookings = $query->orderBy('starts_at')->orderBy('id')->get();

        $rows = $bookings->map(fn (HotelConferenceBooking $booking) => [
            'Start' => $booking->starts_at?->format('d/m/Y H:i') ?? '—',
            'Booking No' => $booking->booking_no ?: '—',
            'Event' => $booking->event_name ?: '—',
            'Organizer' => $booking->organizer_name ?: '—',
            'Company' => $booking->company_name ?: '—',
            'Guest Type' => $this->serviceGuestType($booking->cm_reservation_id),
            'Delegates' => (string) (int) ($booking->delegates ?? 0),
            'Total' => $booking->formattedTotal($hotel),
            'Status' => $booking->statusLabel(),
            'Payment' => $this->servicePaymentCell($booking),
            'Folio Posted' => $booking->posted_to_folio ? 'Yes' : 'No',
        ])->all();

        return [
            'rows' => $rows,
            'summary' => $this->buildServiceRevenueSummary($bookings, $hotel, HotelConferenceBooking::STATUS_CANCELLED),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function applySwimmingDateRange(Builder $query, array $filters): void
    {
        if (empty($filters['from_date']) && empty($filters['to_date'])) {
            return;
        }

        $from = ! empty($filters['from_date'])
            ? Carbon::parse($filters['from_date'])->format('Y-m-d')
            : null;
        $to = ! empty($filters['to_date'])
            ? Carbon::parse($filters['to_date'])->format('Y-m-d')
            : null;

        $query->where(function (Builder $outer) use ($from, $to) {
            $outer->where(function (Builder $entered) use ($from, $to) {
                $entered->whereNotNull('entered_at');
                if ($from !== null) {
                    $entered->whereDate('entered_at', '>=', $from);
                }
                if ($to !== null) {
                    $entered->whereDate('entered_at', '<=', $to);
                }
            })->orWhere(function (Builder $scheduled) use ($from, $to) {
                $scheduled->whereNull('entered_at')->whereNotNull('scheduled_date');
                if ($from !== null) {
                    $scheduled->whereDate('scheduled_date', '>=', $from);
                }
                if ($to !== null) {
                    $scheduled->whereDate('scheduled_date', '<=', $to);
                }
            });
        });
    }

    /** @param  array<string, mixed>  $filters */
    private function applyDateRange(Builder $query, array $filters, string $column): void
    {
        if (! empty($filters['from_date'])) {
            $query->whereDate($column, '>=', Carbon::parse($filters['from_date'])->format('Y-m-d'));
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate($column, '<=', Carbon::parse($filters['to_date'])->format('Y-m-d'));
        }
    }

    /** @param  list<string>  $columns
     * @param  array<string, mixed>  $filters */
    private function applyServiceGuestSearch(Builder $query, array $filters, array $columns): void
    {
        if ($filters['guest_search'] === '') {
            return;
        }

        $term = '%'.$filters['guest_search'].'%';
        $query->where(function (Builder $builder) use ($term, $columns) {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', $term);
            }
        });
    }

    private function serviceGuestType(?int $reservationId): string
    {
        return $reservationId ? 'In-house' : 'Walk-in';
    }

    private function servicePaymentCell(object $record): string
    {
        if (! empty($record->cm_reservation_id)) {
            return 'Room folio';
        }

        $total = (float) ($record->total ?? 0);
        if ($total <= 0.009) {
            return '—';
        }

        $parts = array_filter([
            $record->payment_mode ?? null,
            $record->payment_provider ?? null,
        ]);

        $paid = (float) ($record->amount_paid ?? 0);
        $currency = strtoupper(trim((string) ($record->currency ?? '')));
        if ($paid > 0.009) {
            $parts[] = number_format($paid, 2).($currency !== '' ? ' '.$currency : '').' paid';
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /** @param  Collection<int, HotelLaundryOrder|HotelSwimmingVisit|HotelConferenceBooking>  $records
     * @return array<string, mixed>|null
     */
    private function buildServiceRevenueSummary(Collection $records, Hotel $hotel, string $cancelledStatus): ?array
    {
        $active = $records->reject(fn ($record) => $record->status === $cancelledStatus);

        /** @var array<string, array{subtotal: float, discount: float, surcharge: float, revenue: float, collected: float, folio: float, outstanding: float}> $totals */
        $totals = [];

        foreach ($active as $record) {
            $total = (float) ($record->total ?? 0);
            $isComplimentary = $total <= 0.009
                || ($record instanceof HotelSwimmingVisit && $record->is_complimentary);

            if ($isComplimentary) {
                continue;
            }

            $currency = $this->serviceRecordCurrency($record, $hotel);
            if (! isset($totals[$currency])) {
                $totals[$currency] = [
                    'subtotal' => 0.0,
                    'discount' => 0.0,
                    'surcharge' => 0.0,
                    'revenue' => 0.0,
                    'collected' => 0.0,
                    'folio' => 0.0,
                    'outstanding' => 0.0,
                ];
            }

            $bucket = &$totals[$currency];
            $bucket['revenue'] += $total;
            $bucket['subtotal'] += (float) ($record->subtotal ?? $total);
            $bucket['discount'] += (float) ($record->discount_amount ?? 0);

            if ($record instanceof HotelLaundryOrder) {
                $bucket['surcharge'] += (float) ($record->surcharge_amount ?? 0);
            }

            if (empty($record->cm_reservation_id)) {
                $paid = (float) ($record->amount_paid ?? 0);
                $bucket['collected'] += $paid;
                $bucket['outstanding'] += max(0, round($total - $paid, 2));
            } else {
                $bucket['folio'] += $total;
            }
        }

        if ($totals === []) {
            return null;
        }

        ksort($totals);

        $revenueTotals = [];
        foreach ($totals as $currency => $bucket) {
            $items = [
                ['label' => 'Subtotal', 'value' => $this->formatServiceMoney($bucket['subtotal'], $currency)],
            ];

            if ($bucket['discount'] > 0.009) {
                $items[] = ['label' => 'Discounts', 'value' => $this->formatServiceMoney($bucket['discount'], $currency)];
            }

            if ($bucket['surcharge'] > 0.009) {
                $items[] = ['label' => 'Surcharges', 'value' => $this->formatServiceMoney($bucket['surcharge'], $currency)];
            }

            $items[] = ['label' => 'Net revenue', 'value' => $this->formatServiceMoney($bucket['revenue'], $currency)];
            $items[] = ['label' => 'Walk-in collected', 'value' => $this->formatServiceMoney($bucket['collected'], $currency)];

            if ($bucket['outstanding'] > 0.009) {
                $items[] = ['label' => 'Walk-in outstanding', 'value' => $this->formatServiceMoney($bucket['outstanding'], $currency)];
            }

            $items[] = ['label' => 'In-house (folio)', 'value' => $this->formatServiceMoney($bucket['folio'], $currency)];

            $revenueTotals[] = [
                'currency' => $currency,
                'items' => $items,
            ];
        }

        return [
            'revenue_totals' => $revenueTotals,
        ];
    }

    private function serviceRecordCurrency(object $record, Hotel $hotel): string
    {
        if ($record instanceof HotelLaundryOrder) {
            return $record->displayCurrency($hotel);
        }

        $code = strtoupper(trim((string) ($record->currency ?? '')));
        if ($code !== '') {
            return $code;
        }

        if ($record instanceof HotelSwimmingVisit && $record->relationLoaded('service') === false) {
            $record->loadMissing('service');
        }

        if ($record instanceof HotelSwimmingVisit && $record->service) {
            return $record->service->displayCurrency($hotel);
        }

        return strtoupper($hotel->currency ?? 'TZS');
    }

    private function formatServiceMoney(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency));
        $decimals = in_array($currency, ['TZS', 'UGX', 'RWF', 'BIF'], true) ? 0 : 2;

        return number_format($amount, $decimals).' '.$currency;
    }

}
