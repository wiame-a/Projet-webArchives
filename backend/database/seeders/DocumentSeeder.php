<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentPermission;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DocumentVersionService;
use Database\Seeders\Support\SampleFiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 15 documents d'entreprise pour la démonstration (jeu de données fixe, sans hasard).
 *
 * Répartition : 8 Validés, 3 En attente, 3 Archivés, 1 Rejeté — dans les 6 départements.
 * Chaque document reçoit de VRAIS fichiers (PDF / DOCX / XLSX) de taille réaliste,
 * stockés sur le disque privé : le téléchargement et l'aperçu fonctionnent.
 * Les codes ARC-XXXX sont générés par le modèle Document.
 *
 * Idempotent : un document déjà présent (même référence) n'est pas recréé,
 * donc `php artisan db:seed` peut être relancé sans doublons.
 */
class DocumentSeeder extends Seeder
{
    private const EN_ATTENTE = Document::VALIDATION_EN_ATTENTE;
    private const VALIDE = Document::VALIDATION_VALIDE;
    private const REJETE = Document::VALIDATION_REJETE;

    /** Motif affiché pour les documents rejetés (clé = référence). */
    private const MOTIFS_REJET = [
        'FIN/2026/071' => 'Le montant facturé (48 000 MAD) ne correspond pas au devis accepté (42 500 MAD). Merci de fournir une facture corrigée.',
    ];

    /**
     * Colonnes : catégorie(code), propriétaire, extension, taille d'une version (Ko),
     *            validation, archivé ?, âge en jours, nombre de versions,
     *            référence, titre, mots-clés, description, partages [email => droit]
     */
    private function dataset(): array
    {
        return [
            // ---------------------------------------------------------------- RH
            ['RH', 'youssef@archives.test', 'pdf', 245, self::VALIDE, false, 270, 1, 'RH/2026/001',
                'Contrat de travail CDI - Mehdi Tazi', 'contrat,cdi,embauche',
                'Contrat à durée indéterminée du nouveau développeur, signé par les deux parties.',
                ['archiviste@archives.test' => 'lecture']],
            ['RH', 'nadia@archives.test', 'docx', 180, self::VALIDE, false, 190, 3, 'RH/2026/014',
                'Règlement intérieur 2026', 'reglement,discipline,interne',
                'Règlement intérieur de l\'entreprise, mis à jour après consultation des représentants du personnel.',
                ['sara@archives.test' => 'lecture', 'user@archives.test' => 'lecture']],
            ['RH', 'youssef@archives.test', 'xlsx', 96, self::EN_ATTENTE, false, 12, 2, 'RH/2026/037',
                'Planning des congés - Été 2026', 'conges,planning,ete',
                'Répartition des congés de juillet à septembre, en attente de validation par la direction.',
                ['nadia@archives.test' => 'modification']],

            // ------------------------------------------------------------ Finance
            ['FIN', 'user@archives.test', 'xlsx', 420, self::VALIDE, false, 300, 4, 'FIN/2026/003',
                'Budget prévisionnel 2026', 'budget,previsionnel,2026',
                'Budget annuel par département, avec quatre révisions trimestrielles.',
                ['archiviste@archives.test' => 'modification', 'karim@archives.test' => 'lecture']],
            ['FIN', 'sara@archives.test', 'pdf', 310, self::VALIDE, false, 120, 1, 'FIN/2026/052',
                'Facture FA-2026-0412 - Atlas Informatique', 'facture,fournisseur,materiel',
                'Facture de 42 postes de travail, rapprochée avec le bon de commande.',
                ['user@archives.test' => 'lecture']],
            ['FIN', 'karim@archives.test', 'xlsx', 780, self::VALIDE, true, 520, 2, 'FIN/2025/011',
                'Bilan comptable 2024', 'bilan,comptabilite,2024',
                'Bilan et compte de produits et charges de l\'exercice 2024 (archivé après clôture).',
                []],
            ['FIN', 'sara@archives.test', 'pdf', 190, self::REJETE, false, 25, 2, 'FIN/2026/071',
                'Facture FA-2026-0517 - Prestations de conseil', 'facture,conseil',
                'Facture rejetée : montant différent du devis accepté.',
                ['archiviste@archives.test' => 'lecture']],

            // ----------------------------------------------------------- Juridique
            ['JUR', 'admin@archives.test', 'pdf', 640, self::VALIDE, false, 210, 2, 'JUR/2026/005',
                'Contrat de maintenance - Hébergeur cloud', 'contrat,maintenance,cloud',
                'Contrat de trois ans avec niveau de service garanti à 99,9 %.',
                ['user@archives.test' => 'lecture', 'sara@archives.test' => 'lecture']],
            ['JUR', 'karim@archives.test', 'docx', 88, self::EN_ATTENTE, false, 9, 2, 'JUR/2026/019',
                'Accord de confidentialité (NDA) - Partenaire logistique', 'nda,confidentialite,partenaire',
                'Accord de non-divulgation bilatéral, en relecture juridique avant signature.',
                ['nadia@archives.test' => 'lecture']],

            // ----------------------------------------------------------- Technique
            ['TEC', 'user@archives.test', 'docx', 360, self::VALIDE, false, 150, 5, 'TEC/2026/004',
                'Cahier des charges - Plateforme GED', 'cahier,specifications,ged',
                'Spécifications fonctionnelles et techniques de la plateforme de gestion documentaire.',
                ['archiviste@archives.test' => 'modification', 'youssef@archives.test' => 'lecture']],
            ['TEC', 'youssef@archives.test', 'pdf', 890, self::VALIDE, false, 95, 1, 'TEC/2026/017',
                'Architecture réseau - Siège', 'reseau,architecture,securite',
                'Schéma d\'architecture réseau du siège avec segmentation des VLAN.',
                []],
            ['TEC', 'sara@archives.test', 'xlsx', 210, self::EN_ATTENTE, false, 3, 1, 'TEC/2026/031',
                'Inventaire du parc informatique', 'inventaire,materiel,parc',
                'Inventaire détaillé des équipements, à contrôler avant validation.',
                ['user@archives.test' => 'modification']],
            ['TEC', 'user@archives.test', 'pdf', 1100, self::VALIDE, true, 430, 2, 'TEC/2025/009',
                'Rapport d\'audit sécurité 2025', 'audit,securite,rapport',
                'Rapport d\'audit annuel et plan de remédiation (archivé, remplacé par l\'audit 2026).',
                []],

            // ------------------------------------------------------------ Direction
            ['DIR', 'admin@archives.test', 'pdf', 275, self::VALIDE, false, 80, 1, 'DIR/2026/002',
                'Compte rendu du conseil d\'administration - T1 2026', 'conseil,compte-rendu,gouvernance',
                'Décisions du conseil du premier trimestre : budget, recrutements, investissements.',
                ['karim@archives.test' => 'lecture', 'sara@archives.test' => 'lecture']],

            // --------------------------------------------------------- Administratif
            ['ADM', 'sara@archives.test', 'pdf', 380, self::VALIDE, true, 380, 1, 'ADM/2025/017',
                'Procès-verbal de réception des travaux', 'pv,reception,travaux',
                'Procès-verbal signé, archivé après la levée des réserves.',
                []],
        ];
    }

