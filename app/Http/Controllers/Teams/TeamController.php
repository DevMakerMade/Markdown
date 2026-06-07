<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Http\Requests\Teams\TransferTeamOwnershipRequest;
use App\Http\Requests\Teams\UpdateTeamAvatarRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Display a listing of the user's teams.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('teams/Index', [
            'teams' => $user->toUserTeams(includeCurrent: true),
            'archivedTeams' => $user->archivedOwnedTeams()->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'avatarUrl' => $team->avatar_url,
            ]),
        ]);
    }

    /**
     * Store a newly created team.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        $team = $createTeam->handle($request->user(), $request->validated('name'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Show the team edit page.
     */
    public function edit(Request $request, Team $team): Response
    {
        $user = $request->user();

        return Inertia::render('teams/Edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'isPersonal' => $team->is_personal,
                'avatarUrl' => $team->avatar_url,
            ],
            'members' => $team->members()->get()->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar ?? null,
                'role' => $member->pivot->role->value,
                'role_label' => $member->pivot->role->label(),
            ]),
            'invitations' => $team->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn ($invitation) => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at->toISOString(),
                ]),
            'permissions' => $user->toTeamPermissions($team),
            'canLeave' => $user->canLeaveTeam($team),
            'availableRoles' => TeamRole::assignable(),
        ]);
    }

    /**
     * Update the specified team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team = DB::transaction(function () use ($request, $team) {
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $request->validated('name')]);

            return $team;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Switch the user's current team.
     */
    public function switch(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->belongsToTeam($team), 403);

        $request->user()->switchTeam($team);

        return back();
    }

    /**
     * Transfer ownership of the team to another member.
     */
    public function transferOwnership(TransferTeamOwnershipRequest $request, Team $team): RedirectResponse
    {
        DB::transaction(function () use ($request, $team) {
            $currentOwner = $team->owner();
            $newOwnerId = (int) $request->validated('user');

            $team->memberships()->whereIn('user_id', [$currentOwner->id, $newOwnerId])->lockForUpdate()->get();

            $team->memberships()->where('user_id', $newOwnerId)->update(['role' => TeamRole::Owner]);
            $team->memberships()->where('user_id', $currentOwner->id)->update(['role' => TeamRole::Admin]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team ownership transferred.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Archive (soft delete) the specified team, preserving members and invitations.
     */
    public function archive(DeleteTeamRequest $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        abort_if($user->teams()->count() <= 1, 403, __('You cannot archive your last team.'));

        $fallbackTeam = $user->isCurrentTeam($team)
            ? $user->fallbackTeam($team)
            : null;

        DB::transaction(function () use ($user, $team) {
            User::where('current_team_id', $team->id)
                ->where('id', '!=', $user->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTeam($affectedUser->personalTeam()));

            $team->delete();
        });

        if ($fallbackTeam) {
            $user->switchTeam($fallbackTeam);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team archived.')]);

        return to_route('teams.index');
    }

    /**
     * Restore an archived team.
     */
    public function restore(Request $request, string $teamSlug): RedirectResponse
    {
        $team = Team::onlyTrashed()->where('slug', $teamSlug)->firstOrFail();

        Gate::authorize('restore', $team);

        $team->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team restored.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Update the team's avatar image.
     */
    public function updateAvatar(UpdateTeamAvatarRequest $request, Team $team): RedirectResponse
    {
        $previousPath = $team->avatar_path;

        $path = $request->file('avatar')->store('team-avatars', 'public');

        $team->update(['avatar_path' => $path]);

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team avatar updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the team's avatar image.
     */
    public function destroyAvatar(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        if ($team->avatar_path) {
            Storage::disk('public')->delete($team->avatar_path);
            $team->update(['avatar_path' => null]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team avatar removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}
