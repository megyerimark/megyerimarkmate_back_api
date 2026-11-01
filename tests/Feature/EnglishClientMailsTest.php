<?php

namespace Tests\Feature;

use App\Mail\ConsultationBooked;
use App\Mail\CustomerQuoteConfirmation;
use App\Mail\ProposalReady;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Aki angolul foglal / kér ajánlatot, az angol leveleket kap; az adminnak szóló levél magyar marad. */
class EnglishClientMailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com', 'portfolio.customer_receipt_enabled' => true]);
    }

    private function subject(Mailable $mail): string
    {
        return $mail->withLocale($mail->locale, fn () => $mail->envelope()->subject);
    }

    public function test_english_booking_gets_english_client_mail_and_hungarian_admin_mail(): void
    {
        $slot = ConsultationSlot::create(['starts_at' => now()->addDays(3)->setTime(10, 0), 'duration_minutes' => 30]);
        $this->postJson('/api/consultations/book', [
            'consultation_slot_id' => $slot->id, 'customer_name' => 'Anna Smith', 'email' => 'anna@example.com',
            'privacy_notice_acknowledged' => true, 'lang' => 'en',
        ])->assertCreated();
        $this->assertSame('en', ConsultationBooking::first()->lang);

        $client = Mail::sent(ConsultationBooked::class, fn ($m) => $m->forClient)->first();
        $this->assertStringStartsWith('Confirmed: consultation', $this->subject($client));
        $html = $client->render();
        $this->assertStringContainsString('Dear Anna Smith,', $html);
        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringContainsString('foglalasom?lang=en#', $html);
        $this->assertStringNotContainsString('Kedves', $html);

        $admin = Mail::sent(ConsultationBooked::class, fn ($m) => ! $m->forClient)->first();
        $this->assertStringContainsString('Új konzultációfoglalás', $admin->render());
    }

    public function test_hungarian_is_the_default(): void
    {
        $slot = ConsultationSlot::create(['starts_at' => now()->addDays(3)->setTime(11, 0), 'duration_minutes' => 30]);
        $this->postJson('/api/consultations/book', [
            'consultation_slot_id' => $slot->id, 'customer_name' => 'Kiss Anna', 'email' => 'anna@example.com', 'privacy_notice_acknowledged' => true,
        ])->assertCreated();
        $client = Mail::sent(ConsultationBooked::class, fn ($m) => $m->forClient)->first();
        $this->assertStringStartsWith('Visszaigazolás: konzultáció', $this->subject($client));
        $this->assertStringContainsString('Kedves Kiss Anna!', $client->render());
    }

    public function test_english_quote_request_and_proposal_mails(): void
    {
        $this->postJson('/api/quote-requests', [
            'customer_name' => 'John Doe', 'email' => 'john@example.com', 'website_type' => 'business', 'design_style' => 'dark-premium',
            'colors' => ['#7950F2'], 'features' => ['contact'], 'project_description' => 'A company website with a quote form.',
            'privacy_notice_acknowledged' => true, 'lang' => 'en',
        ])->assertCreated();
        $quote = QuoteRequest::first();
        $this->assertSame('en', $quote->lang);

        $receipt = Mail::sent(CustomerQuoteConfirmation::class)->first();
        $this->assertSame('Your request has arrived – Márk Megyeri | Web Development', $this->subject($receipt));
        $this->assertStringContainsString('/ugyfel/projekt?lang=en#', $receipt->portalUrl);
        $this->assertStringContainsString('Thank you for reaching out.', $receipt->render());

        $quote->forceFill(['quoted_price_huf' => 382000, 'proposal_valid_until' => now()->addDays(14)])->save();
        $proposal = new ProposalReady($quote->fresh(), 'https://megyerimarkmate.hu/ugyfel/projekt?lang=en#x');
        $this->assertSame('Your tailored proposal is ready (#'.$quote->id.') – Márk Máté Megyeri', $this->subject($proposal));
        $this->assertStringContainsString('HUF 382,000', $proposal->render());
    }

    public function test_every_mail_key_has_an_english_translation(): void
    {
        $en = require resource_path('mail-lang/en.php');
        $files = array_merge(glob(resource_path('views/emails/*.blade.php')), glob(resource_path('views/emails/*/*.blade.php')), glob(app_path('Mail/*.php')), glob(app_path('Support/*.php')));
        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/\\bmt\\('((?:[^'\\\\]|\\\\.)*)'/u", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $key = str_replace("\\'", "'", $key);
                if (! isset($en[$key])) $missing[] = basename($file).': '.$key;
            }
        }
        $this->assertSame([], $missing);
    }
}
