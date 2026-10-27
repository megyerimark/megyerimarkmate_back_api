<?php

namespace App\Console\Commands;

use App\Mail\AdminPaymentNotice;
use App\Models\ClientSite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Napi emlékeztető: domain / tárhely / SSL lejárat 30, 14, 7, 3, 1 és 0 nappal előtte. */
class ExpiryReminders extends Command
{
    protected $signature = 'portfolio:expiry-reminders';
    protected $description = 'E-mail emlékeztető a közelgő domain-, tárhely- és SSL-lejáratokról';

    public function handle(): int
    {
        $marks = collect([30, 14, 7, 3, 1, 0])->map(fn ($d) => today()->addDays($d)->toDateString())->all();
        $labels = ['domain_expires_on' => 'Domain', 'hosting_expires_on' => 'Tárhely', 'ssl_expires_on' => 'SSL'];
        $lines = [];
        foreach (ClientSite::expiringWithin(30)->get() as $site) {
            foreach ($labels as $field => $label) {
                $date = $site->{$field}?->toDateString();
                if ($date && in_array($date, $marks, true)) {
                    $days = today()->diffInDays($site->{$field}, false);
                    $lines[] = $label.' – '.($site->domain ?: $site->name).': '.$site->{$field}->format('Y. m. d.').($days === 0 ? ' (MA!)' : ' ('.(int) $days.' nap)');
                }
            }
        }
        $owner = config('portfolio.admin_email');
        if (! $lines || ! $owner || ! \App\Support\Mailer::canSend()) {
            $this->info(count($lines).' esedékes lejárat.');
            return self::SUCCESS;
        }
        Mail::to($owner)->send(new AdminPaymentNotice('Közelgő lejáratok ('.count($lines).')', [...$lines, '', 'Hosszabbítsd meg időben, vagy egyeztess az ügyféllel.']));
        $this->info('Emlékeztető elküldve.');
        return self::SUCCESS;
    }
}
