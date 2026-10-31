<?php

namespace App\Console\Commands;

use App\Mail\ConsultationReminder;
use App\Models\ConsultationBooking;
use App\Support\Mailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Óránként fut: a következő ~24 órában kezdődő konzultációkra egyszer emlékeztetőt küld az ügyfélnek.
 * Nem küld, ha a foglalás 24 órán belül született (az ügyfél épp most kapta a visszaigazolást),
 * vagy ha az időpont 2 órán belül kezdődik.
 */
class ConsultationReminders extends Command
{
    protected $signature = 'portfolio:consultation-reminders';
    protected $description = 'Emlékeztető e-mail a közelgő (24 órán belüli) konzultációkról';

    public function handle(): int
    {
        if (! Mailer::canSend()) {
            $this->info('A levélküldés nincs bekapcsolva – kihagyva.');
            return self::SUCCESS;
        }
        $sent = 0;
        $bookings = ConsultationBooking::query()->whereNull('reminder_sent_at')
            ->whereHas('slot', fn ($q) => $q->whereBetween('starts_at', [now()->addHours(2), now()->addHours(25)]))
            ->with('slot')->get();
        foreach ($bookings as $booking) {
            if ($booking->created_at && $booking->created_at->greaterThan($booking->slot->starts_at->copy()->subHours(24))) {
                continue;
            }
            try {
                Mail::to($booking->email)->send(new ConsultationReminder($booking));
                $booking->forceFill(['reminder_sent_at' => now()])->save();
                $sent++;
            } catch (Throwable $e) {
                Log::error('Consultation reminder failed', ['booking_id' => $booking->id, 'exception_type' => $e::class]);
            }
        }
        $this->info($sent.' emlékeztető elküldve.');
        return self::SUCCESS;
    }
}
