<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ChannelManagerApiTester;
use App\Services\PlatformIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function __construct(
        private PlatformIntegrationService $integrations,
        private ChannelManagerApiTester $apiTester,
    ) {}

    public function index(): View
    {
        $channelManager = $this->integrations->channelManagerForDisplay();
        $cmEndpoints = [];
        $testReport = session('cm_test_report');

        foreach (config('channel_manager_integration.endpoints', []) as $endpoint) {
            $cmEndpoints[] = array_merge($endpoint, [
                'url' => $this->integrations->resolveEndpointUrl($channelManager, $endpoint['path']),
            ]);
        }

        return view('admin.integrations.index', compact('channelManager', 'cmEndpoints', 'testReport'));
    }

    public function updateChannelManager(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider_name' => ['nullable', 'string', 'max:120'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'partner_id' => ['nullable', 'string', 'max:120'],
            'api_username' => ['nullable', 'string', 'max:120'],
            'api_password' => ['nullable', 'string', 'max:255'],
            'webhook_username' => ['nullable', 'string', 'max:120'],
            'webhook_password' => ['nullable', 'string', 'max:255'],
            'webhook_path' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['enabled'] = $request->boolean('enabled');
        $validated['use_sandbox'] = $request->boolean('use_sandbox');

        $this->integrations->updateChannelManager($validated);

        return redirect()
            ->to(route('admin.integrations.index').'#cm-integration')
            ->with('success', 'Channel Manager integration settings saved.');
    }

    public function testApis(): RedirectResponse
    {
        $report = $this->apiTester->runAll();

        $message = sprintf(
            'API tests complete: %d passed, %d failed out of %d.',
            $report['summary']['passed'],
            $report['summary']['failed'],
            $report['summary']['total']
        );

        return redirect()
            ->to(route('admin.integrations.index').'#cm-api-tests')
            ->with('success', $message)
            ->with('cm_test_report', $report);
    }
}
