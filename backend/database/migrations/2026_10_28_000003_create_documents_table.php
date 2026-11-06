<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('mots_cles')->nullable();
            $table->enum('statut', ['actif', 'archive'])->default('actif');
            $table->timestamp('archived_at')->nullable();
            // restrictOnDelete : on ne peut pas supprimer une catégorie ni un
            // utilisateur qui possède encore des documents (filet de sécurité
            // en plus des contrôles faits dans les contrôleurs).
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('titre');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
