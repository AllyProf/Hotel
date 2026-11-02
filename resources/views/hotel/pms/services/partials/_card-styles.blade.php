<style>
  :root {
    --sv-brand: #940000;
    --sv-brand-dark: #7a0000;
  }

  .sv-page { background: #fff; }

  .sv-section { padding: 20px; }

  .sv-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #333;
    margin: 0 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .sv-section-title .fa { color: var(--sv-brand); }

  .sv-template-card__abbr {
    font-size: 11px;
    color: #666;
    margin: -2px 0 8px;
    font-weight: 600;
  }

  .sv-card-actions-stack {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
  }

  .btn-sv-edit {
    background: #fff !important;
    border: 1px solid #6c757d !important;
    color: #495057 !important;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 3px;
    width: 100%;
    cursor: pointer;
  }

  .btn-sv-edit:hover {
    background: #6c757d !important;
    color: #fff !important;
  }

  .sv-template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
  }

  .sv-template-card {
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 16px 12px;
    background: #fafafa;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 168px;
  }

  .sv-template-card.is-added {
    background: #f0fdf4;
    border-color: #bbf7d0;
  }

  .sv-template-card.is-inactive {
    opacity: 0.75;
  }

  .sv-template-card--add {
    background: #fff;
    justify-content: center;
    gap: 10px;
  }

  .sv-template-card__icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #e5e7eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--sv-brand);
    font-size: 18px;
    margin-bottom: 10px;
    flex-shrink: 0;
  }

  .sv-template-card__name {
    font-size: 14px;
    font-weight: 700;
    color: #333;
    margin: 0 0 6px;
    line-height: 1.3;
  }

  .sv-badge-custom {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 2px 8px;
    border-radius: 10px;
    background: #fef3c7;
    color: #92400e;
    margin-bottom: 10px;
  }

  .sv-template-card__actions {
    width: 100%;
    margin-top: auto;
  }

  .btn-sv-outline {
    background: #fff !important;
    border: 1px solid var(--sv-brand) !important;
    color: var(--sv-brand) !important;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 3px;
    width: 100%;
  }

  .btn-sv-outline:hover:not(:disabled) {
    background: var(--sv-brand) !important;
    color: #fff !important;
  }

  .btn-sv-remove {
    background: #fff !important;
    border: 1px solid #dc3545 !important;
    color: #dc3545 !important;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 3px;
    width: 100%;
    cursor: pointer;
  }

  .btn-sv-remove:hover {
    background: #dc3545 !important;
    color: #fff !important;
  }

  .sv-add-form {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .sv-add-form .form-control {
    min-height: 36px;
    font-size: 13px;
    text-align: center;
  }

  .btn-sv {
    background: var(--sv-brand) !important;
    border-color: var(--sv-brand) !important;
    color: #fff !important;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 3px;
    width: 100%;
  }

  .btn-sv:hover {
    background: var(--sv-brand-dark) !important;
    border-color: var(--sv-brand-dark) !important;
  }
</style>
