<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Champs « archive » ajoutés aux documents :
 *
 *  - code        : identifiant lisible et unique « ARC-0001 » (généré par le modèle)
 *  - reference   : référence interne libre (ex. « RH/2026/014 »)
 *  - validation  : circuit de validation (en_attente, valide, rejete)
 *
 * La colonne `statut` (actif / archive) est conservée : elle pilote le cycle de vie
 * (lecture seule, restauration). Le statut affiché est donc :
 *   « Archivé » si statut = archive, sinon le libellé de `validation`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Nullable le temps de la reprise des documents existants ; l'unicité
            // s'applique dès maintenant (MySQL accepte plusieurs NULL).
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('reference', 100)->nullable()->after('code');
            $table->enum('validation', ['en_attente', 'valide', 'rejete'])
                ->default('en_attente')->after('statut');

            $table->index('reference');
            $table->index('validation');
        });

        // Reprise des documents déjà présents : code séquentiel, considérés comme validés.
        DB::table('documents')->orderBy('id')->each(function ($doc) {
            DB::table('documents')->where('id', $doc->id)->update([
                'code' => sprintf('ARC-%04d', $doc->id),
                'validation' => 'valide',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropIndex(['reference']);
            $table->dropIndex(['validation']);
            $table->dropColumn(['code', 'reference', 'validation']);
        });
    }
};
