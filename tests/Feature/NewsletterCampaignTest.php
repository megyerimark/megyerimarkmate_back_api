<?php

namespace Tests\Feature;

use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set(['portfolio.mail_enabled' => true, 'mail.default' => 'smtp', 'portfolio.admin_email' => 'owner@example.com']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        return $u;
    }

    private function subscriber(string $email, string $status, string $lang = 'hu'): NewsletterSubscriber
    {
        $s = new NewsletterSubscriber(['email' => $email, 'status' => $status, 'lang' => $lang, 'consent_text' => 'Teszt hozzájárulás']);
        $s->forceFill(['unsubscribe_token' => bin2hex(random_bytes(32))])->save();
        return $s;
    }

    private function payload(array $extra = []): array
    {
        return ['subject' => 'Őszi akció', 'body' => "Szia!\n\n- Új útmutatók\n- 15% kedvezmény", 'audience' => 'all',
            'button_text' => 'Megnézem', 'button_url' => 'https://megyerimarkmate.hu/utmutatok'] + $extra;
    }

    public function test_only_confirmed_subscribers_get_the_campaign_with_their_own_unsubscribe_link(): void
    {
        $a = $this->subscriber('a@example.com', 'confirmed');
        $this->subscriber('b@example.com', 'pending');
        $this->subscriber('c@example.com', 'unsubscribed');
        $this->subscriber('d@example.com', 'confirmed', 'en');
        $this->actingAs($this->admin());

        $this->postJson('/api/admin/newsletter/campaigns', $this->payload())->assertUnprocessable(); // megerősítés nélkül nem
        $this->postJson('/api/admin/newsletter/campaigns', $this->payload(['audience' => 'hu', 'confirm' => true]))->assertCreated();
        Mail::assertSent(NewsletterCampaignMail::class, 1);
        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $m) use ($a) {
            $this->assertStringContainsString('/hirlevel/leiratkozas#'.$a->unsubscribe_token, $m->unsubscribeUrl);
            $m->assertSeeInHtml('15% kedvezmény');
            $m->assertSeeInHtml('https://megyerimarkmate.hu/utmutatok');
            return $m->hasTo('a@example.com');
        });
        $campaign = NewsletterCampaign::first();
        $this->assertSame(1, $campaign->recipients);
        $this->assertSame(1, $campaign->sent_count);
        $this->getJson('/api/admin/newsletter/campaigns')->assertOk()->assertJsonPath('audience.all', 2)->assertJsonPath('audience.en', 1);
    }

    public function test_test_mail_goes_only_to_admin_and_html_is_escaped(): void
    {
        $this->subscriber('a@example.com', 'confirmed');
        $this->actingAs($this->admin())->postJson('/api/admin/newsletter/campaigns/test', $this->payload(['body' => 'Szia <script>alert(1)</script> világ']))->assertOk();
        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $m) {
            $m->assertDontSeeInHtml('<script>alert(1)</script>', false);
            return $m->isTest && $m->hasTo('owner@example.com');
        });
        Mail::assertSent(NewsletterCampaignMail::class, 1);
        $this->assertSame(0, NewsletterCampaign::count());
    }

    public function test_campaigns_are_admin_only_and_need_https_button(): void
    {
        $this->postJson('/api/admin/newsletter/campaigns', $this->payload(['confirm' => true]))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/admin/newsletter/campaigns', $this->payload(['confirm' => true]))->assertForbidden();
        $this->actingAs($this->admin())->postJson('/api/admin/newsletter/campaigns/test', $this->payload(['button_url' => 'javascript:alert(1)']))->assertUnprocessable();
    }
}
