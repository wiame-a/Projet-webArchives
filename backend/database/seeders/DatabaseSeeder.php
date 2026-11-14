<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de démonstration. Exécution :
     *   php artisan migrate:fresh --seed
     * (Docker : docker compose exec backend php artisan migrate:fresh --seed)
     *
     * L'ordre compte : les documents ont besoin des utilisateurs et des catégories.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            DocumentSeeder::class,
        ]);
    }
}
