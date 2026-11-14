<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'activité : qui a fait quoi, sur quel document, et quand.
 *
 * Les liens vers l'utilisateur et le document passent à NULL s'ils sont supprimés
 * (nullOnDelete) : la ligne du journal est conservée, et sa description contient
 * déjà le titre et le code du document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40)->index();   // ex. document.validated
            $table->string('description', 255);       // phrase lisible
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
