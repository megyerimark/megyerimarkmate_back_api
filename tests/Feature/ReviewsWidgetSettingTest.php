<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsWidgetSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_id_must_be_alphanumeric_so_no_script_can_be_injected(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        $this->actingAs($u);
        $this->patchJson('/api/admin/site-settings', ['reviews_widget_id' => "abc'></script><script>alert(1)"])->assertUnprocessable();
        $this->patchJson('/api/admin/site-settings', ['reviews_widget_id' => 'a1b2c3d4e5', 'google_review_url' => 'https://g.page/r/abc/review'])->assertOk();
        $this->getJson('/api/site-settings')->assertJsonPath('data.reviews_widget_id', 'a1b2c3d4e5');
    }
}
