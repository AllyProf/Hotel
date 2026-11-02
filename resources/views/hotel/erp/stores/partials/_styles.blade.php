<style>
  :root {
    --stores-brand: var(--sv-brand, #940000);
    --stores-brand-dark: #7a0000;
    --stores-brand-soft: var(--sv-brand-soft, rgba(148, 0, 0, 0.08));
    --stores-tab-border: #e5e7eb;
  }

  .stores-page {
    background: #fff;
  }

  /* Main module tabs (Purchase Orders … Settings) */
  .stores-page-head {
    padding: 16px 20px 0;
  }
  .stores-main-tabs {
    border-bottom: 1px solid var(--stores-tab-border);
    margin-bottom: 0;
    flex-wrap: wrap;
  }
  .stores-main-tabs .nav-item {
    margin-bottom: -1px;
  }
  .stores-main-tabs .nav-link {
    color: #555;
    font-weight: 600;
    font-size: 14px;
    padding: 10px 16px;
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    background: transparent;
    white-space: nowrap;
  }
  .stores-main-tabs .nav-link:hover {
    color: var(--stores-brand);
    background: transparent;
  }
  .stores-main-tabs .nav-link.active {
    color: var(--stores-brand);
    background: transparent;
    border-bottom-color: var(--stores-brand);
  }

  .stores-page-divider {
    display: none;
  }

  /* Settings sub-tabs + Configure User */
  .stores-settings-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #eee;
    background: #fafbfc;
  }
  .stores-settings-bar-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }
  .stores-sub-tabs {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .stores-sub-tabs .nav-item {
    margin: 0 6px 6px 0;
  }
  .stores-sub-tabs .nav-link {
    display: block;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    color: #444;
    background: #fff;
    border: 1px solid #c8cdd3;
    border-radius: 3px;
    text-decoration: none;
  }
  .stores-sub-tabs .nav-link:hover {
    color: var(--stores-brand);
    border-color: rgba(148, 0, 0, 0.35);
    background: var(--stores-brand-soft);
    text-decoration: none;
  }
  .stores-sub-tabs .nav-link.active {
    color: var(--stores-brand);
    background: var(--stores-brand-soft);
    border-color: var(--stores-brand);
  }

  .btn-stores {
    background: var(--stores-brand) !important;
    border-color: var(--stores-brand) !important;
    color: #fff !important;
    font-weight: 600;
    font-size: 13px;
  }
  .btn-stores:hover {
    background: var(--stores-brand-dark) !important;
    border-color: var(--stores-brand-dark) !important;
    color: #fff !important;
  }
  .btn-stores-outline {
    border: 1px solid var(--stores-brand) !important;
    color: var(--stores-brand) !important;
    background: #fff !important;
    font-weight: 600;
    font-size: 13px;
    min-height: 36px;
    padding: 6px 20px;
    white-space: nowrap;
  }
  .btn-stores-outline:hover:not(:disabled) {
    background: var(--stores-brand-soft) !important;
    color: var(--stores-brand-dark) !important;
    border-color: var(--stores-brand-dark) !important;
  }
  .btn-stores-outline:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }
  .btn-stores-config {
    border: 1px solid #f0ad4e !important;
    color: #856404 !important;
    background: #fff !important;
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
  }

  /* Toolbar: filters left, Add Item + search right */
  .stores-toolbar {
    padding: 16px 20px 8px;
  }
  .stores-toolbar-form {
    width: 100%;
  }
  .stores-toolbar-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    width: 100%;
  }
  .stores-filter-fields {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    margin: 0 -10px;
  }
  .stores-filter-field {
    flex: 0 0 auto;
    width: 200px;
    max-width: 100%;
    padding: 0 10px 10px;
    box-sizing: border-box;
  }
  .stores-filter-field .control-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #333;
    margin-bottom: 4px;
    line-height: 1.2;
  }
  .stores-filter-field .form-control {
    width: 100%;
    min-height: 36px;
    font-size: 13px;
  }
  .stores-toolbar-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: flex-end;
    flex: 1 1 280px;
    margin-left: auto;
    padding-bottom: 10px;
  }
  .stores-filter-fields--spacer {
    flex: 1 1 auto;
    min-width: 0;
    margin: 0;
    padding: 0;
  }
  .stores-search-field {
    flex: 0 1 280px;
    min-width: 220px;
    margin-left: 14px;
  }
  .stores-toolbar-search-form {
    margin: 0;
  }
  .stores-search-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #333;
    margin-bottom: 4px;
    line-height: 1.2;
  }
  .stores-master-filter-form .stores-search-label {
    visibility: hidden;
    height: 0;
    margin: 0;
    overflow: hidden;
  }
  .stores-search-input .form-control {
    min-height: 36px;
    font-size: 13px;
    border-right: 0;
  }
  .stores-search-input .stores-search-btn {
    min-height: 36px;
    padding-left: 12px;
    padding-right: 12px;
    border-color: #ced4da;
    background: #fff;
    color: #555;
  }
  .stores-search-input .stores-search-btn:hover {
    background: #f8f9fa;
    color: var(--stores-brand);
  }
  .stores-toolbar-action-item {
    flex: 0 0 auto;
  }
  .stores-toolbar-field-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: transparent;
    margin-bottom: 4px;
    line-height: 1.2;
    user-select: none;
  }
  .btn-stores-add-item {
    margin-bottom: 0;
  }
  @media (max-width: 767px) {
    .stores-toolbar-actions {
      width: 100%;
      flex-basis: 100%;
      margin-left: 0;
      justify-content: flex-start;
    }
    .stores-search-field {
      flex: 1 1 100%;
      margin-left: 0;
      margin-top: 8px;
    }
    .btn-stores-add-item {
      margin-bottom: 8px;
    }
  }

  .stores-total-value {
    text-align: right;
    padding: 0 20px 12px;
    font-size: 14px;
  }
  .stores-respond-form-fields {
    padding: 0 20px 4px;
  }
  .stores-respond-line-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 20px 4px;
  }
  .stores-respond-hint {
    padding: 0 20px 10px;
    margin-bottom: 0;
  }
  .stores-respond-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 20px 24px;
    margin-top: 4px;
    border-top: 1px solid #e9ecef;
  }
  .stores-respond-head-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
  }
  .stores-total-value strong {
    font-size: 15px;
  }
  .stores-page .stores-master-table thead th,
  .stores-page .stores-po-list-table thead th,
  .stores-page .stores-po-table thead th {
    background: var(--stores-brand, #940000);
    color: #fff;
    border-color: var(--stores-brand-dark, #7a0000);
  }
  .stores-master-table thead th {
    font-size: 13px;
    font-weight: 600;
    padding: 10px 12px;
    white-space: nowrap;
  }
  .stores-master-table tbody td {
    font-size: 13px;
    padding: 10px 12px;
    vertical-align: middle;
  }
  .stores-master-table .text-right {
    text-align: right;
  }
  .stores-table-wrap {
    padding: 0 20px 24px;
  }
  .stores-icon-btn {
    padding: 4px 8px;
    border: none;
    background: transparent;
    cursor: pointer;
  }

  .stores-modal-header {
    border-bottom: none;
    padding-bottom: 0;
  }
  .stores-modal-header .modal-title {
    font-weight: 700;
    color: #333;
  }
  .stores-modal-divider {
    height: 3px;
    background: var(--stores-brand);
    margin: 0 0 20px;
    border-radius: 1px;
  }
  .stores-add-vendor-modal .modal-footer {
    border-top: none;
    justify-content: flex-end;
  }

  /* Item category list */
  .stores-cat-notice {
    margin: 12px 20px 0;
    padding: 10px 14px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    font-size: 13px;
    color: #555;
  }
  .stores-cat-notice a {
    font-weight: 600;
    color: var(--stores-brand, #940000);
  }
  .stores-cat-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid #eee;
  }
  .stores-cat-add-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    flex: 1 1 280px;
    max-width: 420px;
  }
  .stores-cat-add-input {
    flex: 1 1 180px;
    min-width: 140px;
    margin-right: 8px;
    min-height: 36px;
    font-size: 13px;
  }
  .stores-cat-search-form {
    flex: 0 1 260px;
    min-width: 200px;
    margin-left: auto;
  }
  .stores-cat-toolbar--search-only {
    justify-content: flex-end;
  }
  .stores-cat-toolbar--search-only .stores-cat-search-form {
    margin-left: 0;
    width: 100%;
    max-width: 320px;
  }
  .stores-cat-list-wrap {
    padding: 8px 20px 24px;
  }
  .stores-cat-item {
    border-bottom: 1px solid #e8e8e8;
  }
  .stores-cat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 4px;
    min-height: 48px;
  }
  .stores-cat-row--flat {
    border-bottom: 1px solid #eee;
  }
  .stores-cat-name {
    font-size: 14px;
    font-weight: 600;
    color: #333;
  }
  .stores-cat-actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }
  .stores-cat-toggle {
    margin-left: 2px;
  }
  .stores-cat-chevron {
    transition: transform 0.2s ease;
  }
  .stores-cat-chevron.is-open {
    transform: rotate(180deg);
  }
  .stores-cat-expand-inner {
    padding: 0 4px 16px 8px;
  }
  .stores-subcat-pills {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 12px;
  }
  .stores-subcat-pill {
    display: inline-flex;
    align-items: center;
    background: #f0f1f3;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 4px 6px 4px 10px;
    margin: 0 8px 8px 0;
    font-size: 12px;
    color: #444;
  }
  .stores-subcat-pill-remove {
    border: none;
    background: transparent;
    color: #c0392b;
    font-size: 16px;
    line-height: 1;
    padding: 0 0 0 6px;
    cursor: pointer;
    font-weight: 700;
  }
  .stores-subcat-add-form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    max-width: 480px;
  }
  .stores-subcat-add-input {
    flex: 1 1 160px;
    min-width: 140px;
    margin-right: 8px;
    min-height: 34px;
    font-size: 13px;
  }
  @media (max-width: 767px) {
    .stores-cat-search-form {
      flex: 1 1 100%;
      margin-left: 0;
    }
  }

  .stores-packaging-card {
    background: #f4f5f7;
    border: 1px solid #e2e5ea;
    border-radius: 8px;
    padding: 18px 18px 12px;
  }
  .stores-packaging-card--modal {
    margin-top: 8px;
    padding: 14px 16px 10px;
  }
  .stores-packaging-card--modal .stores-packaging-card__title {
    font-size: 14px;
    margin-bottom: 12px;
  }
  #storesItemModal .modal-footer {
    border-top: 1px solid #e9ecef;
    justify-content: flex-end;
  }
  .stores-packaging-card__title {
    font-weight: 700;
    font-size: 15px;
    margin-bottom: 16px;
    color: #333;
  }
  .stores-packaging-card__title i {
    color: var(--stores-brand);
    margin-right: 6px;
  }
  .stores-packaging-subtitle {
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 10px;
    color: #444;
  }
  .stores-field-hint {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
    margin-bottom: 0;
  }
  .stores-no-spinners::-webkit-outer-spin-button,
  .stores-no-spinners::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  .stores-no-spinners {
    -moz-appearance: textfield;
    appearance: textfield;
  }
  .stores-packaging-card .stores-packaging-row {
    margin-left: -8px;
    margin-right: -8px;
  }
  .stores-packaging-card .stores-packaging-row > [class*="col-"] {
    padding-left: 8px;
    padding-right: 8px;
  }
  .stores-packaging-card .js-stores-sell-options {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e2e5ea;
  }
  .stores-packaging-remove-btn {
    flex-shrink: 0;
    min-width: 28px;
    line-height: 1;
  }
  .stores-packaging-pieces-field .d-flex {
    gap: 8px;
  }

  .stores-po-modal-dialog {
    max-width: 1100px;
  }
  .stores-po-modal-body {
    padding-bottom: 8px;
  }
  .stores-po-entry-row {
    display: grid;
    grid-template-columns: minmax(160px, 2.2fr) repeat(4, minmax(72px, 1fr)) minmax(100px, auto);
    gap: 10px;
    align-items: end;
    margin-bottom: 16px;
  }
  .stores-po-entry-row .control-label {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
  }
  .stores-po-calc {
    background: #f3f4f6;
  }
  .stores-po-table thead th {
    font-size: 12px;
    font-weight: 700;
    padding: 10px;
    white-space: nowrap;
  }
  .stores-po-table tfoot td {
    background: #fff;
    color: #222;
    font-weight: 700;
    font-size: 13px;
    padding: 10px;
    border-color: #dee2e6;
  }
  .stores-po-table .stores-po-line-actions .stores-icon-btn {
    font-size: 15px;
    line-height: 1;
    vertical-align: middle;
  }
  .stores-po-table .stores-po-line-actions .stores-icon-btn.text-primary {
    color: #007bff !important;
  }
  .stores-po-table .stores-po-line-actions .stores-icon-btn.text-danger {
    color: #dc3545 !important;
  }
  .stores-po-modal-footer {
    justify-content: flex-end;
    gap: 8px;
  }
  .stores-po-dept-divider {
    height: 3px;
    background: var(--stores-brand, #940000);
    opacity: 0.35;
    border-radius: 2px;
  }
  .stores-po-page-head {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 12px 20px 0;
  }
  .stores-po-access-link {
    font-weight: 600;
    font-size: 13px;
  }
  .btn-stores-po-bank {
    background: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
    color: #444 !important;
    font-weight: 600;
    font-size: 13px;
    min-height: 36px;
    padding: 6px 16px;
  }
  .stores-report-toolbar .stores-po-filter-row {
    align-items: flex-start;
  }
  .stores-report-filter-fields {
    flex: 1 1 auto;
  }
  .stores-report-toolbar .stores-filter-field--report-type {
    width: 200px;
    min-width: 200px;
  }
  .stores-report-toolbar .stores-filter-field--report-sort {
    width: 260px;
    min-width: 260px;
  }
  .stores-report-head-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    flex-shrink: 0;
    margin-left: auto;
    padding-top: 4px;
  }
  .stores-report-total {
    font-size: 15px;
    white-space: nowrap;
  }
  .stores-report-download {
    min-width: 100px;
  }
  .stores-report-table .stores-report-row--parent {
    background: #f3f4f6;
  }
  .stores-report-table .stores-report-row--child {
    background: #fff;
  }
  .stores-report-chevron {
    transition: transform 0.2s ease;
    color: #555;
  }
  .stores-report-chevron.is-open {
    transform: rotate(180deg);
  }

  .stores-po-filter-bar {
    padding-top: 10px;
    padding-bottom: 12px;
  }
  .stores-po-filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
  }
  .stores-filter-field--status {
    width: 140px;
  }
  .stores-po-inline-actions {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    flex: 0 0 auto;
    margin-left: auto;
  }
  .btn-stores-po-create {
    background: #f3f4f6 !important;
    border: 1px solid #cbd5e1 !important;
    color: #333 !important;
    font-weight: 600;
    font-size: 13px;
    min-height: 36px;
    padding: 6px 18px;
    border-radius: 3px 0 0 3px;
    white-space: nowrap;
  }
  .btn-stores-po-create:hover:not(:disabled) {
    background: #e9ecef !important;
  }
  .stores-po-inline-search {
    min-width: 200px;
    max-width: 260px;
    min-height: 36px;
    font-size: 13px;
    border-radius: 0 3px 3px 0;
    border-left: 0;
  }
  .stores-po-inline-search--solo {
    border-radius: 3px;
    border-left: 1px solid #ced4da;
  }
  .stores-po-inline-actions--search-only {
    margin-left: auto;
  }
  .stores-po-table-wrap {
    padding-top: 0;
  }
  .stores-po-list-table thead th {
    font-size: 12px;
    font-weight: 700;
    padding: 10px 12px;
    white-space: nowrap;
  }
  .stores-po-list-table tbody td {
    font-size: 13px;
    vertical-align: middle;
    padding: 10px 12px;
  }

  /* Department purchase lists — row ⋮ menu (avoid table-responsive clipping) */
  .stores-page .stores-dept-pl-table-wrap {
    overflow: visible;
  }
  @media (max-width: 991.98px) {
    .stores-page .stores-dept-pl-table-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
  }
  .stores-dept-pl-actions-cell .stores-icon-btn {
    font-size: 16px;
    line-height: 1;
    padding: 6px 10px;
    border-radius: 4px;
  }
  .stores-dept-pl-actions-cell .stores-icon-btn:hover {
    background: #f1f3f5;
  }
  .stores-dept-pl-actions-cell {
    position: relative;
  }
  .stores-dept-pl-row-dropdown {
    position: relative;
    display: inline-block;
  }
  .stores-dept-pl-row-dropdown .dropdown-menu.stores-dept-pl-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    left: auto;
    float: none;
    min-width: 11.5rem;
    max-width: 17rem;
    width: auto;
    margin-top: 2px;
  }
  .stores-dept-pl-dropdown-menu {
    padding: 6px 0;
    z-index: 1055;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  }
  .stores-dept-pl-dropdown-menu:empty {
    display: none !important;
  }
  .stores-dept-pl-dropdown-menu .dropdown-item {
    font-size: 13px;
    padding: 8px 16px;
    white-space: nowrap;
  }
  .stores-dept-pl-dropdown-form {
    margin: 0;
    padding: 0;
    width: auto;
    display: block;
  }
  .stores-dept-pl-dropdown-form .dropdown-item {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
  }
  .stores-dept-pl-dropdown-form .dropdown-item.text-danger {
    color: #dc3545 !important;
  }
  .stores-dept-pl-dropdown-form .dropdown-item.text-danger:hover {
    color: #fff !important;
    background-color: #dc3545;
  }
  .stores-dept-pl-dropdown-hint {
    font-size: 12px;
    line-height: 1.4;
    color: #6c757d;
    white-space: normal;
    max-width: 260px;
  }
  .stores-dept-pl-batch-dropdown .dropdown-menu {
    min-width: 220px;
  }
  .stores-dept-pl-batch-dropdown .stores-dept-pl-dropdown-form {
    margin: 0;
  }
  .stores-po-actions-col {
    min-width: 280px;
  }
  .stores-po-actions-cell {
    white-space: nowrap;
  }
  .stores-po-actions-cell .btn,
  .stores-po-actions-cell .stores-icon-btn {
    vertical-align: middle;
    margin-right: 4px;
  }
  .btn-stores-po-approve {
    border: 1px solid #f0ad4e !important;
    color: #856404 !important;
    background: #fff !important;
    font-weight: 600;
    min-width: 72px;
  }
  .btn-stores-po-approve:hover:not(:disabled) {
    background: #fff8e7 !important;
  }
  .btn-stores-po-approve:disabled {
    opacity: 0.45;
  }
  .btn-stores-po-invoice {
    background: #e9ecef !important;
    border: 1px solid #dee2e6 !important;
    color: #6c757d !important;
    font-weight: 600;
    min-width: 72px;
  }
  .btn-stores-indent-awaiting {
    cursor: default;
    pointer-events: none;
    background: #fff8e6;
    border: 1px solid #f0d78c;
    color: #856404;
    font-weight: 600;
    white-space: nowrap;
  }

  .btn-stores-indent-received {
    background: #fff !important;
    border: 1px solid #8bc48b !important;
    color: #7aab7a !important;
    font-weight: 600;
    min-width: 88px;
    min-height: 31px;
    line-height: 1.2;
    padding: 5px 12px;
    cursor: default;
    pointer-events: none;
    vertical-align: middle;
  }
  .stores-invoice-detail-dialog {
    max-width: 920px;
  }
  .stores-invoice-detail-modal {
    border-radius: 4px;
    padding: 4px 8px 16px;
  }
  .stores-invoice-detail-title {
    font-weight: 700;
    font-size: 22px;
    color: #333;
  }
  .stores-invoice-detail-fields .control-label {
    font-size: 12px;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
  }
  .stores-invoice-detail-fields .form-control {
    font-size: 13px;
    min-height: 36px;
  }
  .stores-invoice-detail-table-wrap {
    margin-top: 8px;
    margin-bottom: 20px;
  }
  .stores-invoice-detail-total-row th {
    background: #fff;
    color: #222;
    font-weight: 700;
    border-top-width: 2px;
  }
  .stores-invoice-detail-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    padding-bottom: 8px;
  }
  .btn-stores-cancel {
    background: #fff !important;
    border: 1px solid #dc3545 !important;
    color: #dc3545 !important;
    font-weight: 600;
    min-width: 88px;
    min-height: 36px;
  }
  .btn-stores-cancel:hover {
    background: #fff5f5 !important;
    color: #c82333 !important;
  }
  .stores-goods-receive-table .form-control-sm {
    font-size: 12px;
    min-height: 32px;
  }
  .stores-goods-receive-table th:nth-child(3),
  .stores-goods-receive-table .stores-gr-received-cell {
    text-align: left;
  }
  .stores-goods-receive-table .stores-gr-received-cell .js-stores-gr-qty {
    text-align: left;
  }
  .stores-receive-summary-row {
    margin-left: -8px;
    margin-right: -8px;
  }
  .stores-receive-stat-card {
    background: #fff;
    border: 1px solid #e8ecef;
    border-radius: 6px;
    padding: 14px 16px;
    min-height: 100%;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  }
  .stores-receive-stat-card__label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 6px;
  }
  .stores-receive-stat-card__value {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: #212529;
    line-height: 1.2;
  }
  .stores-receive-stat-card__value--text {
    font-size: 16px;
    font-weight: 600;
    word-break: break-word;
  }
  .stores-receive-stat-card__value small {
    font-size: 14px;
    font-weight: 600;
    color: #495057;
  }
  .stores-receive-stat-card__hint {
    display: block;
    font-size: 12px;
    color: #868e96;
    margin-top: 6px;
    line-height: 1.35;
  }
  .stores-receive-stat-card--total {
    border-left: 4px solid var(--stores-brand, #940000);
  }
  .stores-receive-stat-card--vendor {
    border-left: 4px solid #495057;
  }
  .stores-receive-stat-card--lines {
    border-left: 4px solid #17a2b8;
  }
  .stores-receive-stat-card--missing {
    border-left: 4px solid #f0ad4e;
  }
  .stores-page .stores-goods-receive-table thead th {
    background: var(--stores-brand, #940000);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.15);
  }
  .stores-indent-qty-hint.text-danger {
    font-weight: 600;
  }
  .stores-indent-entry-row {
    display: grid;
    grid-template-columns: minmax(120px, 1fr) minmax(160px, 2.2fr) minmax(90px, 1fr) minmax(72px, 0.85fr) minmax(120px, 1.2fr) minmax(100px, auto);
    gap: 10px 12px;
    margin-bottom: 16px;
    align-items: start;
  }
  .stores-indent-entry-row.stores-po-entry-row {
    align-items: start;
    grid-template-columns: minmax(120px, 1fr) minmax(160px, 2.2fr) minmax(90px, 1fr) minmax(72px, 0.85fr) minmax(120px, 1.2fr) minmax(100px, auto);
  }
  .stores-indent-entry-row.stores-indent-entry-row--compact {
    grid-template-columns: minmax(200px, 2.5fr) minmax(100px, 1fr) minmax(140px, 1.5fr) minmax(108px, auto);
  }
  .stores-indent-entry-row.stores-indent-entry-row--compact.stores-po-entry-row {
    grid-template-columns: minmax(200px, 2.5fr) minmax(100px, 1fr) minmax(140px, 1.5fr) minmax(108px, auto);
  }
  .stores-po-entry-field--rooms {
    min-width: 200px;
    grid-column: 1 / -1;
  }
  @media (min-width: 992px) {
    .stores-indent-entry-row.stores-indent-entry-row--compact .stores-po-entry-field--rooms {
      grid-column: span 2;
    }
  }
  .stores-po-entry-field--qty .stores-indent-qty-hint {
    min-height: 1.35em;
    line-height: 1.35;
    margin-bottom: 0;
  }
  @media (max-width: 991px) {
    .stores-indent-entry-row {
      grid-template-columns: 1fr 1fr;
    }
    .stores-indent-entry-row .stores-po-entry-field--item,
    .stores-indent-entry-row .stores-po-entry-field--action {
      grid-column: 1 / -1;
    }
  }
  @media (max-width: 991px) {
    .stores-po-inline-actions {
      width: 100%;
      margin-left: 0;
    }
    .stores-po-inline-search {
      flex: 1 1 auto;
      max-width: none;
    }
    .stores-po-entry-row {
      grid-template-columns: 1fr 1fr;
    }
    .stores-po-entry-field--item,
    .stores-po-entry-field--action {
      grid-column: 1 / -1;
    }
  }
</style>
