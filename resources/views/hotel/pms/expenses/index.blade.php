@extends('layouts.app')

@section('title', $ui['title'] ?? 'Expenses')

@push('styles')
  <style>
    :root {
      --ex-brand: #940000;
      --ex-brand-dark: #7a0000;
    }

    .ex-page { background: #fff; }

    .ex-header {
      padding: 18px 20px 0;
    }

    .ex-header h3 {
      margin: 0 0 16px;
      font-size: 22px;
      font-weight: 400;
      color: #333;
    }

    .ex-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 14px;
      padding: 0 20px 16px;
      border-bottom: 3px solid var(--ex-brand);
    }

    .ex-field label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #333;
      margin-bottom: 4px;
    }

    .ex-field .form-control {
      min-height: 36px;
      font-size: 13px;
    }

    .ex-field--date input {
      min-width: 150px;
    }

    .ex-field--select select {
      min-width: 130px;
    }

    .ex-actions {
      display: flex;
      gap: 10px;
      margin-left: auto;
    }

    .btn-ex {
      background: var(--ex-brand) !important;
      border-color: var(--ex-brand) !important;
      color: #fff !important;
      font-size: 13px;
      font-weight: 600;
      padding: 7px 18px;
      min-height: 36px;
      border-radius: 3px;
    }

    .btn-ex:hover {
      background: var(--ex-brand-dark) !important;
      border-color: var(--ex-brand-dark) !important;
      color: #fff !important;
    }

    .ex-content {
      padding: 24px 20px 28px;
      min-height: 180px;
    }

    .ex-empty {
      color: var(--ex-brand);
      font-size: 16px;
      font-weight: 600;
      padding: 8px 0;
    }

    .ex-table-wrap {
      overflow-x: auto;
    }

    .ex-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 900px;
    }

    .ex-table thead th {
      background: #343a40;
      color: #fff;
      font-size: 12px;
      font-weight: 600;
      padding: 10px 12px;
      border: 1px solid #2d3238;
      white-space: nowrap;
    }

    .ex-table tbody td {
      border: 1px solid #dee2e6;
      padding: 10px 12px;
      font-size: 13px;
      background: #fff;
    }

    .ex-table tbody tr:hover td { background: #fafafa; }

    .ex-modal__header {
      background: #fff;
      color: #333;
      border-bottom: none;
      padding-bottom: 0;
    }

    .ex-modal__header .modal-title {
      font-size: 22px;
      font-weight: 400;
    }

    .ex-modal__divider {
      height: 3px;
      background: var(--ex-brand);
      margin: 0 0 20px;
    }

    .ex-file-input {
      display: flex;
      align-items: stretch;
    }

    .ex-file-input .form-control {
      border-radius: 3px 0 0 3px;
      border-right: none;
      background: #fff;
    }

    .ex-file-browse {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 88px;
      margin: 0;
      padding: 0 12px;
      background: #e9ecef;
      border: 1px solid #ced4da;
      border-radius: 0 3px 3px 0;
      font-size: 12px;
      font-weight: 700;
      color: #495057;
      cursor: pointer;
      white-space: nowrap;
    }

    .ex-file-browse:hover {
      background: #dee2e6;
    }

    .js-ex-expense-submit:not(:disabled),
    .js-ex-deposit-submit:not(:disabled) {
      background: var(--ex-brand) !important;
      border-color: var(--ex-brand) !important;
      color: #fff !important;
    }
  </style>
@endpush

@section('content')
  <div class="app-title">
    <div>
      <h1><i class="fa fa-dollar"></i> {{ $ui['title'] ?? 'Expenses' }}</h1>
      <p>Track hotel expenses, deposits, and payments</p>
    </div>
    <ul class="app-breadcrumb breadcrumb">
      <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
      <li class="breadcrumb-item"><a href="{{ route('hotel.dashboard') }}">PMS</a></li>
      <li class="breadcrumb-item">{{ $ui['title'] ?? 'Expenses' }}</li>
    </ul>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="tile ex-page">
        @if(session('success'))
          <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif

        <div class="ex-header">
          <h3>{{ $ui['title'] ?? 'Expenses' }}</h3>
        </div>

        <form method="GET" action="{{ route('hotel.expenses.index') }}" id="exFilterForm">
          <div class="ex-toolbar">
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
                <option value="" {{ $filters['payment_type'] === '' ? 'selected' : '' }}>All</option>
                @foreach($options['payment_types'] ?? [] as $value => $label)
                  <option value="{{ $value }}" {{ $filters['payment_type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="ex-field ex-field--select">
              <label for="paid_type">Paid Type:</label>
              <select class="form-control" id="paid_type" name="paid_type">
                <option value="" {{ $filters['paid_type'] === '' ? 'selected' : '' }}>All</option>
                @foreach($options['paid_types'] ?? [] as $value => $label)
                  <option value="{{ $value }}" {{ $filters['paid_type'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="ex-actions">
              <button type="button" class="btn btn-ex" data-toggle="modal" data-target="#exAddExpenseModal">
                Add Expense
              </button>
              <button type="button" class="btn btn-ex" data-toggle="modal" data-target="#exDepositModal">
                Deposit
              </button>
            </div>
          </div>
        </form>

        <div class="ex-content">
          @if($expenses->isEmpty())
            <div class="ex-empty">{{ $emptyMessage }}</div>
          @else
            <div class="ex-table-wrap">
              <table class="ex-table">
                <thead>
                  <tr>
                    @foreach($ui['columns'] ?? [] as $column)
                      <th>{{ $column['label'] }}</th>
                    @endforeach
                  </tr>
                </thead>
                <tbody>
                  @foreach($expenses as $expense)
                    <tr>
                      <td>{{ $expense->expense_date?->format('d M Y') ?? $expense->created_at->format('d M Y') }}</td>
                      <td>{{ $expense->entryTypeLabel() }}</td>
                      <td>{{ $expense->paymentTypeLabel() }}</td>
                      <td>{{ $expense->paidTypeLabel() }}</td>
                      <td>{{ $expense->category ?: '—' }}</td>
                      <td>{{ number_format((float) $expense->amount, 2) }}</td>
                      <td>{{ $expense->vendor ?: '—' }}</td>
                      <td>{{ $expense->invoice_no ?: '—' }}</td>
                      <td>{{ \Illuminate\Support\Str::limit($expense->comments ?: '—', 40) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  @include('hotel.pms.expenses.partials._add-expense-modal')
  @include('hotel.pms.expenses.partials._deposit-modal')
@endsection

@push('scripts')
  <script>
    (function () {
      function setupModalForm(formId, submitClass) {
        var form = document.getElementById(formId);
        if (!form) return;

        var submitBtn = form.querySelector('.' + submitClass);
        var paymentType = form.querySelector('select[name="payment_type"]');
        var amount = form.querySelector('input[name="amount"]');
        var category = form.querySelector('select[name="category"]');
        var expenseDate = form.querySelector('input[name="expense_date"]');

        function syncSubmitState() {
          var valid = paymentType && paymentType.value !== ''
            && amount && parseFloat(amount.value) > 0;

          if (category) {
            valid = valid && category.value !== '';
          }

          if (expenseDate) {
            valid = valid && expenseDate.value !== '';
          }

          if (submitBtn) {
            submitBtn.disabled = !valid;
          }
        }

        form.querySelectorAll('select, input').forEach(function (field) {
          field.addEventListener('change', syncSubmitState);
          field.addEventListener('input', syncSubmitState);
        });

        form.querySelectorAll('.js-ex-file-input').forEach(function (input) {
          input.addEventListener('change', function () {
            var label = input.closest('.ex-file-input')?.querySelector('.js-ex-file-label');
            if (label) {
              label.value = input.files[0] ? input.files[0].name : '';
            }
          });
        });

        syncSubmitState();
      }

      document.getElementById('exFilterForm')?.querySelectorAll('select, input[type="date"]').forEach(function (field) {
        field.addEventListener('change', function () {
          document.getElementById('exFilterForm').submit();
        });
      });

      setupModalForm('exAddExpenseForm', 'js-ex-expense-submit');
      setupModalForm('exDepositForm', 'js-ex-deposit-submit');
    })();
  </script>
@endpush
