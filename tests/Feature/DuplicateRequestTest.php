<?php

namespace Tests\Feature;

use App\Models\AssistanceRequest;
use App\Models\Client;
use App\Models\User;
use App\Support\Requirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filing a second request of the same type for a beneficiary who already has
 * one open is refused, and the clerk is offered the existing one instead.
 */
class DuplicateRequestTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        return User::factory()->create(['role' => 'mswdo_officer']);
    }

    private function client(): Client
    {
        return Client::create([
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'date_of_birth'  => '1990-01-01',
            'sex'            => 'Male',
            'civil_status'   => 'Single',
            'address'        => 'Poblacion',
            'barangay'       => 'Poblacion 1',
            'contact_number' => '09171234567',
        ]);
    }

    private function existingRequest(Client $client, User $user, string $status = 'Pending'): AssistanceRequest
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

    private function payload(Client $client, array $overrides = []): array
    {
        $payload = array_merge([
            'client_id'        => $client->id,
            'assistance_type'  => 'Educational',
            'amount_requested' => 3000,
            'purpose'          => 'Another tuition request.',
        ], $overrides);

        // A request is only accepted with its full checklist ticked.
        $payload['submitted_docs'] ??= Requirements::labels($payload['assistance_type']);

        return $payload;
    }

    public function test_a_second_open_request_of_the_same_type_is_flagged(): void
    {
        $officer = $this->officer();
        $client  = $this->client();
        $open    = $this->existingRequest($client, $officer);

        $this->actingAs($officer)
            ->post(route('mswdo.requests.store'), $this->payload($client))
            ->assertRedirect()
            ->assertSessionHas('duplicate_request', fn ($payload) => $payload['type'] === 'Educational'
                && $payload['status'] === 'Pending'
                && str_contains($payload['url'], (string) $open->id));

        // Nothing was filed while the question is outstanding.
        $this->assertSame(1, AssistanceRequest::count());
    }

    public function test_a_different_assistance_type_is_not_flagged(): void
    {
        $officer = $this->officer();
        $client  = $this->client();
        $this->existingRequest($client, $officer);

        $this->actingAs($officer)
            ->post(route('mswdo.requests.store'), $this->payload($client, ['assistance_type' => 'Medical']))
            ->assertSessionMissing('duplicate_request');

        $this->assertSame(2, AssistanceRequest::count());
    }

    public function test_a_different_beneficiary_is_not_flagged(): void
    {
        $officer = $this->officer();
        $this->existingRequest($this->client(), $officer);

        $other = Client::create([
            'first_name'    => 'Maria',
            'last_name'     => 'Santos',
            'date_of_birth' => '1988-02-02',
            'sex'           => 'Female',
            'civil_status'  => 'Married',
            'address'       => 'Poblacion',
            'barangay'      => 'Poblacion 2',
        ]);

        $this->actingAs($officer)
            ->post(route('mswdo.requests.store'), $this->payload($other))
            ->assertSessionMissing('duplicate_request');

        $this->assertSame(2, AssistanceRequest::count());
    }

    /**
     * A case sitting at Approved or For Release is just as much a reason to
     * stop and look as one still marked Pending.
     */
    public function test_unfinished_statuses_all_warn(): void
    {
        foreach (['Pending', 'Under Review', 'Approved', 'For Release'] as $status) {
            $officer = $this->officer();
            $client  = $this->client();
            $this->existingRequest($client, $officer, $status);

            $this->actingAs($officer)
                ->post(route('mswdo.requests.store'), $this->payload($client))
                ->assertSessionHas('duplicate_request', fn ($p) => $p['status'] === $status);

            AssistanceRequest::query()->delete();
            Client::query()->delete();
        }
    }

    /**
     * Rejected and Released are settled, so a fresh request is ordinary work
     * and must not be interrupted.
     */
    public function test_settled_statuses_do_not_warn(): void
    {
        foreach (['Rejected', 'Released'] as $status) {
            $officer = $this->officer();
            $client  = $this->client();
            $this->existingRequest($client, $officer, $status);

            $this->actingAs($officer)
                ->post(route('mswdo.requests.store'), $this->payload($client))
                ->assertSessionMissing('duplicate_request');

            AssistanceRequest::query()->delete();
            Client::query()->delete();
        }
    }

    public function test_the_block_cannot_be_overridden(): void
    {
        $officer = $this->officer();
        $client  = $this->client();
        $this->existingRequest($client, $officer);

        // The old "file anyway" flag no longer does anything: the open
        // request has to be closed before another of its type is filed.
        $this->actingAs($officer)
            ->post(route('mswdo.requests.store'),
                $this->payload($client, ['allow_duplicate_request' => '1']))
            ->assertSessionHas('duplicate_request');

        $this->assertSame(1, AssistanceRequest::count());
    }

    public function test_the_warning_markup_reaches_the_page(): void
    {
        $officer = $this->officer();
        $client  = $this->client();
        $this->existingRequest($client, $officer);

        $this->actingAs($officer)
            ->post(route('mswdo.requests.store'), $this->payload($client));

        // The dialog only fires if this element is actually rendered.
        $this->actingAs($officer)
            ->get(route('mswdo.requests.create'))
            ->assertOk()
            ->assertSee('data-duplicate-request', false)
            ->assertSee('data-status="Pending"', false);
    }
}
