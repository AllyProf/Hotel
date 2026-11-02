<style>
  .bar-market-summary-row {
    margin-left: -8px;
    margin-right: -8px;
  }
  .bar-market-summary-row > [class*="col-"] {
    padding-left: 8px;
    padding-right: 8px;
    margin-bottom: 16px;
  }
  .bar-market-stat-card {
    height: 100%;
    min-height: 96px;
    padding: 14px 16px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-sizing: border-box;
  }
  .bar-market-stat-card__label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: #666;
    margin-bottom: 6px;
  }
  .bar-market-stat-card__value {
    display: block;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.2;
    color: #222;
  }
  .bar-market-stat-card__value small {
    font-size: 12px;
    font-weight: 600;
    color: #666;
  }
  .bar-market-stat-card__hint {
    display: block;
    font-size: 11px;
    color: #888;
    margin-top: 6px;
    line-height: 1.3;
  }
  .bar-market-stat-card--budget { background: #f8f9fa; }
  .bar-market-stat-card--used {
    background: #eef6fb;
    border-color: #c5dff0;
  }
  .bar-market-stat-card--remaining {
    background: #eef8f0;
    border-color: #c8e6c9;
  }
  .bar-market-stat-card--remaining.bar-market-stat-card--over {
    background: #fdecea;
    border-color: #f5c6cb;
  }
  .bar-market-stat-card--remaining.bar-market-stat-card--over .bar-market-stat-card__value {
    color: #c0392b;
  }
  .bar-market-stat-card--missing {
    background: #fff8e6;
    border-color: #ffe082;
  }
  .bar-market-items-table {
    table-layout: fixed;
    width: 100%;
    min-width: 960px;
  }
  .bar-market-items-table thead th {
    background: #940000;
    color: #fff;
    border-color: #7a0000;
    font-weight: 600;
    font-size: 12px;
    vertical-align: middle;
    padding: 10px 8px;
  }
  .bar-market-col-found { width: 48px; }
  .bar-market-col-name { width: 168px; max-width: 168px; }
  .bar-market-col-plan { width: 52px; }
  .bar-market-col-bought { width: 72px; }
  .bar-market-col-recv-pkg,
  .bar-market-col-sell-pkg { width: 88px; }
  .bar-market-col-cost { width: 96px; }
  .bar-market-col-sell-price { width: 128px; }
  .bar-market-col-expiry { width: 72px; }
  .bar-market-item-name__title {
    font-weight: 700;
    font-size: 13px;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }
  .bar-market-item-name__tag {
    display: inline-block;
    margin-top: 4px;
    padding: 1px 8px;
    font-size: 10px;
    font-weight: 600;
    color: #555;
    background: #ececec;
    border: 1px solid #ddd;
    border-radius: 4px;
  }
  .bar-market-no-spinners::-webkit-outer-spin-button,
  .bar-market-no-spinners::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  .bar-market-no-spinners {
    -moz-appearance: textfield;
    appearance: textfield;
  }
  .bar-market-items-table input.form-control-sm,
  .bar-market-items-table select.form-control-sm {
    min-width: 0;
    width: 100%;
    padding: 4px 4px;
    font-size: 12px;
  }
  .bar-market-unit-cost-hint { font-size: 10px; }
  .bar-market-grand-total {
    font-size: 17px;
    font-weight: 700;
    color: #d35400;
  }
  .bar-market-date-picker {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .bar-market-date-input {
    position: absolute;
    opacity: 0;
    width: 36px;
    height: 32px;
    cursor: pointer;
    z-index: 2;
  }
  .bar-market-date-trigger {
    padding: 4px 8px;
    line-height: 1;
    pointer-events: none;
  }
  .bar-market-items-table tfoot th {
    background: #f1f3f5;
    font-size: 13px;
    padding: 10px 8px;
    border-color: #dee2e6;
  }
  .bar-market-sell-rate-pill {
    display: block;
    text-align: center;
    font-size: 11px;
    font-weight: 600;
    padding: 6px 4px;
    border-radius: 4px;
    background: #f4faf4;
    border: 1px solid #6aab6a;
    color: #2d6a2d;
  }
</style>
