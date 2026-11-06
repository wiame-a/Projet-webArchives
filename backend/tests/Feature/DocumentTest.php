<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ArchivesHelpers;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use ArchivesHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ---------------------------------------------------------------- Création

    public function test_upload_creates_the_document_and_version_1_and_stores_the_file_privately(): void
    {
        $owner = $this->makeUser();

        $response = $this->upload($owner)->assertCreated();

        $response->assertJsonPath('data.statut', 'actif')
            ->assertJsonPath('data.latest_version.numero_version', 1)
            ->assertJsonPath('data.latest_version.nom_original', 'contrat.pdf')
            ->assertJsonPath('data.my_right', 'proprietaire');

        $this->assertDatabaseHas('documents', ['user_id' => $owner->id, 'statut' => 'actif']);
        $this->assertDatabaseHas('document_versions', ['numero_version' => 1, 'nom_original' => 'contrat.pdf']);

        // Fichier présent sur le disque privé, et chemin interne non exposé dans le JSON.
        $path = \App\Models\DocumentVersion::first()->chemin_fichier;
        Storage::disk('local')->assertExists($path);
        $this->assertStringNotContainsString($path, $response->getContent());
    }

    public function test_upload_rejects_forbidden_formats_and_files_over_10_mb(): void
    {
        $owner = $this->makeUser();

        $this->upload($owner, ['fichier' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertUnprocessable()->assertJsonValidationErrors(['fichier']);

        $this->upload($owner, ['fichier' => UploadedFile::fake()->create('script.php', 10, 'text/x-php')])
            ->assertUnprocessable()->assertJsonValidationErrors(['fichier']);

        $this->upload($owner, ['fichier' => $this->pdf('gros.pdf', 10241)])
            ->assertUnprocessable()->assertJsonValidationErrors(['fichier']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_upload_accepts_docx_xlsx_and_images(): void
    {
        $owner = $this->makeUser();

        foreach ([
            ['rapport.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['budget.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['photo.png', 'image/png'],
            ['scan.jpg', 'image/jpeg'],
        ] as [$name, $mime]) {
            $this->upload($owner, ['fichier' => UploadedFile::fake()->create($name, 50, $mime)])->assertCreated();
        }

        $this->assertDatabaseCount('documents', 4);
    }

    public function test_upload_requires_title_category_and_file(): void
    {
        $this->as($this->makeUser())->post('/api/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['titre', 'category_id', 'fichier']);
    }

    // ----------------------------------------------------------------- Accès

    public function test_a_user_cannot_see_or_list_someone_elses_document(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        $document = $this->createDocument($owner);

        $this->as($stranger)->getJson("/api/documents/{$document->id}")->assertForbidden();
        $this->as($stranger)->getJson('/api/documents')->assertOk()->assertJsonCount(0, 'data');
        $this->as($stranger)->getJson("/api/documents/{$document->id}/versions")->assertForbidden();
    }

    public function test_admin_can_access_every_document(): void
    {
        $document = $this->createDocument($this->makeUser());
        $admin = $this->makeUser('admin');

        $this->as($admin)->getJson("/api/documents/{$document->id}")->assertOk()->assertJsonPath('data.my_right', 'admin');
        $this->as($admin)->getJson('/api/documents')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_owner_can_update_metadata_but_a_stranger_cannot(): void
    {
        $owner = $this->makeUser();
        $document = $this->createDocument($owner);
        $payload = ['titre' => 'Nouveau titre', 'category_id' => $document->category_id, 'mots_cles' => 'a,b'];

        $this->as($owner)->putJson("/api/documents/{$document->id}", $payload)
            ->assertOk()->assertJsonPath('data.titre', 'Nouveau titre');

        $this->as($this->makeUser())->putJson("/api/documents/{$document->id}", $payload)->assertForbidden();
    }

    // -------------------------------------------------------------- Versions

    public function test_adding_a_version_increments_the_number_and_keeps_previous_versions(): void
    {
        $owner = $this->makeUser();
        $document = $this->createDocument($owner);

        $this->as($owner)->post("/api/documents/{$document->id}/versions", ['fichier' => $this->pdf('v2.pdf')])
            ->assertCreated()->assertJsonPath('data.numero_version', 2);

        $this->as($owner)->post("/api/documents/{$document->id}/versions", ['fichier' => $this->pdf('v3.pdf')])
            ->assertCreated()->assertJsonPath('data.numero_version', 3);

        $this->assertSame([3, 2, 1], $document->versions()->pluck('numero_version')->all());

        // Les trois fichiers existent toujours.
        foreach ($document->versions as $version) {
            Storage::disk('local')->assertExists($version->chemin_fichier);
        }

        $this->as($owner)->getJson("/api/documents/{$document->id}/versions")
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.numero_version', 3);
    }

    public function test_version_numbers_are_independent_per_document(): void
    {
        $owner = $this->makeUser();
        $a = $this->createDocument($owner);
        $b = $this->createDocument($owner);

        $this->as($owner)->post("/api/documents/{$a->id}/versions", ['fichier' => $this->pdf()])->assertCreated();

        $this->assertSame(2, $a->versions()->max('numero_version'));
        $this->assertSame(1, $b->versions()->max('numero_version'));
    }

    public function test_download_streams_the_requested_version_to_authorized_users_only(): void
    {
        $owner = $this->makeUser();
        $document = $this->createDocument($owner);
        $version = $document->versions()->first();

        $this->as($owner)->get("/api/documents/{$document->id}/versions/{$version->id}/download")
            ->assertOk()
            ->assertDownload('contrat.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->as($this->makeUser())->get("/api/documents/{$document->id}/versions/{$version->id}/download")
            ->assertForbidden();
    }

    public function test_download_refuses_a_version_belonging_to_another_document(): void
    {
        $owner = $this->makeUser();
        $mine = $this->createDocument($owner);
        $other = $this->createDocument($this->makeUser());
        $foreignVersion = $other->versions()->first();

        // L'utilisateur a accès à $mine, mais tente de télécharger la version d'un autre document.
        $this->as($owner)->get("/api/documents/{$mine->id}/versions/{$foreignVersion->id}/download")
            ->assertNotFound();
    }

    // -------------------------------------------------------------- Partages

    public function test_read_only_share_allows_viewing_and_downloading_but_not_editing(): void
    {
        $owner = $this->makeUser();
        $reader = $this->makeUser();
        $document = $this->createDocument($owner);
        $this->share($document, $reader, 'lecture');
        $version = $document->versions()->first();

        $this->as($reader)->getJson("/api/documents/{$document->id}")
            ->assertOk()->assertJsonPath('data.my_right', 'lecture')->assertJsonPath('data.can.update', false);
        $this->as($reader)->get("/api/documents/{$document->id}/versions/{$version->id}/download")->assertOk();
        $this->as($reader)->getJson('/api/documents')->assertJsonCount(1, 'data');

        $payload = ['titre' => 'Piraté', 'category_id' => $document->category_id];
        $this->as($reader)->putJson("/api/documents/{$document->id}", $payload)->assertForbidden();
        $this->as($reader)->post("/api/documents/{$document->id}/versions", ['fichier' => $this->pdf()])->assertForbidden();
        $this->as($reader)->deleteJson("/api/documents/{$document->id}")->assertForbidden();
        $this->as($reader)->patchJson("/api/documents/{$document->id}/archive")->assertForbidden();
        $this->as($reader)->getJson("/api/documents/{$document->id}/permissions")->assertForbidden();
    }

    public function test_edit_share_allows_updating_and_versioning_but_not_delete_share_or_archive(): void
    {
        $owner = $this->makeUser();
        $editor = $this->makeUser();
        $third = $this->makeUser();
        $document = $this->createDocument($owner);
        $this->share($document, $editor, 'modification');

        $this->as($editor)->putJson("/api/documents/{$document->id}", [
            'titre' => 'Modifié par l\'éditeur', 'category_id' => $document->category_id,
        ])->assertOk();
        $this->as($editor)->post("/api/documents/{$document->id}/versions", ['fichier' => $this->pdf()])->assertCreated();

        $this->as($editor)->deleteJson("/api/documents/{$document->id}")->assertForbidden();
        $this->as($editor)->patchJson("/api/documents/{$document->id}/archive")->assertForbidden();
        $this->as($editor)->postJson("/api/documents/{$document->id}/permissions", ['user_id' => $third->id, 'droit' => 'lecture'])
            ->assertForbidden();
    }

    public function test_sharing_twice_updates_the_right_and_revoking_removes_access(): void
    {
        $owner = $this->makeUser();
        $guest = $this->makeUser();
        $document = $this->createDocument($owner);

        $this->share($document, $guest, 'lecture');
        $this->share($document, $guest, 'modification'); // met à jour, ne duplique pas
        $this->assertDatabaseCount('document_permissions', 1);
        $this->assertDatabaseHas('document_permissions', ['user_id' => $guest->id, 'droit' => 'modification']);

        $this->as($owner)->deleteJson("/api/documents/{$document->id}/permissions/{$guest->id}")->assertNoContent();

        $this->as($guest)->getJson("/api/documents/{$document->id}")->assertForbidden();
    }

    public function test_the_owner_cannot_share_a_document_with_himself_and_invalid_rights_are_rejected(): void
    {
        $owner = $this->makeUser();
        $document = $this->createDocument($owner);

        $this->as($owner)->postJson("/api/documents/{$document->id}/permissions", ['user_id' => $owner->id, 'droit' => 'lecture'])
            ->assertUnprocessable()->assertJsonValidationErrors(['user_id']);

        $this->as($owner)->postJson("/api/documents/{$document->id}/permissions", ['user_id' => $this->makeUser()->id, 'droit' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors(['droit']);
    }

    // ------------------------------------------------------------- Archivage

    public function test_archiving_locks_edition_but_keeps_the_document_readable_and_restorable(): void
    {
        $owner = $this->makeUser();
        $document = $this->createDocument($owner);
        $version = $document->versions()->first();

        $this->as($owner)->patchJson("/api/documents/{$document->id}/archive")
            ->assertOk()->assertJsonPath('data.statut', 'archive');
        $this->assertNotNull($document->fresh()->archived_at);

        // Édition verrouillée
        $this->as($owner)->putJson("/api/documents/{$document->id}", [
            'titre' => 'Tentative', 'category_id' => $document->category_id,
        ])->assertForbidden();
        $this->as($owner)->post("/api/documents/{$document->id}/versions", ['fichier' => $this->pdf()])->assertForbidden();

        // Consultation et téléchargement toujours possibles
        $this->as($owner)->getJson("/api/documents/{$document->id}")->assertOk()->assertJsonPath('data.can.update', false);
        $this->as($owner)->get("/api/documents/{$document->id}/versions/{$version->id}/download")->assertOk();
        $this->as($owner)->getJson('/api/documents?statut=archive')->assertJsonCount(1, 'data');

        // Archiver deux fois : conflit
        $this->as($owner)->patchJson("/api/documents/{$document->id}/archive")->assertStatus(409);

        // Restauration
        $this->as($owner)->patchJson("/api/documents/{$document->id}/restore")
            ->assertOk()->assertJsonPath('data.statut', 'actif');
        $this->assertNull($document->fresh()->archived_at);
        $this->as($owner)->putJson("/api/documents/{$document->id}", [
            'titre' => 'Modifiable à nouveau', 'category_id' => $document->category_id,
        ])->assertOk();
    }

    public function test_editor_cannot_edit_an_archived_document_even_with_modification_right(): void
    {
        $owner = $this->makeUser();
        $editor = $this->makeUser();
        $document = $this->createDocument($owner);
        $this->share($document, $editor, 'modification');

        $this->as($owner)->patchJson("/api/documents/{$document->id}/archive")->assertOk();

        $this->as($editor)->putJson("/api/documents/{$document->id}", [
            'titre' => 'X', 'category_id' => $document->category_id,
        ])->assertForbidden();
    }

    // ----------------------------------------------------------- Suppression

    public function test_deleting_a_document_removes_its_versions_shares_and_files(): void
    {
        $owner = $this->makeUser();
        $document = $this->createDocument($owner);
        $this->as($owner)->post("/api/documents/{$document->id}/versions", ['fichier' => $this->pdf()])->assertCreated();
        $this->share($document, $this->makeUser(), 'lecture');
        $paths = $document->versions()->pluck('chemin_fichier');

        $this->as($owner)->deleteJson("/api/documents/{$document->id}")->assertNoContent();

        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_versions', 0);
        $this->assertDatabaseCount('document_permissions', 0);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    // -------------------------------------------------------------- Recherche

    public function test_search_combines_filters_and_returns_only_accessible_documents(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();
        $juridique = $this->makeCategory('Juridique');
        $finance = $this->makeCategory('Financier');

        $contrat = $this->createDocument($alice, [
            'titre' => 'Contrat fournisseur', 'mots_cles' => 'contrat,fournisseur', 'category_id' => $juridique->id,
        ]);
        $facture = $this->createDocument($alice, [
            'titre' => 'Facture mars', 'mots_cles' => 'facture,mars', 'category_id' => $finance->id,
        ]);
        $secret = $this->createDocument($bob, [
            'titre' => 'Contrat de Bob', 'mots_cles' => 'contrat', 'category_id' => $juridique->id,
        ]);

        Document::whereKey($contrat->id)->update(['created_at' => '2026-01-10 10:00:00']);
        Document::whereKey($facture->id)->update(['created_at' => '2026-03-05 10:00:00']);

        $search = fn (string $qs) => $this->as($alice)->getJson('/api/documents/search?'.$qs);

        // Alice ne voit jamais le document de Bob.
        $search('')->assertOk()->assertJsonCount(2, 'data');
        $search('titre=Contrat')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $contrat->id);

        $search('mots_cles=facture,mars')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $facture->id);
        $search('mots_cles=facture,contrat')->assertJsonCount(0, 'data'); // ET logique

        $search('category_id='.$finance->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $facture->id);
        $search('q=fournisseur')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $contrat->id);

        $search('date_debut=2026-02-01')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $facture->id);
        $search('date_fin=2026-02-01')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $contrat->id);
        $search('date_debut=2026-01-01&date_fin=2026-12-31')->assertJsonCount(2, 'data');

        // Combinaison : catégorie ET date => aucun résultat
        $search('category_id='.$juridique->id.'&date_debut=2026-02-01')->assertJsonCount(0, 'data');

        // Filtre par auteur : Alice cherche les documents de Bob => rien (non accessibles)
        $search('user_id='.$bob->id)->assertJsonCount(0, 'data');

        // Date de fin antérieure à la date de début : erreur de validation
        $search('date_debut=2026-05-01&date_fin=2026-01-01')->assertUnprocessable();

        $this->assertNotNull($secret);
    }

    public function test_search_filters_by_status_and_scope_and_is_paginated(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        $mine = $this->createDocument($alice, ['titre' => 'Mien']);
        $archived = $this->createDocument($alice, ['titre' => 'Ancien']);
        $shared = $this->createDocument($bob, ['titre' => 'Partagé par Bob']);
        $this->share($shared, $alice, 'lecture');
        $this->as($alice)->patchJson("/api/documents/{$archived->id}/archive")->assertOk();

        $list = fn (string $qs) => $this->as($alice)->getJson('/api/documents?'.$qs);

        $list('statut=actif')->assertJsonCount(2, 'data');
        $list('statut=archive')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $archived->id);
        $list('portee=mes')->assertJsonCount(2, 'data');
        $list('portee=partages')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $shared->id);
        $list('user_id='.$bob->id)->assertJsonCount(1, 'data'); // auteur Bob, accessible car partagé

        $list('per_page=1')->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 3);

        $list('statut=inconnu')->assertUnprocessable();
        $this->assertNotNull($mine);
    }

    public function test_admin_search_covers_all_documents(): void
    {
        $this->createDocument($this->makeUser());
        $this->createDocument($this->makeUser());

        $this->as($this->makeUser('admin'))->getJson('/api/documents/search')->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------- Dashboard

    public function test_dashboard_returns_stats_and_recent_documents(): void
    {
        $alice = $this->makeUser();
        $this->createDocument($alice);
        $this->createDocument($alice);

        $this->as($alice)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.documents_actifs', 2)
            ->assertJsonPath('stats.mes_documents', 2)
            ->assertJsonPath('stats.versions', 2)
            ->assertJsonCount(2, 'recents')
            ->assertJsonMissingPath('stats.utilisateurs');
    }
}
