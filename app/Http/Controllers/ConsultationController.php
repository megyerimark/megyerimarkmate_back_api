<?php

namespace App\Http\Controllers;

use App\Mail\ConsultationBooked;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ConsultationController extends Controller
{
    public function available(): JsonResponse
    {
        $slots = ConsultationSlot::query()->where('starts_at', '>=', now()->addHours(2))
            ->where('starts_at', '<=', now()->addDays(60))
            ->whereDoesntHave('booking')->orderBy('starts_at')->limit(50)
            ->get(['id', 'starts_at', 'duration_minutes']);
        return response()->json(['data' => $slots])->header('Cache-Control', 'no-store');
    }

    public function book(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consultation_slot_id' => ['required', 'integer', 'exists:consultation_slots,id'],
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'privacy_notice_acknowledged' => ['required', 'accepted'],
            'website' => ['nullable', 'max:0'],
        ]);
        $booking = DB::transaction(function () use ($data): ConsultationBooking {
            $slot = ConsultationSlot::query()->lockForUpdate()->findOrFail($data['consultation_slot_id']);
            if ($slot->starts_at->lessThan(now()->addHours(2)) || $slot->booking()->exists()) {
                throw ValidationException::withMessages(['consultation_slot_id' => 'Ez az időpont már nem foglalható. Válassz egy másikat.']);
            }
            return $slot->booking()->create([
                'customer_name' => $data['customer_name'], 'email' => $data['email'],
                'phone' => $data['phone'] ?? null, 'notes' => $data['notes'] ?? null,
                'privacy_notice_acknowledged_at' => now(),
            ]);
        });
        $booking->load('slot');
        if (config('portfolio.mail_enabled') && ! in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            // Küldés a válasz után, hogy a foglalás azonnal visszaigazolódjon.
            \Illuminate\Support\defer(function () use ($booking) {
                foreach (array_filter([$booking->email, config('portfolio.admin_email')]) as $email) {
                    try {
                        Mail::to($email)->send(new ConsultationBooked($booking, $email === $booking->email));
                    } catch (\Throwable $exception) {
                        Log::error('Consultation confirmation failed', ['booking_id' => $booking->id, 'exception_type' => $exception::class]);
                    }
                }
            });
        }
        return response()->json([
            'message' => 'Az időpontot lefoglaltuk.',
            'reference' => $booking->id,
            'starts_at' => $booking->slot->starts_at->toIso8601String(),
            'duration_minutes' => $booking->slot->duration_minutes,
        ], 201)->header('Cache-Control', 'no-store');
    }
}
