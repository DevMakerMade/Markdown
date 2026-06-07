<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResendInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_resend_a_pending_invitation()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expiresIn(1)->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);
        $originalExpiry = $invitation->expires_at;

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.resend', [$team, $invitation]));

        $response->assertRedirect(route('teams.edit', $team));
        $this->assertTrue($invitation->fresh()->expires_at->greaterThan($originalExpiry));
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_admins_can_resend_invitations()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('teams.invitations.resend', [$team, $invitation]));

        $response->assertRedirect(route('teams.edit', $team));
    }

    public function test_members_cannot_resend_invitations()
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
            ->actingAs($member)
            ->post(route('teams.invitations.resend', [$team, $invitation]));

        $response->assertForbidden();
    }

    public function test_expired_invitations_can_be_resent()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $this
            ->actingAs($owner)
            ->post(route('teams.invitations.resend', [$team, $invitation]));

        $this->assertTrue($invitation->fresh()->isPending());
    }

    public function test_accepted_invitations_cannot_be_resent()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->accepted()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.resend', [$team, $invitation]));

        $response->assertStatus(422);
    }

    public function test_invitation_from_another_team_returns_not_found()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $otherTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $otherTeam->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.invitations.resend', [$team, $invitation]));

        $response->assertNotFound();
    }
}
