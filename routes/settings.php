<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Controllers\Teams\TeamMemberController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');

    // Restore targets an archived (soft-deleted) team, so it lives outside the
    // membership middleware and resolves the trashed team in the controller.
    Route::put('settings/teams/{teamSlug}/restore', [TeamController::class, 'restore'])->name('teams.restore');

    Route::middleware(EnsureTeamMembership::class)->group(function () {
        Route::get('settings/teams/{team}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::patch('settings/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'archive'])->name('teams.destroy');
        Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
        Route::post('settings/teams/{team}/transfer', [TeamController::class, 'transferOwnership'])->name('teams.transfer');

        Route::post('settings/teams/{team}/avatar', [TeamController::class, 'updateAvatar'])->name('teams.avatar.update');
        Route::delete('settings/teams/{team}/avatar', [TeamController::class, 'destroyAvatar'])->name('teams.avatar.destroy');

        Route::patch('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('settings/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
        Route::delete('settings/teams/{team}/leave', [TeamMemberController::class, 'leave'])->name('teams.leave');

        Route::post('settings/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
        Route::delete('settings/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
        Route::post('settings/teams/{team}/invitations/{invitation}/resend', [TeamInvitationController::class, 'resend'])->name('teams.invitations.resend');
    });
});
