<?php

namespace App\Http\Controllers;

use App\Mail\ConsultationBooked;
use App\Mail\ConsultationCancelled;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Support\Mailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Konzultáció önkiszolgálás a levélben kapott személyes linkkel (/konzultacio/foglalasom#<kulcs>):
 * megtekintés, lemondás és átfoglalás – legkésőbb 2 órával a kezdés előtt. A kulcs csak hash-ként van tárolva.
 */
class ConsultationManageController extends Controller
{
    private const LOCK_HOURS = 2;

    private function booking(Request $request): ConsultationBooking
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/D']]);
        return ConsultationBooking::byManageToken($data['token']);
    }

    private function changeable(ConsultationBooking $booking): bool
    {
        return $booking->slot->starts_at->greaterThan(now()->addHours(self::LOCK_HOURS));
    }

    public function show(Request $request): JsonResponse
    {
        $booking = $this->booking($request);
        $changeable = $this->changeable($booking);
        $slots = $changeable ? ConsultationSlot::query()->where('starts_at', '>=', now()->addHours(self::LOCK_HOURS))
            ->where('starts_at', '<=', now()->addDays(60))->whereDoesntHave('booking')->orderBy('starts_at')->limit(50)
            ->get(['id', 'starts_at', 'duration_minutes']) : collect();
        return response()->json(['data' => [
            'reference' => $booking->id,
            'customer_name' => $booking->customer_name,
            'starts_at' => $booking->slot->starts_at->toIso8601String(),
            'duration_minutes' => $booking->slot->duration_minutes,
            'changeable' => $changeable,
            'available' => $slots,
        ]])->header('Cache-Control', 'no-store, private');
    }

    public function cancel(Request $request): JsonResponse
    {
        $booking = $this->booking($request);
        if (! $this->changeable($booking)) {
            throw ValidationException::withMessages(['token' => 'A kezdés előtti 2 órában már nem mondható le online – kérlek, írj e-mailt.']);
        }
        $snapshot = clone $booking;
        $snapshot->setRelation('slot', clone $booking->slot);
        $booking->delete(); // az időpont újra foglalható lesz
        $this->mailBoth(fn (bool $forClient) => new ConsultationCancelled($snapshot, $forClient), $snapshot);
        return response()->json(['message' => 'A foglalást lemondtad.'])->header('Cache-Control', 'no-store, private');
    }

    public function reschedule(Request $request): JsonResponse
    {
        $booking = $this->booking($request);
        $data = $request->validate(['consultation_slot_id' => ['required', 'integer', 'exists:consultation_slots,id']]);
        if (! $this->changeable($booking)) {
            throw ValidationException::withMessages(['token' => 'A kezdés előtti 2 órában már nem foglalható át online – kérlek, írj e-mailt.']);
        }
        DB::transaction(function () use ($booking, $data) {
            $slot = ConsultationSlot::query()->lockForUpdate()->findOrFail($data['consultation_slot_id']);
            if ($slot->id === $booking->consultation_slot_id) return;
            if ($slot->starts_at->lessThan(now()->addHours(self::LOCK_HOURS)) || $slot->booking()->exists()) {
                throw ValidationException::withMessages(['consultation_slot_id' => 'Ez az időpont már nem foglalható. Válassz egy másikat.']);
            }
            $booking->forceFill(['consultation_slot_id' => $slot->id, 'reminder_sent_at' => null])->save();
        });
        $booking->load('slot');
        $this->mailBoth(fn (bool $forClient) => new ConsultationBooked($booking, $forClient, changed: true), $booking);
        return response()->json(['message' => 'Az időpontot módosítottad.', 'data' => [
            'starts_at' => $booking->slot->starts_at->toIso8601String(), 'duration_minutes' => $booking->slot->duration_minutes,
        ]])->header('Cache-Control', 'no-store, private');
    }

    /** Visszaigazolás az ügyfélnek, értesítés nekem – a válasz után küldve. */
    private function mailBoth(\Closure $make, ConsultationBooking $booking): void
    {
        if (! Mailer::canSend()) return;
        $recipients = [[$booking->email, true]];
        $admin = (string) config('portfolio.admin_email');
        if ($admin !== '' && mb_strtolower($admin) !== mb_strtolower($booking->email)) $recipients[] = [$admin, false];
        \Illuminate\Support\defer(function () use ($recipients, $make, $booking) {
            foreach ($recipients as [$email, $forClient]) {
                try {
                    Mail::to($email)->send($make($forClient));
                } catch (\Throwable $e) {
                    Log::error('Consultation change mail failed', ['booking_id' => $booking->id, 'exception_type' => $e::class]);
                }
            }
        });
    }
}
