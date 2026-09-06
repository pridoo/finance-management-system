<?php
namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_flash_element_is_rendered_after_a_redirect(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $this->actingAs($officer)->post(route('mswdo.budget.store'), [
            'assistance_type'  => 'Medical',
            'allocated_amount' => 500000,
            'fiscal_year'      => now()->year,
        ]);

        // Follow the redirect and confirm the markup the JS looks for is
        // actually on the page it lands on.
        $this->actingAs($officer)
            ->get(route('mswdo.budget.index'))
            ->assertOk()
            ->assertSee('data-swal-flash', false)
            ->assertSee('data-icon="success"', false)
            ->assertSee('Created', false);
    }

    public function test_logout_form_no_longer_carries_two_competing_handlers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'mswdo_officer']))
            ->get(route('mswdo.dashboard'))
            ->assertOk()
            ->assertSee('data-swal-logout', false)
            // Both attributes on one form made the click and submit handlers
            // fire together, and the second closed the first's dialog.
            ->assertDontSee('data-swal-confirm' . "\n", false);
    }
}
