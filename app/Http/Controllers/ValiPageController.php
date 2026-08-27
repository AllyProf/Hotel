<?php

namespace App\Http\Controllers;

use App\Services\ValiTemplateService;
use Illuminate\View\View;

class ValiPageController extends Controller
{
    public function __construct(private ValiTemplateService $vali) {}

    public function dashboard(): View
    {
        return $this->render('index');
    }

    public function bootstrapComponents(): View
    {
        return $this->render('bootstrap-components');
    }

    public function uiCards(): View
    {
        return $this->render('ui-cards');
    }

    public function widgets(): View
    {
        return $this->render('widgets');
    }

    public function charts(): View
    {
        return $this->render('charts');
    }

    public function formComponents(): View
    {
        return $this->render('form-components');
    }

    public function formCustom(): View
    {
        return $this->render('form-custom');
    }

    public function formSamples(): View
    {
        return $this->render('form-samples');
    }

    public function formNotifications(): View
    {
        return $this->render('form-notifications');
    }

    public function tableBasic(): View
    {
        return $this->render('table-basic');
    }

    public function tableDataTable(): View
    {
        return $this->render('table-data-table');
    }

    public function blankPage(): View
    {
        return $this->render('blank-page');
    }

    public function userPage(): View
    {
        return $this->render('page-user');
    }

    public function invoicePage(): View
    {
        return $this->render('page-invoice');
    }

    public function calendarPage(): View
    {
        return $this->render('page-calendar');
    }

    public function mailboxPage(): View
    {
        return $this->render('page-mailbox');
    }

    public function errorPage(): View
    {
        return $this->render('page-error');
    }

    public function lockscreen(): View
    {
        $page = $this->vali->renderAuthPage('page-lockscreen');

        return view('layouts.vali-auth-page', $page);
    }

    private function render(string $page): View
    {
        $data = $this->vali->renderAppPage($page);

        return view('layouts.vali-page', $data);
    }
}
