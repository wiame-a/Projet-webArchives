<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('numero_version');
            $table->string('chemin_fichier');
            $table->string('nom_original');
            $table->unsignedBigInteger('taille');
            $table->timestamps();

            // Un numéro de version n'existe qu'une fois par document.
            $table->unique(['document_id', 'numero_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
