<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_super_admins_index(): void
    {
        $actor = User::factory()->superAdmin()->create([
            'email' => 'actor@test.hr',
        ]);

        User::factory()->superAdmin()->create([
            'name' => 'Drugi Admin',
            'email' => 'drugi@test.hr',
        ]);

        $this->actingAs($actor)
            ->get(route('admin.super-admins.index'))
            ->assertOk()
            ->assertSee('Super-admin korisnici')
            ->assertSee('Drugi Admin')
            ->assertSee('drugi@test.hr');
    }

    public function test_super_admin_can_create_super_admin(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $this->actingAs($actor)
            ->post(route('admin.super-admins.store'), [
                'name' => 'Novi Admin',
                'email' => 'novi-admin@test.hr',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('admin.super-admins.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'novi-admin@test.hr',
            'is_super_admin' => true,
        ]);
    }

    public function test_super_admin_can_update_super_admin(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $target = User::factory()->superAdmin()->create([
            'name' => 'Stari naziv',
            'email' => 'stari@test.hr',
        ]);

        $this->actingAs($actor)
            ->patch(route('admin.super-admins.update', $target), [
                'name' => 'Novi naziv',
                'email' => 'stari@test.hr',
            ])
            ->assertRedirect(route('admin.super-admins.index'));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Novi naziv',
        ]);
    }

    public function test_super_admin_cannot_delete_self(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $this->actingAs($actor)
            ->delete(route('admin.super-admins.destroy', $actor))
            ->assertRedirect(route('admin.super-admins.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_super_admin_cannot_delete_last_super_admin(): void
    {
        $onlyAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($onlyAdmin)
            ->delete(route('admin.super-admins.destroy', $onlyAdmin))
            ->assertRedirect(route('admin.super-admins.index'))
            ->assertSessionHas('warning');
    }
}