    public function run(): void
    {
        // Autonome : `php artisan db:seed --class=DocumentSeeder` fonctionne même sur une base
        // sans comptes ni catégories (ces deux seeders sont idempotents).
        $this->call([UserSeeder::class, CategorySeeder::class]);

        // Base vide (migrate:fresh) : les identifiants repartent de 1, on supprime donc les anciens
        // fichiers pour ne laisser aucun orphelin. On ne touche à rien si des documents existent.
        if (Document::count() === 0) {
            Storage::disk(DocumentVersionService::DISK)->deleteDirectory('documents');
        }

        $categories = Category::pluck('id', 'code');
        $users = User::pluck('id', 'email');
        $names = User::pluck('name', 'email');
        $archiviste = $users['archiviste@archives.test'];

        foreach ($this->dataset() as $row) {
            [$cat, $ownerEmail, $ext, $ko, $validation, $archived, $ageDays, $versions,
                $reference, $titre, $motsCles, $description, $shares] = $row;

            if (Document::where('reference', $reference)->exists()) {
                continue; // déjà seedé
            }

            $createdAt = now()->subDays($ageDays)->setTime(9, 30);
            $ext = SampleFiles::resolveExtension($ext);

            $doc = new Document([
                'titre' => $titre,
                'reference' => $reference,
                'description' => $description,
                'mots_cles' => $motsCles,
                'category_id' => $categories[$cat],
            ]);
            $doc->user_id = $users[$ownerEmail];
            $doc->statut = $archived ? Document::STATUT_ARCHIVE : Document::STATUT_ACTIF;
            $doc->validation = $validation;
            // Trace de la décision : validé ou rejeté par l'archiviste, quelques jours après le dépôt.
            if ($validation !== self::EN_ATTENTE) {
                $doc->validated_by = $archiviste;
                $doc->validated_at = $createdAt->copy()->addDays(min(3, max(1, intdiv($ageDays, 3))));
                $doc->motif_rejet = $validation === self::REJETE ? (self::MOTIFS_REJET[$reference] ?? 'Document non conforme.') : null;
            }
            $doc->archived_at = $archived ? $createdAt->copy()->addDays(max(1, intdiv($ageDays, 2))) : null;
            $doc->created_at = $createdAt;
            $doc->save(); // l'événement « created » du modèle pose le code ARC-XXXX

            // --- Journal d'activité : l'historique de ce document, daté comme les données ---
            $label = ActivityLogger::label($doc);
            $this->log($doc->user_id, ActivityLog::DOCUMENT_CREATED, 'a créé le document '.$label.'.', $doc, $createdAt);

            $lastDate = $createdAt;
            for ($n = 1; $n <= $versions; $n++) {
                // Les versions s'étalent entre la création et aujourd'hui ; chaque révision grossit un peu.
                $lastDate = $n === 1
                    ? $createdAt
                    : $createdAt->copy()->addDays((int) round($ageDays * ($n - 1) / $versions));
                $kb = (int) round($ko * (1 + 0.05 * ($n - 1)));

                $this->addVersion($doc, $n, $ext, $titre, $kb * 1024, $n === 1 ? $doc->user_id : $archiviste, $lastDate);

                if ($n > 1) {
                    $this->log($archiviste, ActivityLog::VERSION_ADDED, 'a ajouté la version '.$n.' au document '.$label.'.', $doc, $lastDate);
                }
            }

            if ($validation !== self::EN_ATTENTE) {
                $approved = $validation === self::VALIDE;
                $this->log(
                    $archiviste,
                    $approved ? ActivityLog::DOCUMENT_VALIDATED : ActivityLog::DOCUMENT_REJECTED,
                    $approved ? 'a validé le document '.$label.'.' : 'a rejeté le document '.$label.'. Motif : '.$doc->motif_rejet,
                    $doc,
                    $doc->validated_at
                );
            }

            if ($archived) {
                $this->log($doc->user_id, ActivityLog::DOCUMENT_ARCHIVED, 'a archivé le document '.$label.'.', $doc, $doc->archived_at);
            }

            foreach ($shares as $email => $droit) {
                DocumentPermission::create([
                    'document_id' => $doc->id,
                    'user_id' => $users[$email],
                    'droit' => $droit,
                ]);
                $this->log(
                    $doc->user_id,
                    ActivityLog::SHARE_GRANTED,
                    'a partagé le document '.$label.' avec '.$names[$email].' ('.($droit === 'modification' ? 'modification' : 'lecture seule').').',
                    $doc,
                    $createdAt->copy()->addDay()
                );
            }

            // Un téléchargement par le premier destinataire du partage, quelques jours après.
            if ($shares !== []) {
                $reader = array_key_first($shares);
                $this->log($users[$reader], ActivityLog::VERSION_DOWNLOADED, 'a téléchargé la version 1 du document '.$label.'.', $doc, $createdAt->copy()->addDays(2));
            }

            // saveQuietly() du modèle a rafraîchi updated_at : on le recale sur la dernière version.
            DB::table('documents')->where('id', $doc->id)->update(['updated_at' => $lastDate]);
        }
    }

    /** Écrit une ligne du journal à une date passée (le journal réel utilise l'heure courante). */
    private function log(int $userId, string $action, string $description, Document $doc, $date): void
    {
        ActivityLog::create([
            'user_id' => $userId,
            'document_id' => $doc->id,
            'action' => $action,
            'description' => mb_substr($description, 0, 255),
            'created_at' => $date,
        ]);
    }

    private function addVersion(Document $doc, int $n, string $ext, string $titre, int $bytes, int $authorId, $date): void
    {
        $path = 'documents/'.$doc->id.'/'.Str::uuid().'.'.$ext;
        $content = SampleFiles::make($ext, $titre, $doc->code.' - version '.$n, $bytes);

        Storage::disk(DocumentVersionService::DISK)->put($path, $content);

        $version = new DocumentVersion([
            'document_id' => $doc->id,
            'user_id' => $authorId,
            'numero_version' => $n,
            'chemin_fichier' => $path,
            'nom_original' => Str::slug($titre).'_v'.$n.'.'.$ext,
            'taille' => strlen($content),
        ]);
        $version->created_at = $date;
        $version->updated_at = $date;
        $version->save();
    }
}
