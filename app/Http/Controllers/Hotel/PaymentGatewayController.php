<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelLog;
use App\Services\ChannelManager\ChannelManagerCodeResolver;
use App\Services\HotelLogService;
use App\Services\PaymentGatewayDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private PaymentGatewayDataService $payments,
        private ChannelManagerCodeResolver $codes,
        private HotelLogService $logs,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->payments->filtersFromRequest($request);
        $payments = $this->paginateRows(
            $filters['submitted']
                ? $this->payments->rows($hotel->id, $hotelCode, $filters)
                : collect(),
            $request,
            route('hotel.payment-gateway.index'),
            $filters['per_page']
        );

        return view('hotel.pms.payment-gateway.index', [
            'hotel' => $hotel,
            'payments' => $payments,
            'filters' => $filters,
            'ui' => $this->payments->uiConfig(),
            'orderStatusOptions' => $this->payments->orderStatusOptions(),
            'autoSendEnabled' => $this->payments->autoSendEnabled($hotel),
            'bankDetails' => $this->payments->bankDetails($hotel),
        ]);
    }

    public function unsettled(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->payments->filtersFromRequest($request);
        $filters['submitted'] = true;
        $payments = $this->paginateRows(
            $this->payments->unsettledRows($hotel->id, $hotelCode, $filters),
            $request,
            route('hotel.payment-gateway.unsettled'),
            $filters['per_page']
        );

        return view('hotel.pms.payment-gateway.unsettled', [
            'hotel' => $hotel,
            'payments' => $payments,
            'filters' => $filters,
            'ui' => config('hotel_pms.payment_gateway', []),
            'orderStatusOptions' => $this->payments->orderStatusOptions(),
        ]);
    }

    public function updateBankDetails(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:64'],
            'bank_ifsc' => ['nullable', 'string', 'max:32'],
        ]);

        $this->payments->updateBankDetails($hotel, $validated);

        $this->logs->record($hotel, [
            'action_type' => 'Bank Details Updated',
            'category' => HotelLog::CATEGORY_PAYMENTS,
            'details' => 'Payment gateway bank details were updated.',
        ]);

        return redirect()
            ->route('hotel.payment-gateway.index', $request->only([
                'from_date', 'to_date', 'transaction_id', 'booking_id',
                'guest_name', 'order_status', 'payment_link', 'per_page', 'submitted',
            ]))
            ->with('success', 'Bank details saved successfully.');
    }

    public function settlement(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->payments->dateRangeFiltersFromRequest($request);
        $filters['submitted'] = true;
        $rows = $this->paginateRows(
            $this->payments->settlementRows($hotel->id, $hotelCode, $filters),
            $request,
            route('hotel.payment-gateway.settlement'),
            $filters['per_page']
        );

        return view('hotel.pms.payment-gateway.settlement', [
            'hotel' => $hotel,
            'rows' => $rows,
            'filters' => $filters,
            'ui' => config('hotel_pms.payment_gateway.settlement', []),
        ]);
    }

    public function invoices(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $hotelCode = $this->codes->hotelCode($hotel);
        $filters = $this->payments->dateRangeFiltersFromRequest($request);
        $filters['submitted'] = true;
        $rows = $this->paginateRows(
            $this->payments->invoiceRows($hotel->id, $hotelCode, $filters),
            $request,
            route('hotel.payment-gateway.invoices'),
            $filters['per_page']
        );

        return view('hotel.pms.payment-gateway.invoices', [
            'hotel' => $hotel,
            'rows' => $rows,
            'filters' => $filters,
            'ui' => config('hotel_pms.payment_gateway.invoices', []),
        ]);
    }

    public function enableAutoLinks(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $this->payments->setAutoSend($hotel, true);

        return redirect()
            ->route('hotel.payment-gateway.index', $request->only([
                'from_date', 'to_date', 'transaction_id', 'booking_id',
                'guest_name', 'order_status', 'payment_link', 'per_page', 'submitted',
            ]))
            ->with('success', 'Automatic payment links enabled for postpaid bookings.');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'guest_name' => ['nullable', 'string', 'max:160'],
            'invoice_id' => ['nullable', 'string', 'max:80'],
        ]);

        $link = $this->payments->sendPaymentLink($hotel, $validated);

        $this->logs->record($hotel, [
            'action_type' => 'Payment Link Sent',
            'category' => HotelLog::CATEGORY_PAYMENTS,
            'guest_name' => $validated['guest_name'] ?? null,
            'folio_no' => $validated['invoice_id'] ?? null,
            'details' => 'Payment link sent to '.$link->email.' for amount '.$link->amount.'.',
        ]);

        return redirect()
            ->route('hotel.payment-gateway.index', $request->only([
                'from_date', 'to_date', 'transaction_id', 'booking_id',
                'guest_name', 'order_status', 'payment_link', 'per_page', 'submitted',
            ]))
            ->with('success', 'Payment link sent to '.$link->email.'.');
    }

    /** @param  \Illuminate\Support\Collection<int, mixed>  $allRows */
    private function paginateRows($allRows, Request $request, string $path, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->input('page', 1));
        $total = $allRows->count();
        $items = $allRows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $path, 'query' => $request->query()]
        );
    }
}
