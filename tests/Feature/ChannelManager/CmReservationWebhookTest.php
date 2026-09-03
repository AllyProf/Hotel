<?php

namespace Tests\Feature\ChannelManager;

use App\Models\CmReservation;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class CmReservationWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_webhook_stores_reservation_without_guest_contact_fields(): void
    {
        $this->configureWebhookAuth('cm-user', 'cm-secret');

        $payload = [
            'action' => 'book',
            'hotelCode' => 'sandbox-pms',
            'channel' => 'Goibibo',
            'bookingId' => 'WEBHOOK-BOOK-1',
            'bookedOn' => '2026-09-01 12:00:00',
            'checkin' => '2026-09-05',
            'checkout' => '2026-09-07',
            'segment' => 'OTA',
            'pah' => false,
            'amount' => [
                'amountAfterTax' => 1204,
                'amountBeforeTax' => 1075,
                'tax' => 129,
                'currency' => 'INR',
            ],
            'rooms' => [[
                'roomCode' => 'executive',
                'rateplanCode' => 'executive-s-ep',
                'occupancy' => ['adults' => 1, 'children' => 0],
                'prices' => [['date' => '2026-09-05', 'sellRate' => 537.5]],
            ]],
        ];

        $response = $this->withBasicAuth('cm-user', 'cm-secret')
            ->postJson(route('webhooks.cm.reservations'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Reservation Updated Successfully',
            ]);

        $this->assertDatabaseHas('cm_reservations', [
            'booking_id' => 'WEBHOOK-BOOK-1',
            'hotel_code' => 'sandbox-pms',
            'status' => 'confirmed',
        ]);
    }

    public function test_modify_webhook_replaces_existing_booking_state(): void
    {
        $this->configureWebhookAuth('cm-user', 'cm-secret');

        CmReservation::query()->create([
            'hotel_code' => 'sandbox-pms',
            'booking_id' => 'WEBHOOK-MOD-1',
            'channel' => 'Goibibo',
            'action' => 'book',
            'status' => 'confirmed',
            'checkin' => '2026-09-05',
            'checkout' => '2026-09-07',
            'amount_after_tax' => 1000,
        ]);

        $payload = [
            'action' => 'modify',
            'hotelCode' => 'sandbox-pms',
            'channel' => 'Goibibo',
            'bookingId' => 'WEBHOOK-MOD-1',
            'bookedOn' => '2026-09-01 12:00:00',
            'checkin' => '2026-09-06',
            'checkout' => '2026-09-08',
            'segment' => 'OTA',
            'pah' => true,
            'amount' => [
                'amountAfterTax' => 1500,
                'amountBeforeTax' => 1300,
                'tax' => 200,
                'currency' => 'INR',
            ],
            'rooms' => [[
                'roomCode' => 'executive',
                'rateplanCode' => 'executive-s-ep',
                'occupancy' => ['adults' => 2, 'children' => 0],
                'prices' => [['date' => '2026-09-06', 'sellRate' => 750]],
            ]],
        ];

        $response = $this->withBasicAuth('cm-user', 'cm-secret')
            ->postJson(route('webhooks.cm.reservations'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Reservation Modified Successfully',
            ]);

        $reservation = CmReservation::query()->where('booking_id', 'WEBHOOK-MOD-1')->firstOrFail();
        $this->assertSame('modified', $reservation->status);
        $this->assertSame('2026-09-06', $reservation->checkin?->format('Y-m-d'));
        $this->assertSame('1500.00', (string) $reservation->amount_after_tax);
    }

    public function test_cancel_webhook_marks_reservation_cancelled(): void
    {
        $this->configureWebhookAuth('cm-user', 'cm-secret');

        CmReservation::query()->create([
            'hotel_code' => 'sandbox-pms',
            'booking_id' => 'WEBHOOK-CAN-1',
            'channel' => 'Goibibo',
            'action' => 'book',
            'status' => 'confirmed',
        ]);

        $response = $this->withBasicAuth('cm-user', 'cm-secret')
            ->postJson(route('webhooks.cm.reservations'), [
                'action' => 'cancel',
                'hotelCode' => 'sandbox-pms',
                'channel' => 'Goibibo',
                'bookingId' => 'WEBHOOK-CAN-1',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Reservation Cancelled Successfully',
            ]);

        $this->assertDatabaseHas('cm_reservations', [
            'booking_id' => 'WEBHOOK-CAN-1',
            'status' => 'cancelled',
        ]);
    }

    public function test_webhook_requires_basic_auth_when_credentials_configured(): void
    {
        $this->configureWebhookAuth('cm-user', 'cm-secret');

        $this->postJson(route('webhooks.cm.reservations'), [
            'action' => 'cancel',
            'hotelCode' => 'sandbox-pms',
            'channel' => 'Goibibo',
            'bookingId' => 'NO-AUTH',
        ])->assertUnauthorized();
    }

    private function configureWebhookAuth(string $username, string $password): void
    {
        $setting = PlatformSetting::current();
        $integrations = $setting->integrations ?? [];
        $integrations['channel_manager'] = array_merge(
            app(\App\Services\PlatformIntegrationService::class)->defaultChannelManager(),
            $integrations['channel_manager'] ?? [],
            [
                'enabled' => true,
                'webhook_username' => $username,
                'webhook_password' => Crypt::encryptString($password),
            ]
        );

        $setting->update(['integrations' => $integrations]);
    }
}
