<style>
  .co-stay-page-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px;
    border-bottom: 3px solid var(--co-brand);
    margin-bottom: 0;
  }

  .co-stay-page-head h3 {
    margin: 0 0 6px;
    font-size: 22px;
    font-weight: 400;
    color: #333;
  }

  .co-stay-page-head h3 .fa {
    color: var(--co-brand);
    margin-right: 8px;
  }

  .co-stay-subtitle {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
  }

  .co-stay-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 18px;
  }

  @media (max-width: 767px) {
    .co-stay-stats { grid-template-columns: 1fr; }
  }

  .co-stay-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 12px 14px;
    min-height: 72px;
  }

  .co-stay-stat.is-highlight {
    border-top: 3px solid var(--co-brand);
  }

  .co-stay-stat__icon {
    color: var(--co-brand);
    font-size: 14px;
    margin-bottom: 4px;
  }

  .co-stay-stat__label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
    margin-bottom: 4px;
  }

  .co-stay-stat__value {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
  }

  .co-stay-panel {
    margin-bottom: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
  }

  .co-stay-panel:last-child { margin-bottom: 0; }

  .co-stay-panel__head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #fafafa;
    border-bottom: 1px solid #eee;
    font-size: 13px;
    font-weight: 700;
    color: #333;
  }

  .co-stay-panel__head .fa {
    color: var(--co-brand);
    width: 16px;
    text-align: center;
  }

  .co-stay-panel__body { padding: 12px 14px; }

  .co-stay-kv {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 20px;
    font-size: 13px;
  }

  @media (max-width: 575px) {
    .co-stay-kv { grid-template-columns: 1fr; }
  }

  .co-stay-kv__label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 2px;
  }

  .co-stay-kv__value {
    display: block;
    font-weight: 600;
    color: #111827;
    margin-bottom: 8px;
  }

  .co-stay-table thead th {
    background: var(--co-brand);
    color: #fff;
    font-weight: 700;
    border-color: var(--co-brand-dark);
    font-size: 12px;
    white-space: nowrap;
  }

  .co-stay-table tbody tr:nth-child(even) { background: #fafafa; }

  .co-stay-table a {
    color: var(--co-brand);
    font-weight: 700;
  }

  .co-stay-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 3px;
    background: #fef3c7;
    color: #92400e;
    margin-left: 6px;
  }

  .co-stay-empty {
    font-size: 13px;
    color: #9ca3af;
    margin: 0;
    text-align: center;
    padding: 16px 0;
  }

  .co-stay-empty .fa {
    display: block;
    font-size: 22px;
    margin-bottom: 6px;
    color: #d1d5db;
  }

  .co-stay-detail-btn {
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--co-brand) !important;
    border: 1px solid #d1d5db !important;
    background: #fff !important;
    text-decoration: none !important;
  }

  .co-stay-detail-btn:hover {
    background: var(--co-brand-soft) !important;
    border-color: var(--co-brand) !important;
    color: var(--co-brand) !important;
  }
</style>
