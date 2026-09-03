<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\CmOtaContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OtaContentController extends Controller
{
    public function __construct(private CmOtaContentService $content) {}

    public function index(): View
    {
        $hotel = auth()->user()->hotel;
        $load = $this->content->load($hotel);

        return view('hotel.channel-manager.ota-content.index', [
            'hotel' => $hotel,
            'loaded' => $load['success'],
            'message' => $load['message'],
            'property' => $load['property'],
            'rooms' => $load['rooms'],
        ]);
    }

    public function refresh(): RedirectResponse
    {
        $hotel = auth()->user()->hotel;
        $load = $this->content->load($hotel);

        return redirect()
            ->route('hotel.channel-manager.ota-content.index')
            ->with($load['success'] ? 'success' : 'warning', $load['message']);
    }
}
