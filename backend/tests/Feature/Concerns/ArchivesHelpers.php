<?php

namespace Tests\Feature\Concerns;

use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait ArchivesHelpers
{
    protected function makeUser(string $role = User::ROLE_USER): User
    {
        $user = User::factory()->create();
        $user->role = $role; // le rôle n'est pas mass-assignable
        $user->save();

        return $user;
    }

    protected function makeCategory(?string $nom = null): Category
    {
        return Category::create(['nom' => $nom ?? 'Cat '.Str::random(8)]);
    }

    /** Authentifie la prochaine requête comme $user et force les réponses JSON. */
    protected function as(User $user): static
    {
        return $this->actingAs($user, 'sanctum')->withHeader('Accept', 'application/json');
    }

    protected function pdf(string $name = 'contrat.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    protected function upload(User $owner, array $overrides = []): TestResponse
    {
        $category = $overrides['category_id'] ?? $this->makeCategory()->id;

        return $this->as($owner)->post('/api/documents', array_merge([
            'titre' => 'Contrat 2026',
            'description' => 'Contrat de maintenance',
            'mots_cles' => 'contrat,maintenance',
            'category_id' => $category,
            'fichier' => $this->pdf(),
        ], $overrides));
    }

    protected function createDocument(User $owner, array $overrides = []): Document
    {
        $response = $this->upload($owner, $overrides);
        $response->assertCreated();

        return Document::findOrFail($response->json('data.id'));
    }

    protected function share(Document $document, User $with, string $droit): void
    {
        $this->as($document->owner)
            ->postJson("/api/documents/{$document->id}/permissions", ['user_id' => $with->id, 'droit' => $droit])
            ->assertSuccessful();
    }
}
