<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute le rôle « archiviste » à la colonne users.role.
 *
 * On ne modifie pas la migration d'origine (déjà exécutée chez tout le monde) :
 * une nouvelle migration fait évoluer la colonne, les comptes existants sont conservés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'archiviste', 'user'])->default('user')->change();
        });
    }

    public function down(): void
    {
        // Les archivistes redeviennent de simples utilisateurs avant de retirer la valeur.
        DB::table('users')->where('role', 'archiviste')->update(['role' => 'user']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'user'])->default('user')->change();
        });
    }
};
