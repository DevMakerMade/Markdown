<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_upload_a_team_avatar()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.avatar.update', $team), [
                'avatar' => UploadedFile::fake()->image('logo.png'),
            ]);

        $response->assertRedirect(route('teams.edit', $team));

        $path = $team->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($team->fresh()->avatar_url);
    }

    public function test_admins_can_upload_a_team_avatar()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $response = $this
            ->actingAs($admin)
            ->post(route('teams.avatar.update', $team), [
                'avatar' => UploadedFile::fake()->image('logo.png'),
            ]);

        $response->assertRedirect(route('teams.edit', $team));
    }

    public function test_members_cannot_upload_a_team_avatar()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->post(route('teams.avatar.update', $team), [
                'avatar' => UploadedFile::fake()->image('logo.png'),
            ]);

        $response->assertForbidden();
    }

    public function test_non_image_uploads_are_rejected()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.avatar.update', $team), [
                'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_oversized_images_are_rejected()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('teams.avatar.update', $team), [
                'avatar' => UploadedFile::fake()->image('huge.png')->size(3000),
            ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_replacing_an_avatar_deletes_the_previous_file()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)->post(route('teams.avatar.update', $team), [
            'avatar' => UploadedFile::fake()->image('first.png'),
        ]);
        $firstPath = $team->fresh()->avatar_path;

        $this->actingAs($owner)->post(route('teams.avatar.update', $team), [
            'avatar' => UploadedFile::fake()->image('second.png'),
        ]);

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($team->fresh()->avatar_path);
    }

    public function test_removing_an_avatar_deletes_the_file_and_clears_the_column()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)->post(route('teams.avatar.update', $team), [
            'avatar' => UploadedFile::fake()->image('logo.png'),
        ]);
        $path = $team->fresh()->avatar_path;

        $response = $this->actingAs($owner)->delete(route('teams.avatar.destroy', $team));

        $response->assertRedirect(route('teams.edit', $team));
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($team->fresh()->avatar_path);
    }

    public function test_archiving_a_team_keeps_its_avatar_file()
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this->actingAs($owner)->post(route('teams.avatar.update', $team), [
            'avatar' => UploadedFile::fake()->image('logo.png'),
        ]);
        $path = $team->fresh()->avatar_path;

        $this->actingAs($owner)->delete(route('teams.destroy', $team), ['name' => $team->name]);

        Storage::disk('public')->assertExists($path);
    }
}
