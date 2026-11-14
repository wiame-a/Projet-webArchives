<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration — mot de passe commun : « password ».
 *
 *   admin@archives.test        Administrateur   (rôle admin)
 *   archiviste@archives.test   Archiviste       (rôle archiviste)
 *   karim@archives.test        Archiviste       (rôle archiviste)
 *   user@archives.test         Utilisateur      (rôle user)
 *   sara@archives.test         Utilisateur      (rôle user)
 *   youssef@archives.test      Utilisateur      (rôle user)
 *   nadia@archives.test        Utilisateur      (rôle user)
 */
class UserSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['Administrateur Archivium', 'admin@archives.test', User::ROLE_ADMIN],
        ['Imane El Fassi', 'archiviste@archives.test', User::ROLE_ARCHIVISTE],
        ['Karim Benali', 'karim@archives.test', User::ROLE_ARCHIVISTE],
        ['Utilisateur Demo', 'user@archives.test', User::ROLE_USER],
        ['Sara Alaoui', 'sara@archives.test', User::ROLE_USER],
        ['Youssef Amrani', 'youssef@archives.test', User::ROLE_USER],
        ['Nadia Chraibi', 'nadia@archives.test', User::ROLE_USER],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = 'password'; // hashé automatiquement (cast « hashed »)
            $user->role = $role;          // non mass-assignable : affecté explicitement
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
