<?php

namespace Tests\Feature\Authorization;

use App\Models\Meter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeterAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_manage_bills()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('meters.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_manage_bills()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');

        $response = $this->actingAs($user)->get(route('meters.index'));

        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_create_meter()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('meters.create'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_meter()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');

        $response = $this->actingAs($user)->get(route('meters.create'));

        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_update_meter()
    {
        $user = User::factory()->create();
        $meter = Meter::factory()->create();

        $response = $this->actingAs($user)->get(route('meters.edit', $meter));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_update_meter()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $meter = Meter::factory()->create();

        $response = $this->actingAs($user)->get(route('meters.edit', $meter));

        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_delete_meter()
    {
        $user = User::factory()->create();
        $meter = Meter::factory()->create();

        $response = $this->actingAs($user)->delete(route('meters.destroy', $meter));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_delete_meter()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage-bills');
        $meter = Meter::factory()->create();

        $response = $this->actingAs($user)->delete(route('meters.destroy', $meter));

        $response->assertRedirect();
    }
}

