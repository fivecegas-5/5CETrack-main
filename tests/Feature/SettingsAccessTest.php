<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_open_system_settings(): void
    {
        $branch = Branch::create(['code' => 'settings-branch', 'name' => 'Settings Branch']);
        $staff = User::factory()->create([
            'role' => UserRole::Staff,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($staff)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('data-page="settings"', false);
    }

    public function test_guest_must_sign_in_to_open_system_settings(): void
    {
        $this->get(route('settings.index'))
            ->assertRedirect(route('login'));
    }
}