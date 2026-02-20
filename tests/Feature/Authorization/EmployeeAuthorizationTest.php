<?php

namespace Tests\Feature\Authorization;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_employees()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_employees()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('employees.index'));

        $response->assertOk();
    }

    public function test_non_admin_cannot_create_employee()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.create'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_employee()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('employees.create'));

        $response->assertOk();
    }

    public function test_non_admin_cannot_update_employee()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.edit', $employee));

        $response->assertForbidden();
    }

    public function test_admin_can_update_employee()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.edit', $employee));

        $response->assertOk();
    }

    public function test_non_admin_cannot_delete_employee()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->delete(route('employees.destroy', $employee));

        $response->assertForbidden();
    }

    public function test_admin_can_delete_employee()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->delete(route('employees.destroy', $employee));

        $response->assertRedirect();
    }
}

