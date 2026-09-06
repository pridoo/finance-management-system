<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetPageTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        return User::factory()->create(['role' => 'mswdo_officer']);
    }

    private function budget(User $officer): Budget
    {
        return Budget::create([
            'assistance_type'  => 'Medical',
            'allocated_amount' => 500000,
            'fiscal_year'      => 2026,
            'created_by'       => $officer->id,
        ]);
    }

    public function test_releases_endpoint_returns_breakdown(): void
    {
        $officer = $this->officer();
        $budget  = $this->budget($officer);

        $this->actingAs($officer)
            ->getJson(route('mswdo.budget.show', $budget))
            ->assertOk()
            ->assertJson([
                'assistance_type' => 'Medical',
                'allocated'       => '500,000.00',
                'released'        => '0.00',
                'balance'         => '500,000.00',
                'releases'        => [],
            ]);
    }

    public function test_officer_can_update_allocation(): void
    {
        $officer = $this->officer();
        $budget  = $this->budget($officer);

        $this->actingAs($officer)
            ->put(route('mswdo.budget.update', $budget), ['allocated_amount' => 250000])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(250000, (float) $budget->fresh()->allocated_amount);
    }

    public function test_non_officer_cannot_update_allocation(): void
    {
        $officer = $this->officer();
        $budget  = $this->budget($officer);

        $this->actingAs(User::factory()->create(['role' => 'mswdo_staff']))
            ->put(route('mswdo.budget.update', $budget), ['allocated_amount' => 1])
            ->assertForbidden();
    }

    public function test_budget_page_renders_action_buttons(): void
    {
        $officer = $this->officer();
        $this->budget($officer);

        $this->actingAs($officer)
            ->get(route('mswdo.budget.index', ['year' => 2026]))
            ->assertOk()
            ->assertSee('data-budget-view', false)
            ->assertSee('data-budget-edit', false);
    }
}
