<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ArchivesHelpers;
use Tests\TestCase;

/** Code ARC-XXXX, circuit de validation, rôle archiviste et jeu de données de démonstration. */
class ArchiveFieldsTest extends TestCase
{
    use ArchivesHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_new_document_gets_a_unique_arc_code_and_waits_for_validation(): void
    {
        $owner = $this->makeUser();

        $first = $this->upload($owner, ['reference' => 'RH/2026/014'])->assertCreated();
        $second = $this->upload($owner)->assertCreated();

        $first->assertJsonPath('data.code', sprintf('ARC-%04d', $first->json('data.id')))
            ->assertJsonPath('data.reference', 'RH/2026/014')
            ->assertJsonPath('data.validation', 'en_attente')
            ->assertJsonPath('data.status', 'en_attente')
            ->assertJsonPath('data.status_label', 'En attente')
            ->assertJsonPath('data.file_type', 'pdf');

        $this->assertNotSame($first->json('data.code'), $second->json('data.code'));
        $this->assertMatchesRegularExpression('/^ARC-\d{4}$/', $second->json('data.code'));
    }

    public function test_the_code_cannot_be_forced_by_the_client(): void
    {
        $response = $this->upload($this->makeUser(), ['code' => 'ARC-9999', 'validation' => 'valide'])->assertCreated();

        $response->assertJsonPath('data.validation', 'en_attente');
        $this->assertNotSame('ARC-9999', $response->json('data.code'));
    }

    public function test_an_archived_document_displays_the_archive_status(): void
    {
        $owner = $this->makeUser();
        $doc = $this->createDocument($owner);

        $this->as($owner)->patchJson("/api/documents/{$doc->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archive')
            ->assertJsonPath('data.status_label', 'Archivé');
    }

    public function test_documents_can_be_searched_by_code_and_filtered_by_validation(): void
    {
        $owner = $this->makeUser();
        $a = $this->createDocument($owner, ['titre' => 'Alpha']);
        $this->createDocument($owner, ['titre' => 'Beta']);
        Document::whereKey($a->id)->update(['validation' => 'valide']);

        $this->as($owner)->getJson('/api/documents?q='.$a->fresh()->code)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.titre', 'Alpha');

        $this->as($owner)->getJson('/api/documents?validation=valide')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->as($owner)->getJson('/api/documents?validation=inconnu')->assertUnprocessable();
    }

    public function test_dashboard_exposes_validation_storage_and_category_stats(): void
    {
        $owner = $this->makeUser();
        $a = $this->createDocument($owner, ['fichier' => $this->pdf('a.pdf', 100)]);
        $b = $this->createDocument($owner, ['fichier' => $this->pdf('b.pdf', 50)]);
        $this->createDocument($owner);
        $this->makeCategory(); // catégorie vide : ne doit pas compter comme « active »

        Document::whereKey($a->id)->update(['validation' => 'valide']);
        Document::whereKey($b->id)->update(['validation' => 'valide', 'statut' => 'archive']);

        $stats = $this->as($owner)->getJson('/api/dashboard')->assertOk()->json('stats');

        $this->assertSame(2, $stats['documents_actifs']);   // a + le 3e (b est archivé)
        $this->assertSame(1, $stats['documents_valides']);  // seul a est actif ET validé
        $this->assertSame(1, $stats['documents_en_attente']);
        $this->assertSame(0, $stats['documents_rejetes']);
        $this->assertSame(1, $stats['documents_archives']);
        $this->assertSame(3, $stats['categories_actives']); // 3 uploads = 3 catégories créées par le helper
        $this->assertGreaterThan($stats['categories_actives'], $stats['categories']);
        $this->assertSame((int) \App\Models\DocumentVersion::sum('taille'), $stats['stockage_octets']);
        $this->assertGreaterThan(0, $stats['stockage_octets']);
    }

    public function test_admin_can_give_the_archiviste_role(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN);
        $user = $this->makeUser();

        $this->as($admin)->putJson("/api/users/{$user->id}", ['role' => 'archiviste'])
            ->assertOk()->assertJsonPath('data.role', 'archiviste');

        $this->assertTrue($user->fresh()->isArchiviste());
        $this->assertTrue($user->fresh()->isStaff());
    }

    public function test_demo_seeders_build_a_coherent_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Les 3 rôles sont présents.
        foreach ([User::ROLE_ADMIN, User::ROLE_ARCHIVISTE, User::ROLE_USER] as $role) {
            $this->assertTrue(User::where('role', $role)->exists(), "Aucun compte « {$role} »");
        }
        $this->assertSame(6, Category::count());
        $this->assertSame(0, Category::whereNull('code')->count());

        // Codes uniques au format ARC-XXXX, et les 4 statuts affichables sont représentés.
        $documents = Document::all();
        $this->assertSame(15, $documents->count());
        $this->assertSame($documents->count(), $documents->pluck('code')->unique()->count());
        $documents->each(fn (Document $d) => $this->assertMatchesRegularExpression('/^ARC-\d{4}$/', $d->code));
        $this->assertEqualsCanonicalizing(
            ['archive', 'en_attente', 'rejete', 'valide'],
            $documents->map->displayStatus()->unique()->values()->all()
        );

        // Chaque version pointe vers un vrai fichier de taille réaliste (> 50 Ko) sur le disque privé.
        $this->assertGreaterThan(0, DocumentVersion::count());
        DocumentVersion::all()->each(function (DocumentVersion $v) {
            Storage::disk('local')->assertExists($v->chemin_fichier);
            $this->assertGreaterThan(50 * 1024, $v->taille);
        });
    }

    public function test_seeding_twice_does_not_duplicate_documents(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // = php artisan db:seed lancé deux fois

        $this->assertSame(15, Document::count());
        $this->assertSame(7, User::count());
        $this->assertSame(6, Category::count());
    }
}
