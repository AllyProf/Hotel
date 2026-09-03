<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\CmReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class CmReservationController extends Controller
{
    public function __construct(private CmReservationService $reservations) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->authorizeWebhook($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:book,modify,cancel'],
            'hotelCode' => ['required', 'string'],
            'channel' => ['required', 'string'],
            'bookingId' => ['required', 'string'],
            'checkin' => ['nullable', 'date'],
            'checkout' => ['nullable', 'date'],
            'amount' => ['nullable', 'array'],
            'guest' => ['nullable', 'array'],
            'rooms' => ['nullable', 'array'],
        ]);

        $reservation = $this->reservations->storeFromWebhook($request->all());

        $message = match ($validated['action']) {
            'book' => 'Reservation Updated Successfully',
            'modify' => 'Reservation Modified Successfully',
            'cancel' => 'Reservation Cancelled Successfully',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'received' => [
                'action' => $validated['action'],
                'bookingId' => $validated['bookingId'],
                'hotelCode' => $validated['hotelCode'],
                'id' => $reservation->id,
            ],
        ]);
    }

    private function authorizeWebhook(Request $request): bool
    {
        $integrations = PlatformSetting::current()->integrations['channel_manager'] ?? [];
        $username = $integrations['webhook_username'] ?? '';
        $password = $integrations['webhook_password'] ?? null;

        if ($username === '' || empty($password)) {
            return true;
        }

        try {
            $expected = Crypt::decryptString($password);
        } catch (\Throwable) {
            return false;
        }

        $providedUser = $request->getUser();
        $providedPass = $request->getPassword();

        if ($providedUser === null || $providedPass === null) {
            [$providedUser, $providedPass] = $this->basicAuthFromHeader($request);
        }

        return $providedUser === $username && hash_equals($expected, (string) $providedPass);
    }

    /** @return array{0: string, 1: string} */
    private function basicAuthFromHeader(Request $request): array
    {
        $header = (string) $request->header('Authorization', '');

        if ($header === '') {
            $header = (string) ($request->server->get('HTTP_AUTHORIZATION') ?? '');
        }

        if (! preg_match('/^Basic\s+(.+)$/i', $header, $matches)) {
            return ['', ''];
        }

        $decoded = base64_decode($matches[1], true);

        if ($decoded === false || ! str_contains($decoded, ':')) {
            return ['', ''];
        }

        return explode(':', $decoded, 2);
    }
}
