<?php

namespace App\Http\Controllers\Hotel\ChannelManager;

use App\Http\Controllers\Controller;
use App\Services\ChannelManager\CmMessagesService;
use App\Services\OtaConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessagesController extends Controller
{
    public function __construct(
        private CmMessagesService $messages,
        private OtaConnectionService $otaConnections,
    ) {}

    public function index(Request $request): View
    {
        $hotel = auth()->user()->hotel()->with('settings')->firstOrFail();
        $channelOptions = $this->otaConnections->syncChannelOptions($hotel);
        $filters = $this->filtersFromRequest($request, $channelOptions);

        return view('hotel.channel-manager.messages.index', [
            'hotel' => $hotel,
            'filters' => $filters,
            'channels' => $channelOptions,
            'messages' => $this->messages->list($hotel, $filters),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $hotel = auth()->user()->hotel;

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'max:100'],
        ]);

        $allowed = $this->otaConnections->syncChannelValues($hotel);
        $channels = array_values(array_intersect(
            array_filter($validated['channels'] ?? []),
            $allowed
        ));

        $result = $this->messages->sync(
            $hotel,
            $validated['start_date'],
            $validated['end_date'],
            $channels
        );

        return redirect()
            ->route('hotel.channel-manager.messages.index', [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'channels' => $channels,
            ])
            ->with($result['success'] ? 'success' : 'warning', $result['message']);
    }

    /**
     * @param  list<array{label: string, value: string, slug: string}>  $channelOptions
     * @return array<string, mixed>
     */
    private function filtersFromRequest(Request $request, array $channelOptions): array
    {
        $allowed = array_column($channelOptions, 'value');
        $selected = array_values(array_intersect(
            array_filter((array) $request->input('channels', [])),
            $allowed
        ));

        if ($selected === [] && $allowed !== [] && ! $request->has('channels')) {
            $selected = $allowed;
        }

        return [
            'start_date' => $request->input('start_date', now()->subMonth()->format('Y-m-d')),
            'end_date' => $request->input('end_date', now()->addMonth()->format('Y-m-d')),
            'channels' => $selected,
            'search' => trim((string) $request->input('search', '')),
        ];
    }
}
