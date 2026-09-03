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
        ]);

        $this->expenses->createExpense(
            $hotel,
            array_merge($validated, ['paid_type' => HotelExpense::PAID_OUT]),
            $request->file('details'),
        );

        return redirect()
            ->route('hotel.expenses.index', [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'payment_type' => $request->input('filter_payment_type'),
                'paid_type' => $request->input('filter_paid_type'),
            ])
            ->with('success', 'Expense added successfully.');
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

        return redirect()
            ->route('hotel.expenses.index', [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'payment_type' => $request->input('filter_payment_type'),
                'paid_type' => $request->input('filter_paid_type'),
            ])
            ->with('success', 'Deposit recorded successfully.');
    }
}
