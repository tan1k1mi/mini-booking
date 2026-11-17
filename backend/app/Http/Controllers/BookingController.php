<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slot = Slot::findOrFail($data['slot_id']);

        if ($slot->start_at->lessThanOrEqualTo(now())) {
            return $this->conflict('Этот слот уже прошёл и недоступен для бронирования.');
        }

        try {
            $booking = Booking::create($data);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return $this->conflict('Слот уже занят. Обновите расписание и выберите другое время.');
            }

            throw $exception;
        }

        return response()->json(['id' => $booking->id], Response::HTTP_CREATED);
    }

    public function destroy(Booking $booking): Response
    {
        if ($booking->cancelled_at === null) {
            $booking->update(['cancelled_at' => now()]);
        }

        return response()->noContent();
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_CONFLICT);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['19', '23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
