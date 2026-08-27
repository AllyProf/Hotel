<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $hotelsCount = Hotel::count();
        $activeHotelsCount = Hotel::where('status', Hotel::STATUS_ACTIVE)->count();

        return view('dashboard.index', compact('hotelsCount', 'activeHotelsCount'));
    }
}
