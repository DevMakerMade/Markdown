<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArchiveTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_a_team_preserves_members_and_invitations()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.destroy', $team), ['name' => $team->name]);

        $response->assertRedirect(route('teams.index'));

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('team_invitations', ['id' => $invitation->id]);
    }

    public function test_archived_team_is_excluded_from_the_team_switcher()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Archivable']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)->delete(route('teams.destroy', $team), ['name' => $team->name]);

        $this->assertFalse(
            $owner->fresh()->toUserTeams(includeCurrent: true)->contains(fn ($t) => $t->id === $team->id)
        );
    }

    public function test_owner_can_restore_an_archived_team()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $team->delete();

        $response = $this
            ->actingAs($owner)
            ->put(route('teams.restore', ['teamSlug' => $team->slug]));

        $response->assertRedirect(route('teams.edit', $team));
        $this->assertNull($team->fresh()->deleted_at);
        $this->assertTrue($owner->fresh()->belongsToTeam($team->fresh()));
    }

    public function test_non_owners_cannot_restore_a_team()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $team->delete();

        $response = $this
            ->actingAs($member)
            ->put(route('teams.restore', ['teamSlug' => $team->slug]));

        $response->assertForbidden();
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }

    public function test_archived_owned_teams_appear_on_the_index()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $team->delete();

        $this
            ->actingAs($owner)
            ->get(route('teams.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('archivedTeams', 1)
                ->where('archivedTeams.0.slug', $team->slug));
    }

    public function test_a_user_cannot_archive_their_last_team()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        // Reduce the user to a single team to exercise the last-team guard.
        $owner->teamMemberships()->where('team_id', '!=', $team->id)->delete();
        $owner->update(['current_team_id' => $team->id]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.destroy', $team), ['name' => $team->name]);

        $response->assertForbidden();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'deleted_at' => null]);
    }
}
