<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ArchivesHelpers;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use ArchivesHelpers, RefreshDatabase;

    public function test_register_creates_a_user_with_role_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Wiame Talib',
            'email' => 'wiame@example.com',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
            'role' => 'admin', // tentative d'élévation de privilège : doit être ignorée
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'user')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'wiame@example.com', 'role' => 'user']);
        // Le mot de passe est hashé, jamais stocké en clair.
        $this->assertNotSame('motdepasse1', User::where('email', 'wiame@example.com')->value('password'));
    }

    public function test_responses_carry_the_application_name(): void
    {
        config(['app.name' => 'Archivium']);
        $user = $this->makeUser();

        $login = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password']);

        $login->assertOk()
            ->assertHeader('X-Application', 'Archivium')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('Archivium', $login->json('message'));

        $register = $this->postJson('/api/register', [
            'name' => 'Wiame Talib',
            'email' => 'wiame@example.com',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
        ]);
        $this->assertStringContainsString('Bienvenue sur Archivium', $register->json('message'));
    }

    public function test_register_validates_input(): void
    {
        $this->makeUser();

        $this->postJson('/api/register', [
            'name' => '', 'email' => 'pas-un-email', 'password' => 'court',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);

        $existing = User::first();
        $this->postJson('/api/register', [
            'name' => 'Doublon', 'email' => $existing->email,
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_valid_credentials_returns_a_working_token(): void
    {
        $user = $this->makeUser(); // mot de passe de la factory : "password"

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonStructure(['user', 'token']);

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_with_wrong_password_is_rejected_with_401(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'faux'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Identifiants incorrects.');
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/documents')->assertUnauthorized();
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->makeUser();
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->json('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
