<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ArchivesHelpers;
use Tests\TestCase;

/** Rôle archiviste et circuit de validation : en_attente -> valide | rejete -> en_attente. */
class ValidationWorkflowTest extends TestCase
{
    use ArchivesHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_archiviste_can_validate_a_document_and_the_decision_is_traced(): void
    {
        $owner = $this->makeUser();
        $archiviste = $this->makeUser(User::ROLE_ARCHIVISTE);
        $doc = $this->createDocument($owner);

        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/validate")
            ->assertOk()
            ->assertJsonPath('data.validation', 'valide')
            ->assertJsonPath('data.status', 'valide')
            ->assertJsonPath('data.status_label', 'Validé')
            ->assertJsonPath('data.validated_by.id', $archiviste->id)
            ->assertJsonPath('data.motif_rejet', null);

        $this->assertNotNull($doc->fresh()->validated_at);
    }

    public function test_rejecting_requires_a_reason_and_stores_it(): void
    {
        $archiviste = $this->makeUser(User::ROLE_ARCHIVISTE);
        $doc = $this->createDocument($this->makeUser());

        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/reject", [])
            ->assertUnprocessable()->assertJsonValidationErrors('motif');
        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/reject", ['motif' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('motif');

        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/reject", ['motif' => 'Pièce illisible, merci de rescanner.'])
            ->assertOk()
            ->assertJsonPath('data.validation', 'rejete')
            ->assertJsonPath('data.status_label', 'Rejeté')
            ->assertJsonPath('data.motif_rejet', 'Pièce illisible, merci de rescanner.');
    }

    public function test_standard_user_cannot_validate_or_reject_even_their_own_document(): void
    {
        $owner = $this->makeUser();
        $doc = $this->createDocument($owner);

        $this->as($owner)->patchJson("/api/documents/{$doc->id}/validate")->assertForbidden();
        $this->as($owner)->patchJson("/api/documents/{$doc->id}/reject", ['motif' => 'Motif valable'])->assertForbidden();
        $this->as($owner)->patchJson("/api/documents/{$doc->id}/reopen")->assertForbidden();

        $this->assertSame('en_attente', $doc->fresh()->validation);
    }

    public function test_repeating_the_same_decision_returns_a_conflict(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $doc = $this->createDocument($this->makeUser());

        $this->as($admin)->patchJson("/api/documents/{$doc->id}/reopen")->assertStatus(409); // déjà en attente
        $this->as($admin)->patchJson("/api/documents/{$doc->id}/validate")->assertOk();
        $this->as($admin)->patchJson("/api/documents/{$doc->id}/validate")->assertStatus(409);
    }

    public function test_reopen_clears_the_decision(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $doc = $this->createDocument($this->makeUser());

        $this->as($admin)->patchJson("/api/documents/{$doc->id}/reject", ['motif' => 'Document incomplet.'])->assertOk();
        $this->as($admin)->patchJson("/api/documents/{$doc->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.validation', 'en_attente')
            ->assertJsonPath('data.motif_rejet', null)
            ->assertJsonPath('data.validated_by', null);
    }

    public function test_archived_documents_cannot_be_validated(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $doc = $this->createDocument($this->makeUser());
        $this->as($admin)->patchJson("/api/documents/{$doc->id}/archive")->assertOk();

        $this->as($admin)->patchJson("/api/documents/{$doc->id}/validate")->assertForbidden();
    }

    public function test_a_new_version_sends_the_document_back_to_validation(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $doc = $this->createDocument($owner);
        $this->as($admin)->patchJson("/api/documents/{$doc->id}/validate")->assertOk();

        $this->as($owner)->post("/api/documents/{$doc->id}/versions", ['fichier' => $this->pdf('v2.pdf')])->assertCreated();

        $fresh = $doc->fresh();
        $this->assertSame('en_attente', $fresh->validation);
        $this->assertNull($fresh->validated_by);
    }

    public function test_archiviste_sees_all_documents_and_can_edit_but_not_delete_or_share(): void
    {
        $owner = $this->makeUser();
        $archiviste = $this->makeUser(User::ROLE_ARCHIVISTE);
        $doc = $this->createDocument($owner);

        $this->as($archiviste)->getJson('/api/documents')->assertOk()->assertJsonCount(1, 'data');
        $this->as($archiviste)->getJson("/api/documents/{$doc->id}")
            ->assertOk()
            ->assertJsonPath('data.my_right', 'archiviste')
            ->assertJsonPath('data.can.validate', true)
            ->assertJsonPath('data.can.update', true)
            ->assertJsonPath('data.can.delete', false)
            ->assertJsonPath('data.can.share', false);

        $this->as($archiviste)->putJson("/api/documents/{$doc->id}", [
            'titre' => 'Titre corrigé', 'category_id' => $doc->category_id,
        ])->assertOk();
        $this->as($archiviste)->deleteJson("/api/documents/{$doc->id}")->assertForbidden();

        // Un archiviste peut archiver puis restaurer n'importe quel document.
        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/archive")->assertOk();
        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/restore")->assertOk();
    }

    public function test_standard_user_still_sees_only_their_documents(): void
    {
        $this->createDocument($this->makeUser());
        $other = $this->makeUser();

        $this->as($other)->getJson('/api/documents')->assertOk()->assertJsonCount(0, 'data');
    }
}
