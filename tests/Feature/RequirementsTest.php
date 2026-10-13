<?php

namespace Tests\Feature;

use App\Models\AssistanceRequest;
use App\Models\Client;
use App\Models\User;
use App\Support\Requirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The request form lists the documents for the chosen type of assistance,
 * not one list for every type.
 */
class RequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_assistance_type_has_a_checklist(): void
    {
        foreach (['Medical', 'Burial', 'Educational', 'Livelihood'] as $type) {
            $this->assertNotEmpty(Requirements::for($type), "{$type} has no requirements.");
        }
    }

    public function test_types_ask_for_their_own_documents(): void
    {
        $labels = fn (string $type) => array_column(Requirements::for($type), 0);

        $this->assertContains('Medical certification / abstract', $labels('Medical'));
        $this->assertContains('Death certificate', $labels('Burial'));
        $this->assertContains('Funeral contract', $labels('Burial'));
        $this->assertContains('Enrollment form', $labels('Educational'));
        $this->assertNotContains('Funeral contract', $labels('Medical'));
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
            'barangay'      => 'Santos',
        ]);
    }

    public function test_ticked_requirements_are_saved(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $this->actingAs($officer)->post(route('mswdo.requests.store'), [
            'client_id'        => $this->client()->id,
            'assistance_type'  => 'Burial',
            'amount_requested' => 5000,
            'purpose'          => 'Burial of father.',
            'submitted_docs'   => Requirements::labels('Burial'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            Requirements::labels('Burial'),
            AssistanceRequest::first()->submitted_docs
        );
    }

    public function test_a_request_with_unchecked_requirements_is_refused(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $this->actingAs($officer)->post(route('mswdo.requests.store'), [
            'client_id'        => $this->client()->id,
            'assistance_type'  => 'Burial',
            'amount_requested' => 5000,
            'purpose'          => 'Burial of father.',
            'submitted_docs'   => ['Death certificate', 'Funeral contract'],
        ])->assertSessionHasErrors('submitted_docs');

        $this->assertSame(0, AssistanceRequest::count());
    }

    public function test_the_submit_button_starts_disabled(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $this->actingAs($officer)
            ->get(route('mswdo.requests.create'))
            ->assertOk()
            ->assertSee('data-submit-request disabled', false);
    }

    public function test_a_document_from_another_types_checklist_is_refused(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $this->actingAs($officer)->post(route('mswdo.requests.store'), [
            'client_id'        => $this->client()->id,
            'assistance_type'  => 'Burial',
            'amount_requested' => 5000,
            'purpose'          => 'Burial of father.',
            'submitted_docs'   => ['Enrollment form'],
        ])->assertSessionHasErrors('submitted_docs.0');

        $this->assertSame(0, AssistanceRequest::count());
    }

    public function test_the_details_page_shows_what_is_missing(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);
        $req = AssistanceRequest::create([
            'client_id'        => $this->client()->id,
            'assistance_type'  => 'Burial',
            'amount_requested' => 5000,
            'purpose'          => 'Burial of father.',
            'submitted_docs'   => ['Death certificate'],
            'status'           => 'Pending',
            'encoded_by'       => $officer->id,
        ]);

        $this->actingAs($officer)
            ->get(route('mswdo.requests.show', $req))
            ->assertOk()
            ->assertSee('1 of 5')
            ->assertSee('Funeral contract');
    }

    public function test_the_form_renders_a_group_per_type(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $response = $this->actingAs($officer)
            ->get(route('mswdo.requests.create'))
            ->assertOk();

        foreach (array_keys(Requirements::all()) as $type) {
            $response->assertSee('data-req-type="' . $type . '"', false);
        }
        $response->assertSee('Funeral contract');
    }
}
