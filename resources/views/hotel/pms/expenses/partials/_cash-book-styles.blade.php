<style>
  :root {
    --ex-brand: #940000;
    --ex-brand-dark: #7a0000;
  }

  .ex-page { background: #fff; }

  .cb-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px 0;
  }

  .cb-title {
    margin: 0;
    font-size: 22px;
    font-weight: 400;
    color: #333;
  }

  .cb-back {
    font-weight: 600;
  }

  .ex-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px;
    padding: 16px 20px;
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

  .ex-field--date input { min-width: 150px; }
  .ex-field--select select { min-width: 130px; }

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

  .cb-content {
    padding: 0 20px 28px;
  }

  .ex-empty {
    color: var(--ex-brand);
    font-size: 16px;
    font-weight: 600;
    padding: 24px 0;
  }

  .ex-table-wrap { overflow-x: auto; }

  .ex-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
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
    padding: 9px 12px;
    font-size: 13px;
    background: #fff;
    vertical-align: middle;
  }

  .cb-date-row td {
    background: #e9ecef !important;
    font-weight: 700;
    color: #333;
    border-color: #dee2e6;
  }

  .cb-balance-row td {
    background: #f8f9fa;
    border-top: 2px solid #dee2e6;
  }

  .ex-table tbody tr:hover td:not(.cb-date-row td) {
    background: #fafafa;
  }

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
  }

  .ex-file-browse {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 88px;
    padding: 0 12px;
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 0 3px 3px 0;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
  }

  .js-ex-expense-submit:not(:disabled),
  .js-ex-deposit-submit:not(:disabled) {
    background: var(--ex-brand) !important;
    border-color: var(--ex-brand) !important;
    color: #fff !important;
  }

  .js-ex-expense-submit:disabled,
  .js-ex-deposit-submit:disabled {
    background: #adb5bd !important;
    border-color: #adb5bd !important;
    color: #fff !important;
    opacity: 1;
  }

  #exAddExpenseModal .control-label,
  #exDepositModal .control-label {
    font-size: 13px;
    font-weight: 600;
    color: #444;
  }

  #exAddExpenseModal .modal-footer,
  #exDepositModal .modal-footer {
    border-top: none;
    padding-top: 0;
  }
</style>
