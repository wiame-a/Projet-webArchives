<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ArchivesHelpers;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use ArchivesHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_any_authenticated_user_can_list_categories(): void
    {
        $this->makeCategory('Juridique');

        $this->as($this->makeUser())->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.nom', 'Juridique');
    }

    public function test_regular_user_cannot_create_update_or_delete_a_category(): void
    {
        $user = $this->makeUser();
        $category = $this->makeCategory();

        $this->as($user)->postJson('/api/categories', ['nom' => 'Nouvelle'])->assertForbidden();
        $this->as($user)->putJson("/api/categories/{$category->id}", ['nom' => 'Renommée'])->assertForbidden();
        $this->as($user)->deleteJson("/api/categories/{$category->id}")->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_a_category(): void
    {
        $admin = $this->makeUser('admin');

        $id = $this->as($admin)->postJson('/api/categories', ['nom' => 'Financier', 'description' => 'Factures'])
            ->assertCreated()->json('data.id');

        $this->as($admin)->putJson("/api/categories/{$id}", ['nom' => 'Finance'])
            ->assertOk()->assertJsonPath('data.nom', 'Finance');

        $this->as($admin)->deleteJson("/api/categories/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    public function test_category_name_must_be_unique(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeCategory('Juridique');

        $this->as($admin)->postJson('/api/categories', ['nom' => 'Juridique'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nom']);
    }

    public function test_updating_a_category_without_changing_its_name_is_allowed(): void
    {
        $admin = $this->makeUser('admin');
        $category = $this->makeCategory('Technique');

        $this->as($admin)->putJson("/api/categories/{$category->id}", ['nom' => 'Technique', 'description' => 'Maj'])
            ->assertOk();
    }

    public function test_a_category_containing_documents_cannot_be_deleted(): void
    {
        $admin = $this->makeUser('admin');
        $category = $this->makeCategory();
        $this->createDocument($this->makeUser(), ['category_id' => $category->id]);

        $this->as($admin)->deleteJson("/api/categories/{$category->id}")
            ->assertStatus(409);

        $this->assertTrue(Category::whereKey($category->id)->exists());
    }
}
