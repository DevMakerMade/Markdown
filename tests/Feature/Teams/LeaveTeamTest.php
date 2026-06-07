<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_leave_a_team()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->delete(route('teams.leave', $team));

        $response->assertRedirect(route('teams.index'));
        $this->assertFalse($member->fresh()->belongsToTeam($team));
    }

    public function test_a_sole_owner_cannot_leave_a_team()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.leave', $team));

        $response->assertForbidden();
        $this->assertTrue($owner->fresh()->belongsToTeam($team));
    }

    public function test_an_owner_can_leave_when_another_owner_remains()
    {
        $owner = User::factory()->create();
        $coOwner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($coOwner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('teams.leave', $team));

        $response->assertRedirect(route('teams.index'));
        $this->assertFalse($owner->fresh()->belongsToTeam($team));
    }

    public function test_a_user_cannot_leave_their_personal_team()
    {
        $user = User::factory()->create();
        $personalTeam = $user->personalTeam();

        $response = $this
            ->actingAs($user)
            ->delete(route('teams.leave', $personalTeam));

        $response->assertForbidden();
        $this->assertTrue($user->fresh()->belongsToTeam($personalTeam));
    }

    public function test_leaving_the_current_team_switches_to_a_fallback_team()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->update(['current_team_id' => $team->id]);

        $this
            ->actingAs($member)
            ->delete(route('teams.leave', $team));

        $this->assertSame($member->personalTeam()->id, $member->fresh()->current_team_id);
    }
}
