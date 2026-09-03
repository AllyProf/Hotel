<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Services\HotelAnalyticsService;
use App\Services\HotelDynamicPricingService;
use App\Services\HotelPickupReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private HotelAnalyticsService $analytics,
        private HotelDynamicPricingService $dynamicPricing,
        private HotelPickupReportService $pickupReport,
    ) {}

    public function hotelPerformance(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->analytics->filtersFromRequest($request);
        $report = $this->analytics->hotelPerformance($hotel, $filters);

        return view('hotel.analytics.hotel-performance', [
            'hotel' => $hotel,
            'filters' => $filters,
            'ui' => $this->analytics->uiConfig(),
            'report' => $report,
        ]);
    }

    public function dailyReport(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->analytics->dailyReportFiltersFromRequest($request);
        $report = $this->analytics->dailyReport($hotel, $filters);

        return view('hotel.analytics.daily-report', [
            'hotel' => $hotel,
            'filters' => $filters,
            'ui' => $this->analytics->dailyReportUiConfig(),
            'report' => $report,
        ]);
    }

    public function trendAnalysis(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->analytics->trendAnalysisFiltersFromRequest($request);
        $report = $this->analytics->trendAnalysis($hotel, $filters);

        return view('hotel.analytics.trend-analysis', [
            'hotel' => $hotel,
            'filters' => $filters,
            'ui' => $this->analytics->trendAnalysisUiConfig(),
            'report' => $report,
        ]);
    }

    public function dynamicPricing(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->dynamicPricing->filtersFromRequest($request);
        $report = $this->dynamicPricing->report($hotel, $filters);

        return view('hotel.analytics.dynamic-pricing', [
            'hotel' => $hotel,
            'filters' => $filters,
            'ui' => $this->dynamicPricing->uiConfig(),
            'report' => $report,
        ]);
    }

    public function pickupReport(Request $request): View
    {
        $hotel = auth()->user()->hotel()->firstOrFail();
        $filters = $this->pickupReport->filtersFromRequest($request);
        $report = $this->pickupReport->report($hotel, $filters);

        return view('hotel.analytics.pickup-report', [
            'hotel' => $hotel,
            'filters' => $filters,
            'ui' => $this->pickupReport->uiConfig(),
            'report' => $report,
        ]);
    }
}
