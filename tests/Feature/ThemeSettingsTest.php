<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    public function test_defaults_are_public(): void
    {
        $this->getJson('/api/site-settings')->assertOk()
            ->assertJsonPath('data.site_palette', 'nightblue')
            ->assertJsonPath('data.seasonal_enabled', '1')
            ->assertJsonPath('data.seasonal_christmas', '1');
    }

    public function test_admin_can_pick_palette_and_toggle_holidays(): void
    {
        $this->actingAs($this->admin())->patchJson('/api/admin/site-settings', [
            'site_palette' => 'graphite', 'seasonal_easter' => false, 'seasonal_enabled' => true,
        ])->assertOk()->assertJsonPath('data.site_palette', 'graphite')->assertJsonPath('data.seasonal_easter', '0');

        $this->getJson('/api/site-settings')->assertJsonPath('data.site_palette', 'graphite')->assertJsonPath('data.seasonal_easter', '0');
    }

    public function test_unknown_palette_is_rejected(): void
    {
        // Az ünnepi témák automatikusan kapcsolnak, alapnak nem választhatók; ismeretlen érték sem kerülhet be.
        foreach (['christmas', 'hotpink', ''] as $palette) {
            $this->actingAs($this->admin())->patchJson('/api/admin/site-settings', ['site_palette' => $palette])
                ->assertStatus(422)->assertJsonValidationErrors('site_palette');
        }
    }

    public function test_guests_cannot_change_theme(): void
    {
        $this->patchJson('/api/admin/site-settings', ['site_palette' => 'aurora'])->assertUnauthorized();
    }
}
