# Archivium : gestion sécurisée des archives numériques

Projet du module Programmation Web Dynamique (S5, Ingénierie Logicielle, Faculté des Sciences d'Agadir).

| Dossier | Contenu |
| --- | --- |
| `backend/` | Projet Laravel 13 complet : API REST, Sanctum, migrations, seeders, tests. Le fichier `.env` est fourni (UTF-8 sans BOM). |
| `frontend/` | Application React 18 (Vite) : Axios, React Router, Bootstrap 5. |

## Prérequis

PHP 8.3 ou plus (extensions `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `openssl`), Composer, MySQL (XAMPP convient), Node.js 20 ou plus.

## Lancer le projet

Créer la base une seule fois (phpMyAdmin ou client MySQL) : `archives_numeriques`, interclassement `utf8mb4_unicode_ci`.

**Terminal 1, backend**

```powershell
cd backend
composer install
php artisan migrate:fresh --seed
php artisan serve
```

`composer install` télécharge le dossier `vendor/` (le framework Laravel) : c'est la seule étape qui demande Internet côté backend, et elle n'est à faire qu'une fois.

**Terminal 2, frontend**

```powershell
cd frontend
npm install
npm run dev
```

Ouvrir http://localhost:5173. L'API répond sur http://localhost:8000/api.

## Comptes de démonstration (mot de passe : `password`)

| Email | Rôle |
| --- | --- |
| `admin@archives.test` | Administrateur |
| `user@archives.test` | Utilisateur |
| `sara@archives.test` | Utilisateur (pour tester le partage) |

## Réglages importants

- **Limite d'envoi PHP** : dans `php.ini`, `upload_max_filesize = 12M` et `post_max_size = 12M` (le sujet impose 10 Mo, PHP limite à 2 Mo par défaut).
- **Clé de l'application** : `backend/.env` contient déjà une `APP_KEY`. Pour en générer une autre : `php artisan key:generate`.
- **Modifier `.env`** : toujours en UTF-8 sans BOM (VS Code : barre d'état, « UTF-8 »). Ne pas utiliser `echo ... >> .env` sous PowerShell 5 (écrit en UTF-16).
- Après toute modification de `.env` : `php artisan config:clear`.

## Tests du backend

```powershell
cd backend
php artisan test
```

Les tests utilisent SQLite en mémoire (extension `pdo_sqlite` activée dans `php.ini`).

## Routes principales de l'API

| Méthode | Route | Accès |
| --- | --- | --- |
| POST | `/api/register`, `/api/login` | Public |
| POST | `/api/logout`, GET `/api/me`, `/api/dashboard` | Connecté |
| GET | `/api/categories` | Connecté |
| POST, PUT, DELETE | `/api/categories[/{id}]` | Admin |
| GET, POST | `/api/documents` (+ `/api/documents/search`) | Connecté |
| GET, PUT, DELETE | `/api/documents/{id}` | Selon les droits |
| PATCH | `/api/documents/{id}/archive`, `/restore` | Propriétaire ou admin |
| GET, POST | `/api/documents/{id}/versions` | Selon les droits |
| GET | `/api/documents/{id}/versions/{version}/download` | Selon les droits |
| GET, POST, DELETE | `/api/documents/{id}/permissions` | Propriétaire ou admin |
| GET, PUT, DELETE | `/api/users[/{id}]` | Admin |

## Dépannage

| Symptôme | Piste |
| --- | --- |
| « Malformed UTF-8 characters » | `backend/.env` ou un fichier PHP enregistré en ANSI ou UTF-16 : le réenregistrer en UTF-8 sans BOM |
| « Impossible de joindre le serveur » | `php artisan serve` est-il lancé ? |
| Erreur CORS | `FRONTEND_URL` dans `backend/.env` doit valoir `http://localhost:5173`, puis `php artisan config:clear` |
| Fichier refusé sous 10 Mo | `upload_max_filesize` et `post_max_size` dans `php.ini` |
| `could not find driver` en test | Activer `pdo_sqlite` dans `php.ini` |
