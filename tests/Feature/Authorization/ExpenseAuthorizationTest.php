<?php

namespace Tests\Feature\Authorization;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_view_expenses()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('expenses.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_expenses()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('expenses.index'));

        $response->assertOk();
    }

    public function test_accountant_can_view_expenses()
    {
        $user = User::factory()->create();
        $user->assignRole('accountant');

        $response = $this->actingAs($user)->get(route('expenses.index'));

        $response->assertOk();
    }

    public function test_non_admin_cannot_create_expense()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('expenses.create'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_expense()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('expenses.create'));

        $response->assertOk();
    }

    public function test_non_admin_cannot_update_expense()
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create();

        $response = $this->actingAs($user)->get(route('expenses.edit', $expense));

        $response->assertForbidden();
    }

    public function test_admin_can_update_expense()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $expense = Expense::factory()->create();

        $response = $this->actingAs($user)->get(route('expenses.edit', $expense));

        $response->assertOk();
    }

    public function test_non_admin_cannot_delete_expense()
    {
        $user = User::factory()->create();
        $expense = Expense::factory()->create();

        $response = $this->actingAs($user)->delete(route('expenses.destroy', $expense));

        $response->assertForbidden();
    }

    public function test_admin_can_delete_expense()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $expense = Expense::factory()->create();

        $response = $this->actingAs($user)->delete(route('expenses.destroy', $expense));

        $response->assertRedirect();
    }
}

