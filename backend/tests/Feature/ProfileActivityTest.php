<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ArchivesHelpers;
use Tests\TestCase;

/** Page « Mon profil » et journal d'activité. */
class ProfileActivityTest extends TestCase
{
    use ArchivesHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    // ---------- Profil ----------

    public function test_user_can_update_name_and_email(): void
    {
        $user = $this->makeUser();

        $this->as($user)->putJson('/api/profile', ['name' => 'Nouveau Nom', 'email' => 'nouveau@archives.test'])
            ->assertOk()->assertJsonPath('data.name', 'Nouveau Nom');

        $this->assertSame('nouveau@archives.test', $user->fresh()->email);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => ActivityLog::PROFILE_UPDATED]);
    }

    public function test_email_must_be_unique_but_may_stay_unchanged(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->as($user)->putJson('/api/profile', ['name' => 'Moi', 'email' => $other->email])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->as($user)->putJson('/api/profile', ['name' => 'Moi', 'email' => $user->email])->assertOk();
    }

    public function test_profile_cannot_change_role(): void
    {
        $user = $this->makeUser();

        $this->as($user)->putJson('/api/profile', ['name' => 'Moi', 'email' => $user->email, 'role' => 'admin'])->assertOk();

        $this->assertSame(User::ROLE_USER, $user->fresh()->role);
    }

    public function test_password_change_succeeds_and_revokes_other_tokens(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['password' => Hash::make('ancien-mdp-1')])->save();
        $user->createToken('autre-appareil');

        $this->as($user)->putJson('/api/profile/password', [
            'current_password' => 'ancien-mdp-1',
            'password' => 'nouveau-mdp-2',
            'password_confirmation' => 'nouveau-mdp-2',
        ])->assertOk();

        $this->assertTrue(Hash::check('nouveau-mdp-2', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => ActivityLog::PASSWORD_CHANGED]);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['password' => Hash::make('ancien-mdp-1')])->save();

        $this->as($user)->putJson('/api/profile/password', [
            'current_password' => 'mauvais',
            'password' => 'nouveau-mdp-2',
            'password_confirmation' => 'nouveau-mdp-2',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('ancien-mdp-1', $user->fresh()->password));
    }

    public function test_password_change_requires_confirmation_and_a_different_password(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['password' => Hash::make('ancien-mdp-1')])->save();

        $this->as($user)->putJson('/api/profile/password', [
            'current_password' => 'ancien-mdp-1',
            'password' => 'nouveau-mdp-2',
            'password_confirmation' => 'autre-chose',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->as($user)->putJson('/api/profile/password', [
            'current_password' => 'ancien-mdp-1',
            'password' => 'ancien-mdp-1',
            'password_confirmation' => 'ancien-mdp-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->putJson('/api/profile', ['name' => 'X', 'email' => 'x@x.test'])->assertUnauthorized();
    }

    // ---------- Journal ----------

    public function test_document_actions_write_activity_rows(): void
    {
        $owner = $this->makeUser();
        $archiviste = $this->makeUser(User::ROLE_ARCHIVISTE);
        $doc = $this->createDocument($owner);

        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/validate")->assertOk();
        $this->as($owner)->patchJson("/api/documents/{$doc->id}/archive")->assertOk();

        foreach ([ActivityLog::DOCUMENT_CREATED, ActivityLog::DOCUMENT_VALIDATED, ActivityLog::DOCUMENT_ARCHIVED] as $action) {
            $this->assertDatabaseHas('activity_logs', ['document_id' => $doc->id, 'action' => $action]);
        }
        $this->assertDatabaseHas('activity_logs', [
            'document_id' => $doc->id, 'action' => ActivityLog::DOCUMENT_VALIDATED, 'user_id' => $archiviste->id,
        ]);
    }

    public function test_deleting_a_document_keeps_the_log_entries_without_document(): void
    {
        $owner = $this->makeUser();
        $doc = $this->createDocument($owner);

        $this->as($owner)->deleteJson("/api/documents/{$doc->id}")->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', ['action' => ActivityLog::DOCUMENT_DELETED, 'document_id' => null]);
        $this->assertDatabaseHas('activity_logs', ['action' => ActivityLog::DOCUMENT_CREATED, 'document_id' => null]);
    }

    public function test_global_journal_is_reserved_to_staff(): void
    {
        $user = $this->makeUser();
        $this->createDocument($user);

        $this->as($user)->getJson('/api/activity')->assertForbidden();
        $this->as($this->makeUser(User::ROLE_ARCHIVISTE))->getJson('/api/activity')->assertOk()->assertJsonPath('meta.total', 1);
        $this->as($this->makeUser(User::ROLE_ADMIN))->getJson('/api/activity')->assertOk();
    }

    public function test_journal_filters_by_action_user_and_text(): void
    {
        $owner = $this->makeUser();
        $archiviste = $this->makeUser(User::ROLE_ARCHIVISTE);
        $doc = $this->createDocument($owner, ['titre' => 'Bilan unique']);
        $this->as($archiviste)->patchJson("/api/documents/{$doc->id}/validate")->assertOk();

        $this->as($archiviste)->getJson('/api/activity?action='.ActivityLog::DOCUMENT_VALIDATED)
            ->assertOk()->assertJsonCount(1, 'data');
        $this->as($archiviste)->getJson('/api/activity?user_id='.$owner->id)
            ->assertOk()->assertJsonCount(1, 'data');
        $this->as($archiviste)->getJson('/api/activity?q=Bilan+unique')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->as($archiviste)->getJson('/api/activity?q=introuvable-xyz')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_document_activity_visible_to_owner_and_staff_only(): void
    {
        $owner = $this->makeUser();
        $doc = $this->createDocument($owner);
        $stranger = $this->makeUser();
        $reader = $this->makeUser();
        $this->share($doc, $reader, 'lecture');

        $this->as($owner)->getJson("/api/documents/{$doc->id}/activity")->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->makeUser(User::ROLE_ARCHIVISTE))->getJson("/api/documents/{$doc->id}/activity")->assertOk();
        $this->as($reader)->getJson("/api/documents/{$doc->id}/activity")->assertForbidden();
        $this->as($stranger)->getJson("/api/documents/{$doc->id}/activity")->assertForbidden();
    }
}
