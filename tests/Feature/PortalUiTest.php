<?php

namespace Tests\Feature;

use App\Models\AssistanceRequest;
use App\Models\Budget;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the interactive pieces of the portal UI: the fiscal-year dropdowns
 * and the modal triggers that replaced the separate edit/approval screens.
 */
class PortalUiTest extends TestCase
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

    private function assistanceRequest(User $user, string $status = 'Pending'): AssistanceRequest
    {
        $client = Client::create([
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'date_of_birth'  => '1990-01-01',
            'sex'            => 'Male',
            'civil_status'   => 'Single',
            'address'        => 'Poblacion',
            'barangay'       => 'Poblacion 1',
            'contact_number' => '09171234567',
        ]);

        return AssistanceRequest::create([
            'client_id'        => $client->id,
            'assistance_type'  => 'Medical',
            'amount_requested' => 5000,
            'purpose'          => 'Medical expenses',
            'status'           => $status,
            'encoded_by'       => $user->id,
        ]);
    }

    public function test_fiscal_year_dropdown_stops_at_the_current_year(): void
    {
        $current = (int) now()->year;

        $this->actingAs($this->officer())
            ->get(route('mswdo.budget.index'))
            ->assertOk()
            ->assertSee('<option value="' . $current . '"', false)
            // A year that has not begun cannot hold a budget, so 2027 must not
            // appear while it is still 2026.
            ->assertDontSee('<option value="' . ($current + 1) . '"', false)
            // Past years stay selectable so previous allocations remain readable.
            ->assertSee('<option value="' . ($current - 1) . '"', false);
    }

    public function test_fiscal_year_dropdown_includes_years_that_hold_records(): void
    {
        $officer = $this->officer();
        $past    = (int) now()->year - 3;

        Budget::create([
            'assistance_type'  => 'Medical',
            'allocated_amount' => 1000,
            'fiscal_year'      => $past,
            'created_by'       => $officer->id,
        ]);

        $this->actingAs($officer)
            ->get(route('mswdo.budget.index'))
            ->assertOk()
            ->assertSee('<option value="' . $past . '"', false);
    }

    public function test_unknown_fiscal_year_falls_back_to_current_year(): void
    {
        $this->actingAs($this->officer())
            ->get(route('mswdo.budget.index', ['year' => 1998]))
            ->assertOk()
            ->assertSee((string) now()->year);
    }

    public function test_add_allocation_is_a_modal_trigger_not_a_sidebar_form(): void
    {
        $this->actingAs($this->officer())
            ->get(route('mswdo.budget.index'))
            ->assertOk()
            ->assertSee('data-budget-add', false)
            ->assertSee('tpl-budget-add', false)
            // The permanent side panel is gone; the form lives in the modal.
            ->assertDontSee('name="allocated_amount"', false);
    }

    public function test_staff_sees_no_add_allocation_control(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'mswdo_staff']))
            ->get(route('mswdo.budget.index'))
            ->assertOk()
            // The button markup, not the bare attribute: the delegated click
            // handler names the selector in its script on every render.
            ->assertDontSee('<button type="button" data-budget-add', false)
            ->assertDontSee('<template id="tpl-budget-add">', false);
    }

    public function test_detail_endpoint_returns_the_decision_and_its_reason(): void
    {
        $officer = $this->officer();
        $req     = $this->assistanceRequest($officer, 'Rejected');

        \App\Models\Approval::create([
            'request_id'  => $req->id,
            'recorded_by' => $officer->id,
            'decision'    => 'Rejected',
            'remarks'     => 'Incomplete supporting documents.',
            'decided_at'  => now(),
        ]);

        $this->actingAs($officer)
            ->getJson(route('mswdo.approvals.show', $req))
            ->assertOk()
            ->assertJson([
                'status'   => 'Rejected',
                'type'     => 'Medical',
                'approval' => [
                    'decision' => 'Rejected',
                    // The reason is the whole point of opening a closed request.
                    'remarks'  => 'Incomplete supporting documents.',
                ],
            ]);
    }

    public function test_detail_endpoint_explains_a_missing_reason(): void
    {
        $officer = $this->officer();
        $req     = $this->assistanceRequest($officer, 'Approved');

        \App\Models\Approval::create([
            'request_id'  => $req->id,
            'recorded_by' => $officer->id,
            'decision'    => 'Approved',
            'remarks'     => null,
            'decided_at'  => now(),
        ]);

        $this->actingAs($officer)
            ->getJson(route('mswdo.approvals.show', $req))
            ->assertOk()
            // A bare dash reads as a rendering fault; say what it means.
            ->assertJsonPath('approval.remarks', 'No reason recorded.');
    }

    public function test_detail_endpoint_reports_a_pending_request_as_undecided(): void
    {
        $officer = $this->officer();
        $req     = $this->assistanceRequest($officer);

        $this->actingAs($officer)
            ->getJson(route('mswdo.approvals.show', $req))
            ->assertOk()
            ->assertJsonPath('approval', null)
            ->assertJsonPath('release', null);
    }

    public function test_listings_open_the_details_modal_in_place(): void
    {
        $officer = $this->officer();
        $this->assistanceRequest($officer, 'Rejected');

        // Both listings must offer View even on a closed request, which is
        // where the "No action needed" label used to be the only thing shown.
        $this->actingAs($officer)
            ->get(route('mswdo.requests.index'))
            ->assertOk()
            ->assertSee('data-approval-view', false);

        $this->actingAs($officer)
            ->get(route('mswdo.approvals.index'))
            ->assertOk()
            ->assertSee('data-approval-view', false);
    }

    public function test_requests_listing_offers_one_view_control_not_two(): void
    {
        $officer = $this->officer();
        $this->assistanceRequest($officer);

        $this->actingAs($officer)
            ->get(route('mswdo.requests.index'))
            ->assertOk()
            ->assertSee('data-approval-view', false)
            // The separate "Open" link went to the same record the modal now
            // links through to via data-page-url.
            ->assertSee('data-page-url', false)
            ->assertDontSee('title="Open the full request page"', false);
    }

    public function test_creating_an_allocation_reports_what_happened(): void
    {
        $this->actingAs($this->officer())
            ->post(route('mswdo.budget.store'), [
                'assistance_type'  => 'Medical',
                'allocated_amount' => 500000,
                'fiscal_year'      => now()->year,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Created')
                && str_contains($message, '500,000.00'));
    }

    public function test_topping_up_an_allocation_says_so_and_shows_the_new_total(): void
    {
        $officer = $this->officer();
        $this->budget($officer);

        // The add form tops up rather than replacing, so the message has to
        // distinguish the two or the officer cannot tell which happened.
        $this->actingAs($officer)
            ->post(route('mswdo.budget.store'), [
                'assistance_type'  => 'Medical',
                'allocated_amount' => 100000,
                'fiscal_year'      => 2026,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Added')
                && str_contains($message, '600,000.00'));
    }

    public function test_deleting_an_allocation_names_the_record_removed(): void
    {
        $officer = $this->officer();
        $budget  = $this->budget($officer);

        $this->actingAs($officer)
            ->delete(route('mswdo.budget.destroy', $budget))
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Medical 2026')
                && str_contains($message, 'deleted'));
    }

    public function test_recording_a_decision_reports_the_outcome(): void
    {
        $officer = $this->officer();
        $req     = $this->assistanceRequest($officer);

        $this->actingAs($officer)
            ->from(route('mswdo.approvals.index'))
            ->post(route('mswdo.approvals.store'), [
                'request_id' => $req->id,
                'decision'   => 'Rejected',
                'remarks'    => 'Incomplete documents.',
            ])
            // Back to the listing the decision was made from, not off to a
            // separate page the modal made unnecessary.
            ->assertRedirect(route('mswdo.approvals.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'rejected')
                && str_contains($message, 'Juan'));
    }

    public function test_submitting_a_request_lands_on_approvals_with_a_success_message(): void
    {
        $officer = $this->officer();
        $client  = Client::create([
            'first_name'     => 'Maria',
            'last_name'      => 'Santos',
            'date_of_birth'  => '1985-05-05',
            'sex'            => 'Female',
            'civil_status'   => 'Married',
            'address'        => 'Poblacion',
            'barangay'       => 'Poblacion 1',
            'contact_number' => '09170000000',
        ]);

        $this->actingAs($officer)
            ->post(route('mswdo.requests.store'), [
                'client_id'        => $client->id,
                'assistance_type'  => 'Emergency',
                'amount_requested' => 10000,
                'purpose'          => 'Fire victim assistance.',
            ])
            // The next step after filing is a decision, so the officer is put
            // where that happens rather than on a read-only detail page.
            ->assertRedirect(route('mswdo.approvals.index'))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Maria')
                && str_contains($message, 'submitted'));
    }

    public function test_listings_page_at_twenty_five_per_page_and_number_continuously(): void
    {
        $officer = $this->officer();

        for ($i = 0; $i < 26; $i++) {
            $this->assistanceRequest($officer);
        }

        foreach (['mswdo.requests.index', 'mswdo.approvals.index'] as $route) {
            $response = $this->actingAs($officer)->get(route($route));
            $response->assertOk()->assertSee('Showing');
            $this->assertSame(25, $response->viewData('requests')->perPage(), "{$route} page size");
            $this->assertSame(26, $response->viewData('requests')->total(), "{$route} total");
        }

        // Numbering continues across pages instead of restarting at 1.
        $this->actingAs($officer)
            ->get(route('mswdo.approvals.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('>26</td>', false);
    }

    public function test_request_page_exposes_edit_and_approval_modal_triggers(): void
    {
        $officer = $this->officer();
        $req     = $this->assistanceRequest($officer);

        $this->actingAs($officer)
            ->get(route('mswdo.requests.show', $req))
            ->assertOk()
            ->assertSee('data-request-edit', false)
            ->assertSee('data-approval-update', false)
            ->assertSee('Back to Requests');
    }

    public function test_released_request_offers_no_approval_action(): void
    {
        $officer = $this->officer();
        $req     = $this->assistanceRequest($officer, 'Released');

        // Asserting on the button markup, not the bare attribute name: the
        // delegated click handler mentions both selectors in its script on
        // every render, so the attribute alone is always present.
        $this->actingAs($officer)
            ->get(route('mswdo.requests.show', $req))
            ->assertOk()
            ->assertDontSee('data-approval-update' . "\n", false)
            ->assertDontSee('data-request-edit' . "\n", false);
    }

    public function test_approvals_index_uses_modal_trigger_with_budget_options(): void
    {
        $officer = $this->officer();
        $req     = $this->assistanceRequest($officer, 'For Release');

        Budget::create([
            'assistance_type'  => 'Medical',
            'allocated_amount' => 50000,
            'fiscal_year'      => now()->year,
            'created_by'       => $officer->id,
        ]);

        $this->actingAs($officer)
            ->get(route('mswdo.approvals.index'))
            ->assertOk()
            ->assertSee('data-approval-row', false)
            ->assertSee('data-budgets', false)
            ->assertSee('remaining');
    }

    public function test_approvals_index_marks_final_requests_as_needing_no_action(): void
    {
        $officer = $this->officer();
        $this->assistanceRequest($officer, 'Released');

        $this->actingAs($officer)
            ->get(route('mswdo.approvals.index'))
            ->assertOk()
            ->assertSee('No action needed');
    }
}
