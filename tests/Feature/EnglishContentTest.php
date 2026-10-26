<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnglishContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_and_projects_accept_optional_english_fields(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['is_admin' => true])->save();
        $this->actingAs($u);
        $this->postJson('/api/admin/services', ['title' => 'Weboldal', 'summary' => 'Egyedi oldal', 'title_en' => 'Website', 'summary_en' => 'Custom site',
            'features_en' => ['Responsive'], 'is_active' => true])->assertCreated();
        $this->getJson('/api/services')->assertJsonPath('data.0.title_en', 'Website')->assertJsonPath('data.0.features_en.0', 'Responsive');
        $this->postJson('/api/admin/projects', ['title' => 'Projekt', 'summary' => 'Összefoglaló', 'title_en' => 'Project', 'summary_en' => 'Summary', 'is_demo' => false, 'is_published' => true])->assertCreated();
        $this->getJson('/api/projects')->assertJsonPath('data.0.title_en', 'Project');
    }
}
