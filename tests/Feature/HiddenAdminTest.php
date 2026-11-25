<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The administrator account is hidden from the MSWDO officer: it does not
 * appear in User Management or Roles & Permissions, and its pages answer 404.
 * It still exists and can still sign in.
 */
class HiddenAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin', 'email' => 'admin@sanagustin.gov.ph',
            'first_name' => 'System', 'last_name' => 'Administrator',
        ]);
    }

    public function test_officer_does_not_see_the_admin_account_or_role(): void
    {
        $admin   = $this->admin();
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $this->actingAs($officer)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('admin@sanagustin.gov.ph');

        $this->actingAs($officer)
            ->get(route('admin.users.edit', $admin))
            ->assertNotFound();

        $adminRole = Role::where('key', 'admin')->first();
        if ($adminRole) {
            $this->actingAs($officer)
                ->get(route('admin.roles.edit', $adminRole))
                ->assertNotFound();
        }
    }

    public function test_admin_still_sees_everything_and_can_sign_in(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('admin@sanagustin.gov.ph');

        auth()->logout();

        $this->post(route('login'), ['email' => 'admin@sanagustin.gov.ph', 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }
}
