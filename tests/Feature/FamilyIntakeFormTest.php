<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The beneficiary form follows the paper Family Intake Data Form: client
 * details, family composition, housing condition and the case notes.
 */
class FamilyIntakeFormTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        return User::factory()->create(['role' => 'mswdo_officer']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'intake_date'            => '2026-10-01',
            'last_name'              => 'dela cruz',
            'first_name'             => 'juan',
            'middle_name'            => 'santos',
            'date_of_birth'          => '1980-03-15',
            'sex'                    => 'Male',
            'civil_status'           => 'Married',
            'religion'               => 'Roman Catholic',
            'birth_place'            => 'San Agustin, Isabela',
            'educational_attainment' => 'High School Graduate',
            'occupation'             => 'Farmer',
            'monthly_income'         => '4500',
            'purok'                  => '3',
            'barangay'               => 'Calao',
            'contact_number'         => '09171234567',
            'category'               => 'Indigent',
            'family' => [
                ['name' => 'maria dela cruz', 'birth_date' => '1982-07-01', 'sex' => 'Female', 'civil_status' => 'Married', 'relationship' => 'Spouse', 'educational_attainment' => 'Elementary Graduate', 'occupation_income' => 'Housewife'],
                ['name' => 'Pedro Dela Cruz', 'age' => '12', 'sex' => 'Male', 'civil_status' => 'Single', 'relationship' => 'Son'],
                ['name' => '', 'age' => '', 'relationship' => ''],
            ],
            'employment_nature'      => 'Casual',
            'housing'                => 'Light materials, one room',
            'water_source'           => 'Deep well',
            'toilet'                 => 'Water-sealed',
            'light'                  => 'Electricity',
            'problem_presented'      => 'Hospitalisation of the son.',
            'initial_assessment'     => 'Family cannot cover the bill.',
            'recommendation'         => 'Medical assistance.',
            'plan_of_action'         => 'Refer for AICS.',
        ], $overrides);
    }

    public function test_the_whole_form_is_saved_with_its_household(): void
    {
        $this->actingAs($this->officer())
            ->post(route('mswdo.beneficiaries.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $client = Client::with('familyMembers')->firstOrFail();

        $this->assertSame('Juan', $client->first_name);
        $this->assertSame('Purok 3, Calao, San Agustin, Isabela', $client->address);
        $this->assertSame('Casual', $client->employment_nature);
        $this->assertSame('Refer for AICS.', $client->plan_of_action);
        $this->assertEquals(4500, (float) $client->monthly_income);

        // The blank third row is dropped; names are title-cased.
        $this->assertCount(2, $client->familyMembers);
        $this->assertSame('Maria Dela Cruz', $client->familyMembers[0]->name);
        $this->assertSame(12, $client->familyMembers[1]->current_age);
    }

    public function test_editing_replaces_the_household(): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $this->payload());
        $client = Client::firstOrFail();

        $this->actingAs($officer)
            ->put(route('mswdo.beneficiaries.update', $client), $this->payload([
                'family' => [['name' => 'Ana Dela Cruz', 'relationship' => 'Daughter']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Ana Dela Cruz'], $client->familyMembers()->pluck('name')->all());
    }

    public function test_an_old_free_text_address_survives_an_edit_without_a_purok(): void
    {
        $officer = $this->officer();
        $client = Client::create([
            'first_name' => 'Rosa', 'last_name' => 'Reyes', 'date_of_birth' => '1970-01-01',
            'sex' => 'Female', 'civil_status' => 'Widowed', 'address' => '12 Rizal St.', 'barangay' => 'Calao',
        ]);

        $this->actingAs($officer)
            ->put(route('mswdo.beneficiaries.update', $client), [
                'first_name' => 'Rosa', 'last_name' => 'Reyes', 'date_of_birth' => '1970-01-01',
                'sex' => 'Female', 'civil_status' => 'Widowed', 'barangay' => 'Calao', 'religion' => 'Aglipayan', 'contact_number' => '09171112222',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('12 Rizal St.', $client->fresh()->address);
        $this->assertSame('Aglipayan', $client->fresh()->religion);
    }

    public function test_the_form_shows_every_section(): void
    {
        $this->actingAs($this->officer())
            ->get(route('mswdo.beneficiaries.create'))
            ->assertOk()
            ->assertSee('Family Intake Data Form')
            ->assertSeeInOrder(['Source of Information / Referring Party', "Client's Information",
                'Family Composition', 'Additional Income',
                'Problem Presented', 'Initial Assessment', 'Recommendation', 'Plan of Action / Taken'], false)
            ->assertSee('data-family-template', false);
    }

    public function test_the_referring_party_is_saved_and_printed(): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $this->payload([
            'referrer_name'           => 'Lito Ramos',
            'referrer_relationship'   => 'Barangay Official',
            'referrer_sex'            => 'Male',
            'referrer_date_of_birth'  => '1970-05-05',
            'referrer_occupation'     => 'Student',
            'referrer_income'         => '5000',
            'referrer_contact_number' => '09181234567',
            'referrer_address'        => 'Purok 1, Calao, San Agustin, Isabela',
        ]))->assertSessionHasNoErrors();

        $client = Client::firstOrFail();
        $this->assertSame('Lito Ramos', $client->referrer_name);
        // A student earns nothing, whatever was typed.
        $this->assertEquals(0, (float) $client->referrer_income);
        // The referring party is not part of the household's income.
        $this->assertEquals(4500 + 0, (float) $client->monthly_income);

        $this->actingAs($officer)->get(route('mswdo.beneficiaries.show', $client))
            ->assertOk()
            ->assertSee('Lito Ramos')
            ->assertSee('Barangay Official');

        $this->actingAs($officer)->get(route('mswdo.beneficiaries.intake-pdf', $client))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_profile_and_printable_form_show_the_intake(): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $this->payload());
        $client = Client::firstOrFail();

        $this->actingAs($officer)
            ->get(route('mswdo.beneficiaries.show', $client))
            ->assertOk()
            ->assertSee('Maria Dela Cruz')
            ->assertSee('Refer for AICS.');

        $this->actingAs($officer)
            ->get(route('mswdo.beneficiaries.intake-pdf', $client))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unemployed_and_students_have_zero_income_and_family_income_is_totalled(): void
    {
        $this->actingAs($this->officer())
            ->post(route('mswdo.beneficiaries.store'), $this->payload([
                'occupation' => 'Farmer', 'monthly_income' => '4500',
                'family' => [
                    ['name' => 'Maria Santos Dela Cruz', 'occupation' => 'Vendor', 'monthly_income' => '3000'],
                    ['name' => 'Pedro Santos Dela Cruz', 'occupation' => 'Student', 'monthly_income' => '999'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $client = Client::with('familyMembers')->firstOrFail();
        $this->assertEquals(0, (float) $client->familyMembers[1]->monthly_income);
        $this->assertEquals(7500, $client->family_income);
    }

    public function test_nature_of_employment_accepts_not_applicable(): void
    {
        $this->actingAs($this->officer())
            ->post(route('mswdo.beneficiaries.store'), $this->payload(['employment_nature' => 'Not Applicable']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Not Applicable', Client::firstOrFail()->employment_nature);
    }

    public function test_cp_number_is_required(): void
    {
        $this->actingAs($this->officer())
            ->post(route('mswdo.beneficiaries.store'), $this->payload(['contact_number' => '']))
            ->assertSessionHasErrors('contact_number');
    }

    public function test_a_family_member_client_is_linked_to_the_head_and_shares_the_ledger(): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $this->payload());
        $head = Client::firstOrFail();

        // The lookup finds the head by ID and returns the household to copy.
        $this->actingAs($officer)
            ->getJson(route('mswdo.beneficiaries.lookup', ['q' => (string) $head->id]))
            ->assertOk()
            ->assertJsonPath('0.id', $head->id)
            ->assertJsonPath('0.purok', '3');

        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $this->payload([
            'first_name' => 'Pedro', 'middle_name' => '', 'date_of_birth' => '2010-01-01',
            'family_head_id' => $head->id, 'family' => [],
        ]))->assertSessionHasNoErrors();
        $son = Client::where('first_name', 'Pedro')->firstOrFail();
        $this->assertSame($head->id, $son->family_head_id);

        \App\Models\AssistanceRequest::create([
            'client_id' => $son->id, 'assistance_type' => 'Medical', 'amount_requested' => 2500,
            'purpose' => 'Checkup.', 'status' => 'Pending', 'encoded_by' => $officer->id,
        ]);

        // The head's profile shows the son's request in the family ledger.
        $this->actingAs($officer)
            ->get(route('mswdo.beneficiaries.show', $head))
            ->assertOk()
            ->assertSee('Family Ledger')
            ->assertSee('Pedro Dela Cruz');
    }

    public function test_searching_a_household_member_finds_the_family_head(): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $this->payload());

        $this->actingAs($officer)
            ->get(route('mswdo.beneficiaries.index', ['search' => 'Pedro Dela Cruz']))
            ->assertOk()
            ->assertSee('Juan S. Dela Cruz')
            ->assertSee('Family Head of')
            ->assertSee('Pedro Dela Cruz (Son)');

        // A full name typed with spaces still finds the beneficiary themselves.
        $this->actingAs($officer)
            ->get(route('mswdo.beneficiaries.index', ['search' => 'Juan Dela Cruz']))
            ->assertSee('Juan S. Dela Cruz');
    }

    public function test_household_endpoint_reports_assistance_already_received_in_the_family(): void
    {
        $officer = $this->officer();
        $this->actingAs($officer)->post(route('mswdo.beneficiaries.store'), $this->payload());
        $head = Client::firstOrFail();

        // Maria, listed as Juan's spouse, is also registered on her own and
        // has already received Medical assistance.
        $maria = Client::create([
            'first_name' => 'Maria', 'last_name' => 'Dela Cruz', 'date_of_birth' => '1982-07-01',
            'sex' => 'Female', 'civil_status' => 'Married', 'address' => 'Calao', 'barangay' => 'Calao',
        ]);
        \App\Models\AssistanceRequest::create([
            'client_id' => $maria->id, 'assistance_type' => 'Medical', 'amount_requested' => 5000,
            'purpose' => 'Hospital bill', 'status' => 'Released', 'encoded_by' => $officer->id,
        ]);

        $json = $this->actingAs($officer)
            ->getJson(route('mswdo.beneficiaries.household', $head))
            ->assertOk()
            ->json();

        $this->assertSame('Maria Dela Cruz', $json['members'][0]['name']);
        $this->assertSame('Medical', $json['members'][0]['matches'][0]['requests'][0]['type']);
        $this->assertSame([], $json['members'][1]['matches']);

        // From Maria's side: she is listed in Juan's household.
        $fromMaria = $this->actingAs($officer)
            ->getJson(route('mswdo.beneficiaries.household', $maria))
            ->json();
        $this->assertSame('Spouse', $fromMaria['listed_in'][0]['relationship']);
    }
}
