<style>
  :root {
    --be-brand: #940000;
    --be-brand-dark: #7a0000;
  }

  .be-subnav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding-bottom: 14px;
    border-bottom: 3px solid var(--be-brand);
  }

  .be-subnav__link {
    display: inline-block;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    color: #444;
    background: #f3f4f6;
    border-radius: 4px;
    text-decoration: none;
  }

  .be-subnav__link:hover {
    color: var(--be-brand);
    text-decoration: none;
    background: #fef2f2;
  }

  .be-subnav__link.is-active {
    background: var(--be-brand);
    color: #fff;
  }
</style>
