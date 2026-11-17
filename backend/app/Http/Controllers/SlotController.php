<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SlotController extends Controller
{
    public function index(): JsonResponse
    {
        $timezone = config('app.timezone');
        $now = now();

        $slots = Slot::query()
            ->with('activeBooking:id,slot_id')
            ->orderBy('start_at')
            ->get()
            ->map(function (Slot $slot) use ($now, $timezone): array {
                $status = match (true) {
                    $slot->start_at->lessThanOrEqualTo($now) => 'past',
                    $slot->activeBooking !== null => 'booked',
                    default => 'available',
                };

                return [
                    'id' => $slot->id,
                    'start_at' => Carbon::parse($slot->start_at)->setTimezone($timezone)->toIso8601String(),
                    'end_at' => Carbon::parse($slot->end_at)->setTimezone($timezone)->toIso8601String(),
                    'status' => $status,
                    'booking_id' => $slot->activeBooking?->id,
                ];
            });

        return response()->json(['data' => $slots, 'timezone' => $timezone]);
    }
}
