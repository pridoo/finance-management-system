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
                'sex' => 'Female', 'civil_status' => 'Widowed', 'barangay' => 'Calao', 'religion' => 'Aglipayan',
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
            ->assertSeeInOrder(['Name of Client', 'Family Composition', 'Additional Income',
                'Problem Presented', 'Initial Assessment', 'Recommendation', 'Plan of Action / Taken'])
            ->assertSee('data-family-template', false);
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
}
