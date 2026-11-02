@include('hotel.pms.services.partials._card-styles')
<style>
  .pms-brand-tabs.nav-tabs {
    border-bottom: 2px solid #e5e7eb;
  }

  .pms-brand-tabs.nav-tabs .nav-link {
    color: #666;
    font-weight: 600;
    font-size: 13px;
    border: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
  }

  .pms-brand-tabs.nav-tabs .nav-link:hover {
    color: var(--sv-brand);
  }

  .pms-brand-tabs.nav-tabs .nav-link.active {
    color: var(--sv-brand);
    background: transparent;
    border-bottom-color: var(--sv-brand);
  }

  .pms-brand-tabs.nav-pills .nav-link {
    color: #555;
    font-weight: 600;
    font-size: 13px;
  }

  .pms-brand-tabs.nav-pills .nav-link:hover {
    color: var(--sv-brand);
    background: var(--sv-brand-soft);
  }

  .pms-brand-tabs.nav-pills .nav-link.active {
    background: var(--sv-brand);
    color: #fff !important;
  }
</style>
