@extends('layouts.app')

@section('title', 'Expenses')

@push('styles')
  @include('hotel.pms.expenses.partials._cash-book-styles')
@endpush

@section('content')
  <div class="tile ex-page mb-0">
    @if(session('success'))
      <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
    @endif

    <div class="cb-head">
      <h3 class="cb-title">Expenses</h3>
      <a href="{{ $backUrl }}" class="btn btn-light btn-sm cb-back">Back</a>
    </div>

    <form method="GET" action="{{ route('hotel.stay-view.cash-book') }}" id="cbFilterForm">
      <div class="ex-toolbar cb-toolbar">
        <div class="ex-field ex-field--date">
          <label for="from_date">From Date:</label>
          <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $filters['from_date'] }}">
        </div>
        <div class="ex-field ex-field--date">
          <label for="to_date">To Date:</label>
          <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $filters['to_date'] }}">
        </div>
        <div class="ex-field ex-field--select">
          <label for="payment_type">Type:</label>
          <select class="form-control" id="payment_type" name="payment_type">
            @foreach($options['payment_types'] ?? [] as $value => $label)
              <option value="{{ $value }}" @selected($filters['payment_type'] === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="ex-actions">
          <button type="button" class="btn btn-ex" data-toggle="modal" data-target="#exAddExpenseModal">Add Expense</button>
          <button type="button" class="btn btn-ex" data-toggle="modal" data-target="#exDepositModal">Deposit</button>
        </div>
      </div>
    </form>

    <div class="cb-content">
      @if($ledger['groups'] === [])
        <div class="ex-empty">{{ $ledger['empty_message'] }}</div>
      @else
        <div class="ex-table-wrap">
          <table class="ex-table cb-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>POS</th>
                <th>Guest</th>
                <th>Room No.</th>
                <th>Type</th>
                <th>Category</th>
                <th>Expense Date</th>
                <th>Invoice No.</th>
                <th>Details</th>
                <th>Comments</th>
                <th class="text-right">Paid in</th>
                <th class="text-right">Paid out</th>
                <th class="text-right">Balance</th>
                <th style="width:40px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($ledger['groups'] as $group)
                <tr class="cb-date-row">
                  <td colspan="14">{{ $group['label'] }}</td>
                </tr>
                @foreach($group['rows'] as $row)
                  <tr>
                    <td class="text-nowrap">{{ $row['time'] }}</td>
                    <td>{{ $row['pos'] ?: '—' }}</td>
                    <td>{{ $row['guest'] ?: '—' }}</td>
                    <td>{{ $row['room_no'] ?: '—' }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['category'] ?: '—' }}</td>
                    <td>{{ $row['expense_date'] ?: '—' }}</td>
                    <td>{{ $row['invoice_no'] ?: '—' }}</td>
                    <td>
                      @if($row['details_url'])
                        <a href="{{ $row['details_url'] }}" target="_blank" rel="noopener">View</a>
                      @else
                        —
                      @endif
                    </td>
                    <td>{{ \Illuminate\Support\Str::limit($row['comments'] ?: '—', 48) }}</td>
                    <td class="text-right">{{ $row['paid_in'] > 0 ? number_format($row['paid_in'], 2) : '' }}</td>
                    <td class="text-right">{{ $row['paid_out'] > 0 ? number_format($row['paid_out'], 2) : '' }}</td>
                    <td class="text-right"></td>
                    <td class="text-center">
                      <form method="POST" action="{{ route('hotel.expenses.destroy', $row['id']) }}" class="d-inline js-swal-delete">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                        <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                        <input type="hidden" name="payment_type" value="{{ $filters['payment_type'] }}">
                        <input type="hidden" name="redirect" value="cash_book">
                        <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Delete"
                          data-title="Delete entry?"
                          data-text="Remove this cash book line?"
                          data-confirm="Yes, delete">
                          <i class="fa fa-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach
                <tr class="cb-balance-row">
                  <td colspan="12"></td>
                  <td class="text-right font-weight-bold">{{ number_format((float) $group['closing_balance'], 2) }}</td>
                  <td></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  @php $cashBook = true; @endphp
  @include('hotel.pms.expenses.partials._add-expense-modal')
  @include('hotel.pms.expenses.partials._deposit-modal')
@endsection

@push('scripts')
  <script>
    (function () {
      document.getElementById('cbFilterForm')?.querySelectorAll('select, input[type="date"]').forEach(function (field) {
        field.addEventListener('change', function () {
          document.getElementById('cbFilterForm').submit();
        });
      });

      function setupModalForm(formId, submitClass) {
        var form = document.getElementById(formId);
        if (!form) return;
        var submitBtn = form.querySelector('.' + submitClass);
        var paymentType = form.querySelector('select[name="payment_type"]');
        var amount = form.querySelector('input[name="amount"]');
        var category = form.querySelector('select[name="category"]');
        var expenseDate = form.querySelector('input[name="expense_date"]');

        function syncSubmitState() {
          var valid = paymentType && paymentType.value !== '' && amount && parseFloat(amount.value) > 0;
          if (category) valid = valid && category.value !== '';
          if (expenseDate) valid = valid && expenseDate.value !== '';
          if (submitBtn) submitBtn.disabled = !valid;
        }

        form.querySelectorAll('select, input').forEach(function (field) {
          field.addEventListener('change', syncSubmitState);
          field.addEventListener('input', syncSubmitState);
        });

        form.querySelectorAll('.js-ex-file-input').forEach(function (input) {
          input.addEventListener('change', function () {
            var label = input.closest('.ex-file-input')?.querySelector('.js-ex-file-label');
            if (label) label.value = input.files[0] ? input.files[0].name : '';
          });
        });

        syncSubmitState();
      }

      setupModalForm('exAddExpenseForm', 'js-ex-expense-submit');
      setupModalForm('exDepositForm', 'js-ex-deposit-submit');
    })();
  </script>
@endpush
