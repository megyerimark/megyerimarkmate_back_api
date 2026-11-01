<?php

namespace Tests\Feature;

use App\Mail\ProjectAccessRenewed;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailHeroImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_image_is_embedded_in_the_sent_message(): void
    {
        config()->set('mail.mailers.array', ['transport' => 'array']);
        $q = new QuoteRequest(['customer_name' => 'Kiss Anna', 'email' => 'anna@example.com', 'website_type' => 'business', 'project_description' => 'Céges weboldal készítése']);
        $q->forceFill(['privacy_notice_acknowledged_at' => now()])->save();

        Mail::mailer('array')->to('anna@example.com')->send(new ProjectAccessRenewed($q, 'https://megyerimarkmate.hu/ugyfel/projekt#x'));

        $message = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $html = $message->getHtmlBody();
        $this->assertStringContainsString('src="cid:', $html);
        $this->assertStringNotContainsString('megyerimarkmate.hu/brand/email-hero.jpg', $html);
        $inline = collect($message->getAttachments())->filter(fn ($part) => str_contains((string) $part->getContentType(), 'image/jpeg'));
        $this->assertCount(1, $inline);
    }
}
