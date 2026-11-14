<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Code court (RH, FIN…) et couleur d'affichage des catégories / départements. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('code', 10)->nullable()->unique()->after('nom');
            $table->string('couleur', 7)->default('#2563EB')->after('description'); // hex #RRGGBB
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'couleur']);
        });
    }
};
