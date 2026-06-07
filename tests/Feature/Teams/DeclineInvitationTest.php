<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeclineInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invited_user_can_decline_an_invitation()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
        $this->assertFalse($invitedUser->fresh()->belongsToTeam($team));
    }

    public function test_a_user_cannot_decline_an_invitation_for_a_different_email()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($otherUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');
        $this->assertDatabaseHas('team_invitations', ['id' => $invitation->id]);
    }

    public function test_guests_cannot_decline_invitations()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this->delete(route('invitations.decline', $invitation));

        $response->assertRedirect(route('login'));
    }

    public function test_accepted_invitations_cannot_be_declined()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->accepted()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');
    }

    public function test_expired_invitations_can_be_declined()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
    }
}
