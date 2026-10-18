<?php

namespace Tests\Feature;

use App\Models\AssistanceRequest;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MswdoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_the_dashboard(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('mswdo.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Overview');
    }

    public function test_all_report_types_render_and_can_be_exported(): void
    {
        $officer = $this->user();

        foreach (['recipient', 'budget', 'audit'] as $type) {
            $this->actingAs($officer)
                ->get(route('mswdo.reports.index', ['type' => $type]))
                ->assertOk();

            $this->actingAs($officer)
                ->get(route('mswdo.reports.export', ['type' => $type]))
                ->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');

            $pdf = $this->actingAs($officer)
                ->get(route('mswdo.reports.export-pdf', ['type' => $type]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertDownload();

            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }
    }

    public function test_staff_cannot_create_a_budget(): void
    {
        $staff = $this->user('mswdo_staff');

        $this->actingAs($staff)->post(route('mswdo.budget.store'), [
            'assistance_type' => 'Medical',
            'allocated_amount' => 10000,
            'fiscal_year' => now()->year,
        ])->assertForbidden();
    }

    public function test_officer_can_complete_a_safe_release_workflow(): void
    {
        $officer = $this->user();
        $request = $this->assistanceRequest($officer);
        $budget = Budget::create([
            'assistance_type' => 'Medical',
            'allocated_amount' => 10000,
            'fiscal_year' => now()->year,
            'created_by' => $officer->id,
        ]);

        // Approving without naming a funding source is refused.
        $this->actingAs($officer)->post(route('mswdo.approvals.store'), [
            'request_id' => $request->id,
            'decision' => 'Approved',
        ])->assertSessionHasErrors('budget_id');

        $this->actingAs($officer)->post(route('mswdo.approvals.store'), [
            'request_id' => $request->id,
            'decision' => 'Approved',
            'budget_id' => $budget->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('approvals', ['request_id' => $request->id, 'budget_id' => $budget->id]);

        $this->actingAs($officer)->put(route('mswdo.approvals.update', $request), [
            'status' => 'For Release',
        ])->assertRedirect();

        // The funding source chosen at approval is used when none is sent.
        $this->actingAs($officer)->put(route('mswdo.approvals.update', $request), [
            'status' => 'Released',
            'amount_released' => 5000,
            'remarks' => 'Released at cashier',
        ])->assertRedirect();

        $this->assertDatabaseHas('assistance_requests', ['id' => $request->id, 'status' => 'Released']);
        $this->assertDatabaseHas('disbursements', ['request_id' => $request->id, 'amount_released' => 5000, 'budget_id' => $budget->id]);
    }

    public function test_staff_can_release_but_cannot_approve(): void
    {
        $officer = $this->user();
        $staff   = User::factory()->create(['role' => 'mswdo_staff']);
        $request = $this->assistanceRequest($officer);
        $budget  = Budget::create([
            'assistance_type' => 'Medical', 'allocated_amount' => 10000,
            'fiscal_year' => now()->year, 'created_by' => $officer->id,
        ]);

        // Staff can open Approvals, but the decision is not theirs.
        $this->actingAs($staff)->get(route('mswdo.approvals.index'))
            ->assertOk()
            ->assertSee("Awaiting Officer's decision", false);

        $this->actingAs($staff)->post(route('mswdo.approvals.store'), [
            'request_id' => $request->id, 'decision' => 'Approved', 'budget_id' => $budget->id,
        ])->assertForbidden();

        $this->actingAs($officer)->post(route('mswdo.approvals.store'), [
            'request_id' => $request->id, 'decision' => 'Approved', 'budget_id' => $budget->id,
        ])->assertRedirect();

        // Once approved, the staff carry out the release.
        $this->actingAs($staff)->put(route('mswdo.approvals.update', $request), ['status' => 'For Release'])
            ->assertRedirect();
        $this->actingAs($staff)->put(route('mswdo.approvals.update', $request), [
            'status' => 'Released', 'amount_released' => 3000,
        ])->assertRedirect();

        $this->assertDatabaseHas('disbursements', ['request_id' => $request->id, 'released_by' => $staff->id]);
    }

    public function test_release_is_rejected_when_budget_is_insufficient(): void
    {
        $officer = $this->user();
        $request = $this->assistanceRequest($officer, 'For Release');
        $budget = Budget::create([
            'assistance_type' => 'Medical',
            'allocated_amount' => 1000,
            'fiscal_year' => now()->year,
            'created_by' => $officer->id,
        ]);

        $this->actingAs($officer)->put(route('mswdo.approvals.update', $request), [
            'status' => 'Released',
            'budget_id' => $budget->id,
            'amount_released' => 5000,
        ])->assertSessionHasErrors('amount_released');

        $this->assertSame(0, Disbursement::count());
        $this->assertSame('For Release', $request->fresh()->status);
    }

    public function test_duplicate_beneficiary_is_not_created_and_existing_record_is_offered(): void
    {
        $officer = $this->user();
        $existing = Client::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'middle_name' => 'Santos',
            'date_of_birth' => '1990-01-01',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'address' => 'Poblacion',
            'barangay' => 'Poblacion 1',
        ]);

        $this->actingAs($officer)
            ->from(route('mswdo.beneficiaries.create'))
            ->post(route('mswdo.beneficiaries.store'), [
                'first_name' => '  JUAN ',
                'last_name' => 'dela cruz',
                'middle_name' => 'santos',
                'suffix' => null,
                'date_of_birth' => '1990-01-01',
                'sex' => 'Male',
                'civil_status' => 'Married',
                'address' => 'Different address',
                'barangay' => 'Poblacion 2',
                'contact_number' => '09171112222',
            ])
            ->assertRedirect(route('mswdo.beneficiaries.create'))
            ->assertSessionHas('duplicate_beneficiary', fn ($duplicate) =>
                $duplicate['url'] === route('mswdo.beneficiaries.show', $existing)
            );

        $this->assertSame(1, Client::count());
    }

    public function test_beneficiaries_can_be_filtered_by_barangay(): void
    {
        $officer = $this->user();
        Client::create([
            'first_name' => 'Ana', 'last_name' => 'Reyes', 'date_of_birth' => '1985-02-01',
            'sex' => 'Female', 'civil_status' => 'Single', 'address' => 'One', 'barangay' => 'Calao',
        ]);
        Client::create([
            'first_name' => 'Ben', 'last_name' => 'Santos', 'date_of_birth' => '1980-03-01',
            'sex' => 'Male', 'civil_status' => 'Married', 'address' => 'Two', 'barangay' => 'Baculud',
        ]);

        $this->actingAs($officer)
            ->get(route('mswdo.beneficiaries.index', ['barangay' => 'Calao']))
            ->assertOk()
            ->assertSee('Ana Reyes')
            ->assertDontSee('Ben Santos');
    }

    public function test_same_beneficiary_can_only_be_registered_in_another_barangay_with_explicit_override(): void
    {
        $officer = $this->user();
        Client::create([
            'first_name' => 'Lina', 'last_name' => 'Garcia', 'date_of_birth' => '1975-04-10',
            'sex' => 'Female', 'civil_status' => 'Married', 'address' => 'Old address', 'barangay' => 'Calao',
        ]);
        $payload = [
            'first_name' => 'Lina', 'last_name' => 'Garcia', 'date_of_birth' => '1975-04-10',
            'sex' => 'Female', 'civil_status' => 'Married', 'address' => 'New address', 'barangay' => 'Baculud', 'contact_number' => '09171112222',
        ];

        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $payload)
            ->assertSessionHas('duplicate_beneficiary', fn ($duplicate) => $duplicate['different_barangay'] === true);
        $this->assertSame(1, Client::count());

        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $payload + [
            'allow_duplicate_registration' => '1',
        ])->assertRedirect();
        $this->assertSame(2, Client::count());
        $this->assertDatabaseHas('clients', ['first_name' => 'Lina', 'last_name' => 'Garcia', 'barangay' => 'Baculud']);
    }

    /**
     * The birth date used to be part of the duplicate match, so a mistyped or
     * differently-remembered date let a second record through unchallenged —
     * which is the way duplicates actually got created in practice.
     */
    public function test_duplicate_is_flagged_even_when_the_birth_date_differs(): void
    {
        $officer = $this->user();
        Client::create([
            'first_name' => 'Mario', 'last_name' => 'Cruz', 'date_of_birth' => '1970-01-01',
            'sex' => 'Male', 'civil_status' => 'Single', 'address' => 'First', 'barangay' => 'Calao',
        ]);

        $payload = [
            'first_name' => 'Mario', 'last_name' => 'Cruz', 'date_of_birth' => '1985-12-25',
            'sex' => 'Male', 'civil_status' => 'Single', 'address' => 'Second', 'barangay' => 'Calao', 'contact_number' => '09171112222',
        ];

        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $payload)
            ->assertSessionHas('duplicate_beneficiary', fn ($duplicate) => $duplicate['different_barangay'] === false);
        $this->assertSame(1, Client::count());

        // Confirming once is enough: the same submit goes through with the flag.
        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $payload + [
            'allow_duplicate_registration' => '1',
        ])->assertRedirect();
        $this->assertSame(2, Client::count());
    }

    private function user(string $role = 'mswdo_officer'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function assistanceRequest(User $user, string $status = 'Pending'): AssistanceRequest
    {
        $client = Client::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'date_of_birth' => '1990-01-01',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'address' => 'Poblacion',
            'barangay' => 'Poblacion 1',
            'contact_number' => '09171234567',
        ]);

        return AssistanceRequest::create([
            'client_id' => $client->id,
            'assistance_type' => 'Medical',
            'amount_requested' => 5000,
            'purpose' => 'Medical expenses',
            'status' => $status,
            'encoded_by' => $user->id,
        ]);
    }
}
