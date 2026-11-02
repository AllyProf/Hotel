<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\CmReservation;
use App\Models\HotelCompany;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\CheckOutService;
use App\Services\CompanyDataService;
use App\Services\GuestStayFolioPrintService;
use App\Services\GuestStaySummaryService;
use App\Services\HotelLogService;
use App\Services\LostFoundNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CheckOutController extends Controller
{
    public function __construct(
        private CheckOutService $checkOut,
        private GuestStaySummaryService $staySummaries,
        private GuestStayFolioPrintService $stayFolioPrint,
        private HotelLogService $logs,
        private LostFoundNotificationService $lostFoundNotifications,
        private CompanyDataService $companies,
        private ChannelManagerCodeResolver $codes,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $date = Carbon::parse($request->input('date', now()->format('Y-m-d')))->startOfDay();
        $tab = (string) $request->input('tab', 'departures');

        if (! in_array($tab, ['departures', 'in_house', 'checked_out'], true)) {
            $tab = 'departures';
        }

        $data = $this->checkOut->listForHotel($hotel, $date, $tab);
        $hotelCode = $this->codes->hotelCode($hotel);

        return view('hotel.pms.check-out.index', [
            'hotel' => $hotel,
            'companyReceivables' => $this->companies->billingStatsByCompany($hotel, $hotelCode),
            'date' => $data['date'],
            'tab' => $data['tab'],
            'stats' => $data['stats'],
            'bookings' => $data['bookings'],
            'frontDesk' => $data['front_desk'],
            'checkoutContexts' => $this->checkOut->checkoutContexts($hotel, $data['bookings']),
            'foundItemsByReservation' => $this->lostFoundNotifications->openItemsByReservation($hotel, $data['bookings']),
            'paymentModes' => $this->checkOut->paymentModes($hotel),
            'paymentProviderConfig' => $this->checkOut->paymentProviderConfig($hotel),
            'companies' => $this->checkOut->companiesForHotel($hotel),
            'ui' => $this->checkOut->uiConfig(),
        ]);
    }

    public function stay(Request $request, CmReservation $reservation): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_if((int) $reservation->hotel_id !== (int) $hotel->id, 404);

        $date = Carbon::parse($request->input('date', now()->format('Y-m-d')))->startOfDay();
        $tab = (string) $request->input('tab', 'in_house');
        $summary = $this->staySummaries->summaryForBooking($hotel, $reservation, $date);
        $payment = $this->checkOut->paymentContext($reservation);

        $foundInRoom = collect($this->lostFoundNotifications->openItemsByReservation($hotel, collect([$reservation]))[$reservation->id] ?? []);

        return view('hotel.pms.check-out.stay', [
            'hotel' => $hotel,
            'booking' => $reservation,
            'summary' => $summary,
            'payment' => $payment,
            'foundInRoom' => $foundInRoom,
            'date' => $date,
            'tab' => $tab,
            'backUrl' => route('hotel.check-out.index', [
                'tab' => $tab,
                'date' => $date->format('Y-m-d'),
            ]),
            'printAllUrl' => route('hotel.check-out.stay.print', [
                'reservation' => $reservation,
                'date' => $date->format('Y-m-d'),
                'tab' => $tab,
            ]),
            'ui' => $this->checkOut->uiConfig(),
        ]);
    }

    public function printStay(Request $request, CmReservation $reservation): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_if((int) $reservation->hotel_id !== (int) $hotel->id, 404);

        $date = Carbon::parse($request->input('date', now()->format('Y-m-d')))->startOfDay();
        $tab = (string) $request->input('tab', 'in_house');
        $folio = $this->stayFolioPrint->build($hotel, $reservation, $date);

        return view('hotel.pms.check-out.print-stay', [
            'hotel' => $hotel,
            'booking' => $reservation,
            'folio' => $folio,
            'formatter' => $this->stayFolioPrint,
            'stayUrl' => route('hotel.check-out.stay', [
                'reservation' => $reservation,
                'date' => $date->format('Y-m-d'),
                'tab' => $tab,
            ]),
        ]);
    }

    public function pay(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_if((int) $reservation->hotel_id !== (int) $hotel->id, 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'string', 'max:80'],
            'payment_provider' => ['nullable', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:80'],
            'bill_to_company_id' => ['nullable', 'integer'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
            'tab' => ['nullable', 'string'],
        ]);

        $pendingLateFee = $request->filled('late_fee_amount') ? (float) $validated['late_fee_amount'] : null;

        try {
            $updated = $this->checkOut->collectPayment(
                $hotel,
                $reservation,
                (float) $validated['amount'],
                $validated['payment_mode'],
                $pendingLateFee,
                $validated['payment_provider'] ?? null,
                $validated['payment_reference'] ?? null,
                isset($validated['bill_to_company_id']) ? (int) $validated['bill_to_company_id'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectBack($request, $e->getMessage(), 'error');
        }

        $paymentDetails = $validated['payment_mode'];
        if (! empty($validated['payment_provider'])) {
            $paymentDetails .= ' ('.$validated['payment_provider'].')';
        }
        if (! empty($validated['payment_reference'])) {
            $paymentDetails .= ', ref '.$validated['payment_reference'];
        }
        if (! empty($validated['bill_to_company_id'])) {
            $companyName = HotelCompany::query()
                ->where('hotel_id', $hotel->id)
                ->whereKey($validated['bill_to_company_id'])
                ->value('name');
            if ($companyName) {
                $paymentDetails .= ', billed to '.$companyName;
            }
        }

        $this->logs->record($hotel, [
            'action_type' => 'Checkout Payment',
            'details' => "Collected {$validated['amount']} {$updated->currency} via {$paymentDetails} for {$updated->guestName()} ({$updated->booking_id}).",
        ]);

        return $this->redirectBack($request, 'Payment recorded successfully.');
    }

    public function sendLink(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_if((int) $reservation->hotel_id !== (int) $hotel->id, 404);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
            'tab' => ['nullable', 'string'],
        ]);

        $pendingLateFee = $request->filled('late_fee_amount') ? (float) $validated['late_fee_amount'] : null;

        try {
            $link = $this->checkOut->sendPaymentLink(
                $hotel,
                $reservation,
                $validated['email'],
                $validated['phone'],
                (float) $validated['amount'],
                $pendingLateFee,
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectBack($request, $e->getMessage(), 'error');
        }

        $this->logs->record($hotel, [
            'action_type' => 'Payment Link Sent',
            'details' => "Payment link sent to {$link->email} for {$link->amount} {$reservation->currency} ({$reservation->booking_id}).",
        ]);

        return $this->redirectBack($request, 'Payment link sent to '.$link->email.'.');
    }

    public function process(Request $request, CmReservation $reservation): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_if((int) $reservation->hotel_id !== (int) $hotel->id, 404);

        $validated = $request->validate([
            'apply_late_fee' => ['nullable', 'boolean'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
            'tab' => ['nullable', 'string'],
        ]);

        try {
            $updated = $this->checkOut->checkOut(
                $hotel,
                $reservation,
                $request->boolean('apply_late_fee'),
                isset($validated['late_fee_amount']) ? (float) $validated['late_fee_amount'] : null,
            );
        } catch (InvalidArgumentException $e) {
            return $this->redirectBack($request, $e->getMessage(), 'error');
        }

        $details = "Checked out {$updated->guestName()} — booking {$updated->booking_id}.";

        if ((float) ($updated->late_checkout_fee ?? 0) > 0) {
            $details .= ' Late checkout fee: '.number_format((float) $updated->late_checkout_fee, 2).' '.$updated->currency.'.';
        }

        $this->logs->record($hotel, [
            'action_type' => 'Guest Check-out',
            'details' => $details,
        ]);

        return redirect()
            ->route('hotel.check-out.index', array_filter([
                'date' => $request->input('date'),
                'tab' => 'checked_out',
            ]))
            ->with('success', 'Guest checked out successfully.');
    }

    private function redirectBack(Request $request, string $message, string $type = 'success'): RedirectResponse
    {
        return redirect()
            ->route('hotel.check-out.index', array_filter([
                'date' => $request->input('date'),
                'tab' => $request->input('tab', 'departures'),
            ]))
            ->with($type, $message);
    }
}
