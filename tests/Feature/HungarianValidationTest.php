<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HungarianValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_are_hungarian(): void
    {
        $this->postJson('/api/quote-requests', ['email' => 'nem-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Adj meg érvényes e-mail-címet.')
            ->assertJsonPath('errors.customer_name.0', 'A(z) név megadása kötelező.');
    }
}
