<style>
  :root {
    --pg-brand: #940000;
    --pg-brand-dark: #7a0000;
  }

  .pg-page { background: #fff; }

  .pg-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 0;
  }

  .pg-header h3 {
    margin: 0;
    font-size: 22px;
    font-weight: 400;
    color: #333;
  }

  .pg-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 0 20px 14px;
  }

  .btn-pg,
  .btn-pg-submit,
  .btn-pg-modal-primary {
    background: var(--pg-brand) !important;
    border-color: var(--pg-brand) !important;
    color: #fff !important;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 14px;
    border-radius: 3px;
    white-space: nowrap;
  }

  .btn-pg:hover,
  .btn-pg-submit:hover,
  .btn-pg-modal-primary:hover {
    background: var(--pg-brand-dark) !important;
    border-color: var(--pg-brand-dark) !important;
    color: #fff !important;
  }

  .btn-pg.is-muted {
    opacity: 0.65;
    cursor: not-allowed;
  }

  .pg-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    padding: 0 20px 16px;
    border-bottom: 3px solid var(--pg-brand);
  }

  .pg-toolbar--report {
    border-bottom: 1px solid #e5e7eb;
  }

  .pg-field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #333;
    margin-bottom: 4px;
  }

  .pg-field .form-control {
    min-height: 36px;
    font-size: 13px;
  }

  .pg-field--date input { min-width: 145px; }
  .pg-field--text input { min-width: 130px; }
  .pg-field--select select { min-width: 120px; }

  .pg-table-wrap {
    overflow-x: auto;
    padding: 0 20px;
  }

  .pg-table-wrap--compact {
    padding-top: 16px;
  }

  .pg-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1400px;
  }

  .pg-table--compact {
    min-width: 720px;
  }

  .pg-table thead th {
    background: #343a40;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    padding: 10px 12px;
    border: 1px solid #2d3238;
    white-space: nowrap;
  }

  .pg-table tbody td {
    border: 1px solid #dee2e6;
    padding: 10px 12px;
    font-size: 13px;
    background: #fff;
    vertical-align: middle;
  }

  .pg-table tbody tr:hover td { background: #fafafa; }

  .pg-empty-cell {
    height: 120px;
    background: #fff !important;
  }

  .pg-link {
    color: var(--pg-brand);
    text-decoration: none;
    font-weight: 600;
  }

  .pg-link:hover {
    color: var(--pg-brand-dark);
    text-decoration: underline;
  }

  .pg-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    padding: 14px 20px 18px;
  }

  .pg-per-page {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #555;
  }

  .pg-per-page select {
    width: 70px;
    min-height: 34px;
  }

  .pg-count {
    font-size: 13px;
    color: #555;
    min-width: 70px;
    text-align: center;
  }

  .pg-nav {
    display: flex;
    gap: 4px;
  }

  .pg-nav-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #dee2e6;
    background: #fff;
    color: #495057;
    border-radius: 3px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 16px;
  }

  .pg-nav-btn:hover {
    border-color: var(--pg-brand);
    color: var(--pg-brand);
    text-decoration: none;
  }

  .pg-nav-btn.is-disabled {
    opacity: 0.45;
    pointer-events: none;
  }

  .pg-empty {
    padding: 32px 20px;
    text-align: center;
    color: #666;
    font-size: 15px;
  }

  .pg-modal__header {
    background: var(--pg-brand);
    color: #fff;
    border-bottom: none;
  }

  .pg-modal__header .close {
    color: #fff;
    opacity: 1;
  }

  .pg-modal__header-plain {
    border-bottom: none;
    padding-bottom: 0;
  }

  .pg-modal__header-plain .modal-title {
    font-size: 22px;
    font-weight: 400;
    color: #333;
  }

  .pg-modal__subtitle {
    font-size: 13px;
    color: #666;
  }

  .pg-modal__divider {
    height: 3px;
    background: var(--pg-brand);
    margin: 0 0 20px;
  }

  .pg-modal__body {
    font-size: 14px;
    line-height: 1.6;
    color: #333;
  }
</style>
