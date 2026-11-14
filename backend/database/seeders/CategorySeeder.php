<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/** Départements de l'entreprise (code court + couleur d'affichage). */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // nom                    code   couleur      description
            ['Ressources humaines', 'RH',  '#7C3AED', 'Dossiers du personnel, contrats de travail et procédures internes.'],
            ['Finance',             'FIN', '#059669', 'Factures, budgets, bilans et pièces comptables.'],
            ['Juridique',           'JUR', '#DC2626', 'Contrats, conventions, litiges et textes réglementaires.'],
            ['Technique',           'TEC', '#2563EB', 'Documentation technique, plans, cahiers des charges et rapports.'],
            ['Direction',           'DIR', '#0F172A', 'Comptes rendus de conseil, stratégie et décisions de la direction.'],
            ['Administratif',       'ADM', '#D97706', 'Courriers, attestations et formulaires administratifs.'],
        ];

        foreach ($rows as [$nom, $code, $couleur, $description]) {
            Category::updateOrCreate(
                ['nom' => $nom],
                ['code' => $code, 'couleur' => $couleur, 'description' => $description]
            );
        }
    }
}
