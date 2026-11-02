<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelExpense;
use App\Services\ExpenseDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseDataService $expenses,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->expenses->filtersFromRequest($request);
        $expenseList = $this->expenses->query($hotel->id, $filters)->get();

        return view('hotel.pms.expenses.index', [
            'hotel' => $hotel,
            'expenses' => $expenseList,
            'filters' => $filters,
            'options' => $this->expenses->formOptions($hotel),
            'ui' => $this->expenses->uiConfig(),
            'emptyMessage' => $this->expenses->emptyMessage($filters),
            'cashBook' => false,
        ]);
    }

    public function cashBook(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->expenses->filtersForCashBook($request);
        $ledger = $this->expenses->cashBookLedger($hotel->id, $filters);

        $stayStart = $request->input('stay_start');
        $backUrl = $stayStart
            ? route('hotel.stay-view', ['start' => $stayStart])
            : route('hotel.stay-view');

        return view('hotel.pms.expenses.cash-book', [
            'hotel' => $hotel,
            'filters' => $filters,
            'ledger' => $ledger,
            'options' => $this->expenses->formOptions($hotel),
            'backUrl' => $backUrl,
            'cashBook' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'payment_type' => ['required', 'in:'.implode(',', HotelExpense::PAYMENT_TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['required', 'string', 'max:120'],
            'expense_date' => ['required', 'date'],
            'invoice_no' => ['nullable', 'string', 'max:80'],
            'vendor' => ['nullable', 'string', 'max:160'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'details' => ['nullable', 'file', 'max:10240'],
            'pos_label' => ['nullable', 'string', 'max:80'],
            'guest_name' => ['nullable', 'string', 'max:160'],
            'room_no' => ['nullable', 'string', 'max:40'],
        ]);

        $this->expenses->createExpense(
            $hotel,
            array_merge($validated, ['paid_type' => HotelExpense::PAID_OUT]),
            $request->file('details'),
        );

        return $this->redirectAfterExpense($request)->with('success', 'Expense added successfully.');
    }

    public function storeDeposit(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();

        $validated = $request->validate([
            'payment_type' => ['required', 'in:'.implode(',', HotelExpense::PAYMENT_TYPES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'details' => ['nullable', 'file', 'max:10240'],
        ]);

        $this->expenses->createDeposit($hotel, $validated, $request->file('details'));

        return $this->redirectAfterExpense($request)->with('success', 'Deposit recorded successfully.');
    }

    public function destroy(Request $request, HotelExpense $expense): RedirectResponse
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        abort_unless((int) $expense->hotel_id === (int) $hotel->id, 404);

        $expense->delete();

        return $this->redirectAfterExpense($request)->with('success', 'Entry removed.');
    }

    private function redirectAfterExpense(Request $request): RedirectResponse
    {
        $params = [
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'payment_type' => $request->input('filter_payment_type', $request->input('payment_type', 'cash')),
        ];

        if ($request->input('redirect') === 'cash_book') {
            if ($request->filled('stay_start')) {
                $params['stay_start'] = $request->input('stay_start');
            }

            return redirect()->route('hotel.stay-view.cash-book', array_filter($params));
        }

        $params['paid_type'] = $request->input('filter_paid_type', '');

        return redirect()->route('hotel.expenses.index', array_filter($params));
    }
}
