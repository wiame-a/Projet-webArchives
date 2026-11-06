<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de démonstration. Exécution : php artisan migrate:fresh --seed
     *
     * Comptes (mot de passe : password) :
     *   admin@archives.test  -> administrateur
     *   user@archives.test   -> utilisateur
     *   sara@archives.test   -> utilisateur (pour tester le partage)
     */
    public function run(): void
    {
        $this->makeUser('Administrateur', 'admin@archives.test', User::ROLE_ADMIN);
        $this->makeUser('Utilisateur Demo', 'user@archives.test', User::ROLE_USER);
        $this->makeUser('Sara Alaoui', 'sara@archives.test', User::ROLE_USER);

        $categories = [
            'Administratif' => 'Courriers, attestations et formulaires administratifs.',
            'Juridique' => 'Contrats, conventions et textes réglementaires.',
            'Financier' => 'Factures, budgets et bilans.',
            'Technique' => 'Documentation technique, plans et rapports.',
            'Ressources humaines' => 'Dossiers du personnel et procédures internes.',
        ];

        foreach ($categories as $nom => $description) {
            Category::firstOrCreate(['nom' => $nom], ['description' => $description]);
        }
    }

    private function makeUser(string $name, string $email, string $role): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = 'password'; // hashé automatiquement (cast "hashed")
        $user->role = $role;          // non mass-assignable : affecté explicitement
        $user->save();
    }
}
