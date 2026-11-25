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

    public function test_allocations_are_drawn_from_the_annual_fund(): void
    {
        $officer = $this->officer();
        $year = now()->year;

        $this->actingAs($officer)
            ->post(route('mswdo.budget.fund.store'), ['fiscal_year' => $year, 'amount' => 3000000])
            ->assertSessionHasNoErrors();

        $this->actingAs($officer)
            ->post(route('mswdo.budget.store'), ['assistance_type' => 'Medical', 'allocated_amount' => 2000000, 'fiscal_year' => $year])
            ->assertSessionHasNoErrors();

        // Only ₱1M is left, so ₱1.5M more is refused rather than growing the year.
        $this->actingAs($officer)
            ->post(route('mswdo.budget.store'), ['assistance_type' => 'Burial', 'allocated_amount' => 1500000, 'fiscal_year' => $year])
            ->assertSessionHasErrors('allocated_amount');

        $this->actingAs($officer)
            ->post(route('mswdo.budget.store'), ['assistance_type' => 'Burial', 'allocated_amount' => 1000000, 'fiscal_year' => $year])
            ->assertSessionHasNoErrors();

        $this->assertEquals(3000000, (float) Budget::where('fiscal_year', $year)->sum('allocated_amount'));
    }

    public function test_an_allocation_needs_a_fund_and_the_fund_cannot_drop_below_it(): void
    {
        $officer = $this->officer();
        $year = now()->year;

        $this->actingAs($officer)
            ->post(route('mswdo.budget.store'), ['assistance_type' => 'Medical', 'allocated_amount' => 1000, 'fiscal_year' => $year])
            ->assertSessionHasErrors('allocated_amount');

        $this->actingAs($officer)->post(route('mswdo.budget.fund.store'), ['fiscal_year' => $year, 'amount' => 5000]);
        $this->actingAs($officer)->post(route('mswdo.budget.store'), ['assistance_type' => 'Medical', 'allocated_amount' => 4000, 'fiscal_year' => $year]);

        $this->actingAs($officer)
            ->post(route('mswdo.budget.fund.store'), ['fiscal_year' => $year, 'amount' => 3000])
            ->assertSessionHasErrors('amount');
    }

    public function test_type_filter_shows_only_that_type(): void
    {
        $officer = $this->officer();
        $this->budget($officer);
        Budget::create(['assistance_type' => 'Burial', 'allocated_amount' => 1000, 'fiscal_year' => 2026, 'created_by' => $officer->id]);

        $this->actingAs($officer)
            ->get(route('mswdo.budget.index', ['year' => 2026, 'type' => 'Medical']))
            ->assertOk()
            ->assertSee('Medical releases')
            ->assertSee('data-budget-view="' . route('mswdo.budget.show', Budget::where('assistance_type', 'Medical')->value('id')) . '"', false)
            ->assertDontSee('data-budget-view="' . route('mswdo.budget.show', Budget::where('assistance_type', 'Burial')->value('id')) . '"', false)
            // Split / sub-allocations are switched off for now.
            ->assertDontSee('data-budget-split="', false);
    }

    public function test_fiscal_year_is_typed_and_starts_at_2026(): void
    {
        $officer = $this->officer();

        // Years before the first fiscal year fall back to it.
        $this->actingAs($officer)
            ->get(route('mswdo.budget.index', ['year' => 2019]))
            ->assertOk()
            ->assertSee('type="number" id="budget-year" name="year" value="2026"', false)
            ->assertSee('min="2026"', false);

        // A future year can be opened and funded.
        $this->actingAs($officer)
            ->post(route('mswdo.budget.fund.store'), ['fiscal_year' => 2027, 'amount' => 1000])
            ->assertSessionHasNoErrors();

        $this->actingAs($officer)
            ->post(route('mswdo.budget.fund.store'), ['fiscal_year' => 2025, 'amount' => 1000])
            ->assertSessionHasErrors('fiscal_year');
    }

    public function test_budget_report_totals_each_assistance_type(): void
    {
        $officer = $this->officer();
        $this->budget($officer);
        Budget::create(['assistance_type' => 'Burial', 'allocated_amount' => 1000, 'fiscal_year' => 2026, 'created_by' => $officer->id]);

        $this->actingAs($officer)
            ->get(route('mswdo.reports.index', ['type' => 'budget']))
            ->assertOk()
            ->assertSee('Totals by Funding Source')
            ->assertSeeInOrder(['Medical', 'Burial', 'Educational', 'Livelihood', 'Grand Total'])
            ->assertSee('501,000.00');
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
