<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan down` shows the branded maintenance screen, and pages already
 * open can tell that it happened through the ping route.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->maintenanceMode()->deactivate();
        parent::tearDown();
    }

    public function test_ping_answers_no_content_while_up(): void
    {
        $this->get(route('ping'))->assertNoContent();
    }

    public function test_down_shows_the_maintenance_screen(): void
    {
        $this->app->maintenanceMode()->activate([]);

        $this->get('/login')
            ->assertStatus(503)
            ->assertSee('System Under Maintenance');

        // Open pages poll this; the 503 is what opens their modal.
        $this->get(route('ping'))->assertStatus(503);
    }

    public function test_portal_pages_carry_the_maintenance_modal(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        $this->actingAs($officer)
            ->get(route('mswdo.dashboard'))
            ->assertOk()
            ->assertSee('data-maintenance-modal', false);
    }
}
