<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'company_email' => 'test.user@sycharsuppliers.com',
        ]);
    }

    public function test_generated_company_addresses_are_unique(): void
    {
        User::factory()->create(['company_email' => 'jane.doe@sycharsuppliers.com']);

        $this->assertSame(
            'jane.doe.2@sycharsuppliers.com',
            User::generateCompanyEmail('Jane Doe')
        );
    }
}
