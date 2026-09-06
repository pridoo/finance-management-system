<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AssistanceRequest;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Disbursement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The case timeline behind the eye icon in a beneficiary's assistance history:
 * every step, its exact time, who recorded it and their remarks.
 */
class CaseTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function officer(string $name = 'Officer Cruz'): User
    {
        return User::factory()->create(['role' => 'mswdo_officer', 'name' => $name]);
    }

    private function client(): Client
    {
        return Client::create([
            'first_name'    => 'Juan',
            'last_name'     => 'Dela Cruz',
            'date_of_birth' => '1990-01-01',
            'sex'           => 'Male',
            'civil_status'  => 'Single',
            'address'       => 'Poblacion',
            'barangay'      => 'Poblacion 1',
        ]);
    }

    private function request(Client $client, User $user, string $status = 'Pending'): AssistanceRequest
    {
        return AssistanceRequest::create([
            'client_id'        => $client->id,
            'assistance_type'  => 'Educational',
            'amount_requested' => 2000,
            'purpose'          => 'Tuition assistance.',
            'status'           => $status,
            'encoded_by'       => $user->id,
        ]);
    }

    public function test_a_pending_case_shows_filing_then_an_awaiting_step(): void
    {
        $officer = $this->officer();
        $req     = $this->request($this->client(), $officer);

        $response = $this->actingAs($officer)
            ->getJson(route('mswdo.approvals.show', $req))
            ->assertOk();

        $timeline = $response->json('timeline');

        $this->assertCount(2, $timeline);
        $this->assertSame('Request filed', $timeline[0]['label']);
        $this->assertSame($officer->name, $timeline[0]['by']);
        $this->assertNotEmpty($timeline[0]['time'], 'The filing time must be recorded, not just the date.');

        // Says plainly the case is still moving, so a short timeline does not
        // read as missing information.
        $this->assertSame('pending', $timeline[1]['tone']);
        $this->assertStringContainsString('Awaiting', $timeline[1]['label']);
    }

    public function test_a_rejected_case_ends_at_its_decision_and_carries_the_reason(): void
    {
        $officer = $this->officer();
        $req     = $this->request($this->client(), $officer, 'Rejected');

        Approval::create([
            'request_id'  => $req->id,
            'recorded_by' => $officer->id,
            'decision'    => 'Rejected',
            'remarks'     => 'Incomplete supporting documents.',
            'decided_at'  => now(),
        ]);

        $timeline = $this->actingAs($officer)
            ->getJson(route('mswdo.approvals.show', $req))
            ->assertOk()
            ->json('timeline');

        // Filed, rejected — and nothing after it. The absent release step is
        // the answer to "why was nothing paid out?".
        $this->assertCount(2, $timeline);
        $this->assertSame('Rejected', $timeline[1]['label']);
        $this->assertSame('bad', $timeline[1]['tone']);
        $this->assertSame('Incomplete supporting documents.', $timeline[1]['note']);
        $this->assertNotEmpty($timeline[1]['time']);
    }

    public function test_a_released_case_shows_all_three_steps_with_times_and_remarks(): void
    {
        $officer  = $this->officer();
        $releaser = $this->officer('Officer Santos');
        $req      = $this->request($this->client(), $officer, 'Released');

        Approval::create([
            'request_id'  => $req->id,
            'recorded_by' => $officer->id,
            'decision'    => 'Approved',
            'remarks'     => 'Documents complete.',
            'decided_at'  => now(),
        ]);

        $budget = Budget::create([
            'assistance_type'  => 'Educational',
            'allocated_amount' => 50000,
            'fiscal_year'      => now()->year,
            'created_by'       => $officer->id,
        ]);

        Disbursement::create([
            'request_id'      => $req->id,
            'budget_id'       => $budget->id,
            'amount_released' => 2000,
            'released_by'     => $releaser->id,
            'released_at'     => now(),
            'remarks'         => 'Claimed in person.',
        ]);

        $timeline = $this->actingAs($officer)
            ->getJson(route('mswdo.approvals.show', $req))
            ->assertOk()
            ->json('timeline');

        $this->assertCount(3, $timeline);

        $this->assertSame('Approved', $timeline[1]['label']);
        $this->assertSame('Documents complete.', $timeline[1]['note']);

        $this->assertStringContainsString('2,000.00', $timeline[2]['label']);
        $this->assertSame('Officer Santos', $timeline[2]['by'], 'Whoever released the funds, not whoever approved.');
        $this->assertSame('Claimed in person.', $timeline[2]['note']);
        $this->assertNotEmpty($timeline[2]['time']);
    }

    public function test_missing_remarks_are_explained_rather_than_left_blank(): void
    {
        $officer = $this->officer();
        $req     = $this->request($this->client(), $officer, 'Approved');

        Approval::create([
            'request_id'  => $req->id,
            'recorded_by' => $officer->id,
            'decision'    => 'Approved',
            'remarks'     => null,
            'decided_at'  => now(),
        ]);

        $timeline = $this->actingAs($officer)
            ->getJson(route('mswdo.approvals.show', $req))
            ->assertOk()
            ->json('timeline');

        // A blank panel reads as a rendering fault; say what it means.
        $this->assertSame('No reason recorded.', $timeline[1]['note']);
    }

    public function test_the_history_table_offers_the_eye_control_per_row(): void
    {
        $officer = $this->officer();
        $client  = $this->client();
        $this->request($client, $officer);

        $this->actingAs($officer)
            ->get(route('mswdo.beneficiaries.show', $client))
            ->assertOk()
            ->assertSee('data-approval-view', false)
            ->assertSee('View full case history', false);
    }
}
