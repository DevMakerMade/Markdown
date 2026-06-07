<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_transfer_ownership_to_a_member()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.transfer', $team), ['user' => $member->id]);

        $response->assertRedirect(route('teams.edit', $team));

        $this->assertSame(TeamRole::Owner, $member->fresh()->teamRole($team));
        $this->assertSame(TeamRole::Admin, $owner->fresh()->teamRole($team));
    }

    public function test_admins_cannot_transfer_ownership()
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($admin)
            ->post(route('teams.transfer', $team), ['user' => $admin->id]);

        $response->assertForbidden();
    }

    public function test_members_cannot_transfer_ownership()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->post(route('teams.transfer', $team), ['user' => $member->id]);

        $response->assertForbidden();
    }

    public function test_ownership_cannot_be_transferred_to_a_non_member()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.transfer', $team), ['user' => $stranger->id]);

        $response->assertSessionHasErrors('user');
        $this->assertSame(TeamRole::Owner, $owner->fresh()->teamRole($team));
    }

    public function test_ownership_cannot_be_transferred_to_self()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.transfer', $team), ['user' => $owner->id]);

        $response->assertSessionHasErrors('user');
    }

    public function test_personal_team_ownership_cannot_be_transferred()
    {
        $owner = User::factory()->create();
        $personalTeam = $owner->personalTeam();

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.transfer', $personalTeam), ['user' => $owner->id]);

        $response->assertForbidden();
    }
}
