<style>
  :root { --pos-brand: #940000; --pos-brand-dark: #7a0000; }
  .pos-page .pos-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
  }
  .pos-filters-fields,
  .pos-filters-report {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
  }
  .pos-filters-report {
    margin-left: auto;
  }
  .pos-page .pos-filters .pos-filter-outlet {
    min-width: 160px;
  }
  .pos-page .pos-filters .btn-pos-report {
    min-width: 110px;
    white-space: nowrap;
  }
  .pos-page .pos-filters label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
    color: #333;
  }
  .pos-page .pos-filters .form-control {
    min-height: 36px;
    font-size: 13px;
  }
  .pos-kpi-row {
    padding: 16px 12px 8px;
    margin-left: 0;
    margin-right: 0;
    align-items: stretch;
  }
  .pos-kpi-row .pos-kpi-col {
    display: flex;
    margin-bottom: 12px;
  }
  .pos-stat-box {
    flex: 1 1 auto;
    width: 100%;
    min-height: 92px;
    height: 100%;
    font-size: 13px;
    border-radius: 4px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
  }
  .pos-stat-box strong {
    display: block;
    margin-bottom: 4px;
    line-height: 1.3;
  }
  .pos-stat-value {
    line-height: 1.35;
  }
  .pos-stat-sub {
    font-size: 12px;
    line-height: 1.35;
    min-height: 1.35em;
    margin-top: auto;
    padding-top: 4px;
    opacity: 0.9;
  }
  .pos-section-head {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    background: #f8f9fa;
    border-top: 1px solid #e5e7eb;
  }
  .pos-section-head h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #333;
  }
  .pos-section-tools {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-left: auto;
  }
  .pos-section-search {
    width: 180px;
    min-height: 36px;
    font-size: 13px;
  }
  .btn-pos-complimentary {
    background: #e9ecef !important;
    border: 1px solid #adb5bd !important;
    color: #212529 !important;
    font-size: 13px;
    font-weight: 600;
    min-height: 36px;
    padding-left: 16px;
    padding-right: 16px;
  }
  .btn-pos-complimentary:hover {
    background: #dee2e6 !important;
    border-color: #868e96 !important;
    color: #212529 !important;
  }
  .pos-order-actions-cell { min-width: 280px; }
  .pos-order-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
  }
  .pos-order-actions .btn {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
  }
  .pos-status-paid {
    font-size: 12px;
    font-weight: 600;
    padding: 0.35em 0.65em;
  }
  .pos-status-unpaid {
    font-size: 12px;
    font-weight: 600;
    padding: 0.35em 0.65em;
  }
  .pos-table-wrap { padding: 0 20px 20px; }
  .pos-table thead th {
    background: var(--pos-brand);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 10px 12px;
    border-color: var(--pos-brand-dark);
    white-space: nowrap;
  }
  .pos-table tbody td {
    font-size: 13px;
    padding: 10px 12px;
    vertical-align: middle;
  }
  .pos-table tfoot td {
    background: #6c757d;
    color: #fff;
    font-weight: 700;
    padding: 10px 12px;
  }
  .btn-pos {
    background: var(--pos-brand) !important;
    border-color: var(--pos-brand) !important;
    color: #fff !important;
    font-size: 13px;
    font-weight: 600;
    min-height: 36px;
  }
  .btn-pos:hover {
    background: var(--pos-brand-dark) !important;
    border-color: var(--pos-brand-dark) !important;
    color: #fff !important;
  }
  .btn-pos-outline {
    background: #fff !important;
    border: 1px solid #ccc !important;
    color: #333 !important;
    font-size: 13px;
    min-height: 36px;
  }
  .pos-modal-header {
    background: var(--pos-brand);
    color: #fff;
    border-bottom: none;
  }
  .pos-modal-header .close { color: #fff; opacity: 1; text-shadow: none; }
  /* Theme Bootstrap has no .modal-xl — default dialog is only 500px wide */
  .pos-new-order-modal .pos-new-order-dialog {
    max-width: 96vw;
    width: 96vw;
    margin: 1.25rem auto;
  }
  @media (min-width: 992px) {
    .pos-new-order-modal .pos-new-order-dialog {
      max-width: min(1140px, 92vw);
      width: auto;
    }
  }
  @media (min-width: 1400px) {
    .pos-new-order-modal .pos-new-order-dialog {
      max-width: 1200px;
    }
  }
  .pos-new-order-modal .modal-body {
    padding: 20px 24px 24px;
    overflow: visible;
  }
  .pos-new-order-modal .modal-content {
    overflow: visible;
  }
  .pos-new-order-modal .pos-line-entry-grid {
    overflow: visible;
    display: grid;
    grid-template-columns:
      minmax(160px, 1.15fr)
      minmax(200px, 2fr)
      88px
      minmax(120px, 1fr)
      minmax(120px, 1fr)
      minmax(120px, 1fr)
      48px;
    gap: 12px 14px;
    align-items: end;
  }
  @media (max-width: 991px) {
    .pos-new-order-modal .pos-line-entry-grid {
      grid-template-columns: 1fr 1fr;
    }
    .pos-new-order-modal .pos-line-field-add {
      grid-column: 1 / -1;
      justify-self: start;
    }
  }
  .pos-new-order-modal .pos-line-field .form-control,
  .pos-new-order-modal .pos-line-field select.form-control {
    width: 100%;
    min-width: 0;
  }
  .pos-new-order-modal #posLineCategory {
    text-overflow: ellipsis;
  }
  .pos-new-order-modal .pos-line-field-add {
    display: flex;
    align-items: flex-end;
    padding-bottom: 1px;
  }
  .pos-new-order-modal .pos-new-order-head {
    display: flex;
    flex-wrap: wrap;
    background: #fff;
    color: #333;
    border-bottom: 1px solid #e5e7eb;
    padding: 16px 20px;
    align-items: center;
  }
  .pos-new-order-modal .pos-new-order-head .modal-title {
    font-size: 18px;
    font-weight: 600;
  }
  .pos-new-order-modal .pos-new-order-head-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: auto;
  }
  .pos-new-order-modal .pos-new-order-head .close {
    color: #333;
    opacity: 0.7;
    font-size: 1.5rem;
    margin: 0;
    padding: 0 0 0 4px;
  }
  .pos-new-order-modal .pos-new-order-head .close:hover { opacity: 1; }
  .pos-modal-divider {
    height: 3px;
    background: var(--pos-brand);
    margin: 0 0 20px;
  }
  .pos-guest-panel {
    padding: 14px 16px 16px;
    background: #fafbfc;
    border: 1px solid #e9ecef;
    border-radius: 6px;
  }
  .pos-guest-panel__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 16px;
    margin-bottom: 12px;
  }
  .pos-guest-panel__title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #495057;
  }
  .pos-guest-type-row {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }
  .pos-guest-type-option {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #495057;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    cursor: pointer;
    user-select: none;
  }
  .pos-guest-type-option input {
    margin: 0;
    flex-shrink: 0;
  }
  .pos-guest-type-option.is-checked {
    border-color: var(--pos-brand);
    background: rgba(148, 0, 0, 0.06);
    color: #333;
  }
  .pos-guest-panel__fields {
    margin-left: 0;
    margin-right: 0;
  }
  .pos-guest-panel__fields > [class*="col-"] {
    padding-left: 0;
    padding-right: 0;
  }
  @media (min-width: 768px) {
    .pos-guest-panel__fields > .pr-md-2 {
      padding-right: 8px !important;
    }
    .pos-guest-panel__fields > .pl-md-2 {
      padding-left: 8px !important;
    }
  }
  .pos-guest-field .control-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
    line-height: 1.3;
    color: #333;
  }
  .pos-guest-field .control-label .text-muted {
    font-size: 11px;
    font-weight: 400;
    margin-left: 4px;
  }
  .pos-guest-field .form-control {
    min-height: 36px;
    font-size: 13px;
  }
  .pos-guest-search-cell {
    z-index: 30;
  }
  @media (max-width: 767px) {
    .pos-guest-panel__fields > .col-md-5 {
      margin-bottom: 12px;
    }
    .pos-guest-panel__fields > .pr-md-2,
    .pos-guest-panel__fields > .pl-md-2 {
      padding-left: 0 !important;
      padding-right: 0 !important;
    }
  }
  .pos-readonly-field {
    background-color: #f1f3f5 !important;
    cursor: default;
  }
  .pos-new-order-modal .pos-line-field .control-label {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
    white-space: nowrap;
  }
  .pos-new-order-modal .pos-line-field .form-control {
    min-height: 36px;
    font-size: 13px;
  }
  .pos-add-line-btn {
    width: 38px;
    height: 38px;
    min-height: 38px;
    border-radius: 50%;
    padding: 0;
    line-height: 36px;
    flex-shrink: 0;
  }
  .pos-lines-actions-col { width: 40px; }
  .pos-new-order-modal .pos-service-cell {
    position: relative;
    overflow: visible;
    z-index: 20;
  }
  .pos-item-suggestions {
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;
    z-index: 2050;
    max-height: 240px;
    overflow-y: auto;
    margin: 2px 0 0;
    display: none;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
  }
  .pos-item-suggestions .list-group-item {
    padding: 0;
    font-size: 13px;
    cursor: pointer;
    border-left: none;
    border-right: none;
  }
  .pos-suggest-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px !important;
  }
  .pos-suggest-item:hover,
  .pos-suggest-item:focus {
    background: rgba(148, 0, 0, 0.08);
    color: #333;
  }
  .pos-suggest-name {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .pos-suggest-price {
    flex: 0 0 auto;
    font-weight: 600;
    color: #333;
    white-space: nowrap;
  }
  .pos-lines-table thead th {
    background: var(--pos-brand);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    border-color: var(--pos-brand-dark);
    padding: 10px 12px;
  }
  .pos-lines-table tbody td {
    font-size: 13px;
    padding: 10px 12px;
    vertical-align: middle;
  }
  .pos-lines-table tfoot td {
    background: #f8f9fa;
    color: #333;
    font-weight: 700;
    padding: 10px 12px;
    border-color: #dee2e6;
  }
  .pos-modal-footer .btn-primary {
    background: var(--pos-brand);
    border-color: var(--pos-brand);
  }
  .pos-modal-footer .btn-primary:hover {
    background: var(--pos-brand-dark);
    border-color: var(--pos-brand-dark);
  }
  .pos-view-order-modal .modal-backdrop.show {
    opacity: 0.6;
  }
  .pos-view-order-dialog {
    max-width: 420px;
    width: calc(100% - 32px);
    margin: 1rem auto;
  }
  .pos-view-order-dialog.pos-view-invoice-dialog {
    max-width: min(1200px, 96vw);
    width: calc(100% - 32px);
    margin: 1rem auto;
  }
  .pos-view-invoice-body {
    padding: 20px 24px 24px;
  }
  .pos-invoice-tables-row {
    align-items: flex-start;
  }
  .pos-invoice-payment-table thead th,
  .pos-invoice-payment-table tbody td {
    font-size: 12px;
    vertical-align: middle;
  }
  .pos-invoice-payment-table tbody td {
    word-break: break-word;
  }
  .pos-invoice-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    justify-content: space-between;
  }
  .pos-invoice-printer-select {
    max-width: 220px;
    min-height: 38px;
    font-size: 13px;
  }
  .pos-invoice-footer-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-left: auto;
  }
  .pos-invoice-footer .btn-secondary {
    background: #e9ecef;
    border-color: #ced4da;
    color: #495057;
    font-weight: 600;
  }
  .pos-invoice-footer .btn-primary {
    background: var(--pos-brand);
    border-color: var(--pos-brand);
    font-weight: 600;
  }
  .pos-view-order-content {
    position: relative;
    border: none;
    border-radius: 8px;
    overflow: visible;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.22);
  }
  .pos-view-order-close {
    position: absolute;
    top: -16px;
    right: -16px;
    z-index: 5;
    width: 40px;
    height: 40px;
    margin: 0;
    padding: 0;
    border-radius: 50%;
    background: #111;
    color: #fff;
    opacity: 1;
    font-size: 24px;
    font-weight: 300;
    line-height: 38px;
    text-shadow: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  }
  .pos-view-order-close:hover {
    color: #fff;
    opacity: 0.92;
  }
  .pos-view-receipt {
    text-align: center;
    color: #222;
  }
  .pos-view-receipt-head {
    padding: 28px 24px 20px;
    border-bottom: 1px solid #eee;
  }
  .pos-view-heading {
    font-size: 18px;
    font-weight: 700;
    line-height: 1.35;
    color: #1a1a1a;
  }
  .pos-view-address,
  .pos-view-tax {
    font-size: 12px;
    color: #666;
    line-height: 1.5;
    margin-top: 6px;
  }
  .pos-view-receipt-meta {
    text-align: left;
    padding: 16px 24px;
    font-size: 12px;
    border-bottom: 1px solid #f0f0f0;
  }
  .pos-view-meta-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 6px;
  }
  .pos-view-meta-row:last-child {
    margin-bottom: 0;
  }
  .pos-view-meta-item {
    flex: 1 1 0;
    min-width: 0;
  }
  .pos-view-meta-label {
    font-weight: 700;
    color: #333;
    margin-right: 4px;
  }
  .pos-view-meta-value {
    color: #444;
    word-break: break-word;
  }
  .pos-view-receipt-table-wrap {
    padding: 8px 0 20px;
    min-height: 120px;
  }
  .pos-view-lines-table {
    width: 100%;
    font-size: 13px;
    border-collapse: collapse;
  }
  .pos-view-lines-table thead th {
    background: #eceff1;
    color: #333;
    font-weight: 600;
    font-size: 12px;
    padding: 10px 24px;
    border: none;
  }
  .pos-view-lines-table tbody td {
    padding: 12px 24px;
    border: none;
    border-bottom: 1px solid #f3f3f3;
    vertical-align: top;
    text-align: left;
    line-height: 1.4;
  }
  .pos-view-lines-table tbody td.text-right {
    text-align: right;
    font-weight: 600;
    color: #333;
    white-space: nowrap;
  }
  .pos-view-lines-table tbody tr:last-child td {
    border-bottom: none;
  }
  .pos-view-empty {
    font-size: 13px;
    padding: 24px;
    margin: 0;
  }
  .pos-view-receipt-foot {
    padding: 0 24px 24px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    border-top: 1px solid #f0f0f0;
    padding-top: 16px;
    margin-top: 4px;
  }
  .pos-view-subtitle {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
  }
  .pos-view-meta-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 10px 12px;
    height: 100%;
  }
  .pos-view-meta-card__label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #666;
    margin-bottom: 4px;
  }
  .pos-view-meta-card__value {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #222;
    word-break: break-word;
  }
  .pos-view-order-dialog.pos-view-invoice-dialog {
    max-width: min(1140px, 92vw);
  }
</style>
