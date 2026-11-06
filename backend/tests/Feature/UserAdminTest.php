<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ArchivesHelpers;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use ArchivesHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_only_admins_can_manage_users(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->as($user)->getJson('/api/users')->assertForbidden();
        $this->as($user)->putJson("/api/users/{$other->id}", ['role' => 'admin'])->assertForbidden();
        $this->as($user)->deleteJson("/api/users/{$other->id}")->assertForbidden();

        $this->as($this->makeUser('admin'))->getJson('/api/users')->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_change_a_role_but_not_his_own(): void
    {
        $admin = $this->makeUser('admin');
        $user = $this->makeUser();

        $this->as($admin)->putJson("/api/users/{$user->id}", ['role' => 'admin'])
            ->assertOk()->assertJsonPath('data.role', 'admin');

        $this->as($admin)->putJson("/api/users/{$admin->id}", ['role' => 'user'])->assertUnprocessable();
        $this->as($admin)->putJson("/api/users/{$user->id}", ['role' => 'superman'])->assertUnprocessable();
    }

    public function test_admin_cannot_delete_himself_or_a_user_who_owns_documents(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser();
        $this->createDocument($owner);
        $empty = $this->makeUser();

        $this->as($admin)->deleteJson("/api/users/{$admin->id}")->assertUnprocessable();
        $this->as($admin)->deleteJson("/api/users/{$owner->id}")->assertStatus(409);
        $this->as($admin)->deleteJson("/api/users/{$empty->id}")->assertNoContent();
    }

    public function test_user_lookup_is_available_to_everyone_and_returns_minimal_fields(): void
    {
        $me = $this->makeUser();
        $sara = $this->makeUser();
        $sara->forceFill(['name' => 'Sara Alaoui'])->save();

        $this->as($me)->getJson('/api/users/lookup?q=Sara')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sara->id)
            ->assertJsonMissingPath('data.0.role');

        // Moins de 2 caractères : aucun résultat
        $this->as($me)->getJson('/api/users/lookup?q=S')->assertJsonCount(0, 'data');
    }
}
