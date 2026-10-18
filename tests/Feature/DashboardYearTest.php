<?php

namespace Tests\Feature;

use App\Models\AssistanceRequest;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's fiscal year picker, and the utilisation bars that were
 * reporting a flat 0% for allocations that had genuinely been drawn against.
 */
class DashboardYearTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        return User::factory()->create(['role' => 'mswdo_officer']);
    }

    private function budget(User $officer, int $year, float $amount = 9000000): Budget
    {
        return Budget::create([
            'assistance_type'  => 'Educational',
            'allocated_amount' => $amount,
            'fiscal_year'      => $year,
            'created_by'       => $officer->id,
        ]);
    }

    private function releasedRequest(User $officer, Budget $budget, float $amount, string $releasedAt): void
    {
        $client = Client::create([
            'first_name'    => 'Juan',
            'last_name'     => 'Dela Cruz',
            'date_of_birth' => '1990-01-01',
            'sex'           => 'Male',
            'civil_status'  => 'Single',
            'address'       => 'Poblacion',
            'barangay'      => 'Poblacion 1',
        ]);

        $req = AssistanceRequest::create([
            'client_id'        => $client->id,
            'assistance_type'  => 'Educational',
            'amount_requested' => $amount,
            'purpose'          => 'Tuition.',
            'status'           => 'Released',
            'encoded_by'       => $officer->id,
        ]);

        Disbursement::create([
            'request_id'      => $req->id,
            'budget_id'       => $budget->id,
            'amount_released' => $amount,
            'released_by'     => $officer->id,
            'released_at'     => $releasedAt,
        ]);
    }

    public function test_a_tiny_draw_still_reports_a_visible_bar_and_a_non_zero_percent(): void
    {
        $officer = $this->officer();
        $budget  = $this->budget($officer, (int) now()->year);

        // ₱2,000 of ₱9,000,000 is 0.022%, which round() flattens to 0 — the
        // bar then rendered empty, as though nothing had been released.
        $this->releasedRequest($officer, $budget, 2000, now()->toDateTimeString());

        $row = $this->actingAs($officer)
            ->get(route('mswdo.dashboard'))
            ->assertOk()
            ->viewData('budgetUtilization')
            ->firstWhere('type', 'Educational');

        $this->assertSame(0.02, $row['percent'], 'A real release must not report a flat 0%.');
        $this->assertGreaterThan(0, $row['bar'], 'The bar must be visible when money has moved.');
        $this->assertSame(2000.0, $row['released']);
    }

    public function test_a_fully_unused_allocation_still_reports_zero(): void
    {
        $officer = $this->officer();
        $this->budget($officer, (int) now()->year);

        $row = $this->actingAs($officer)
            ->get(route('mswdo.dashboard'))
            ->assertOk()
            ->viewData('budgetUtilization')
            ->firstWhere('type', 'Educational');

        // Nothing released really is 0%, and the bar stays empty.
        $this->assertEqualsWithDelta(0, $row['percent'], 0.001);
        $this->assertEqualsWithDelta(0, $row['bar'], 0.001);
    }

    public function test_the_year_picker_switches_the_figures(): void
    {
        $officer = $this->officer();
        // The system starts at 2026, so the "other" year is the next one.
        $past    = (int) now()->year + 1;

        $current = $this->budget($officer, (int) now()->year, 1000);
        $old     = $this->budget($officer, $past, 500000);

        $this->releasedRequest($officer, $old, 250000, $past . '-06-15 10:00:00');

        $response = $this->actingAs($officer)
            ->get(route('mswdo.dashboard', ['year' => $past]))
            ->assertOk();

        $this->assertSame($past, $response->viewData('year'));
        $this->assertFalse($response->viewData('isCurrentYear'));

        $row = $response->viewData('budgetUtilization')->firstWhere('type', 'Educational');
        $this->assertSame(500000.0, $row['allocated'], 'Should show the past year allocation, not this year.');
        $this->assertEqualsWithDelta(50, $row['percent'], 0.001);

        // On a past year the month is arbitrary, so the whole year is totalled.
        $this->assertSame(250000.0, (float) $response->viewData('releasedTotal'));
    }

    public function test_the_year_is_typed_and_starts_at_the_first_fiscal_year(): void
    {
        $this->actingAs($this->officer())
            ->get(route('mswdo.dashboard', ['year' => 2025]))
            ->assertOk()
            ->assertViewHas('year', 2026)
            ->assertSee('type="number" id="dash-year"', false)
            ->assertSee('min="2026"', false);
    }

    public function test_breakdown_by_funding_source_and_daily_processed_counts(): void
    {
        $officer = $this->officer();
        $budget  = $this->budget($officer, (int) now()->year, 100000);
        $this->releasedRequest($officer, $budget, 4000, now()->toDateTimeString());

        $response = $this->actingAs($officer)
            ->get(route('mswdo.dashboard'))
            ->assertOk()
            ->assertSee('Breakdown by Funding Source')
            ->assertSee('Daily Processed Requests');

        $row = $response->viewData('fundBreakdown')->firstWhere('type', 'Educational');
        $this->assertSame(1, $row['recipients']);
        $this->assertEquals(4000, $row['released']);

        $today = $response->viewData('dailyProcessed')->last();
        $this->assertSame(1, $today['released']);
        $this->assertSame(1, $today['filed']);
    }

    public function test_an_unknown_year_falls_back_to_the_current_one(): void
    {
        $this->actingAs($this->officer())
            ->get(route('mswdo.dashboard', ['year' => 1998]))
            ->assertOk()
            ->assertViewHas('year', (int) now()->year);
    }
}
