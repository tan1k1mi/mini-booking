<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_booking_and_returns_201(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $slot = Slot::factory()->create();

        $response = $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Анна Смирнова',
            'customer_email' => 'anna@example.com',
        ]);

        $response->assertCreated()->assertJsonStructure(['id']);
        $this->assertDatabaseHas('bookings', [
            'slot_id' => $slot->id,
            'customer_email' => 'anna@example.com',
            'cancelled_at' => null,
        ]);
    }

    public function test_returns_409_when_slot_is_already_booked(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $slot = Slot::factory()->create();
        Booking::factory()->for($slot)->create();

        $response = $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Второй пользователь',
            'customer_email' => 'second@example.com',
        ]);

        $response->assertConflict()->assertJsonStructure(['message']);
        $this->assertSame(1, Booking::where('slot_id', $slot->id)->whereNull('cancelled_at')->count());
        $this->assertDatabaseMissing('bookings', [
            'slot_id' => $slot->id,
            'customer_email' => 'second@example.com',
        ]);
    }
}
