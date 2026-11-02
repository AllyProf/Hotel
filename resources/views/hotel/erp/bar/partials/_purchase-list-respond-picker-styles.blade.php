<style>
  .bar-pl-item-suggestions {
    position: fixed;
    z-index: 1050;
    margin: 0;
    padding: 0;
    list-style: none;
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    max-height: 220px;
    overflow-y: auto;
  }
  .bar-pl-item-suggestions li {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
  }
  .bar-pl-item-suggestions li:hover,
  .bar-pl-item-suggestions li.is-active {
    background: rgba(148, 0, 0, 0.08);
    color: #940000;
  }
</style>
