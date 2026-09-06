<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the single-source rule for SweetAlert.
 *
 * The layout used to load it twice: bundled into app.js (which sets
 * window.Swal) and again from public/vendor with a plain blocking <script>.
 * Two live instances meant a dialog opened by one could be closed by the
 * other, so buttons appeared to do nothing at all.
 */
class AssetLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sweetalert_is_loaded_exactly_once(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'mswdo_officer']))
            ->get(route('mswdo.budget.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'vendor/sweetalert2',
            $html,
            'The vendor copy of SweetAlert is back; it fights with the bundled one.'
        );
    }

    public function test_the_app_bundle_is_still_loaded(): void
    {
        // The check above must not be satisfiable by removing both copies.
        $this->actingAs(User::factory()->create(['role' => 'mswdo_officer']))
            ->get(route('mswdo.budget.index'))
            ->assertOk()
            ->assertSee('build/assets/app-', false);
    }

    public function test_bundle_url_matches_the_host_the_app_is_served_from(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        // `php artisan serve` — the app sits at the host root.
        $html = $this->actingAs($officer)
            ->get('http://127.0.0.1:8000/mswdo/budget')
            ->assertOk()
            ->getContent();

        preg_match('/<script[^>]*src="([^"]+app-[^"]+\.js)"/', $html, $matches);

        $this->assertNotEmpty($matches, 'No app bundle <script> was emitted.');
        $this->assertStringStartsWith(
            'http://127.0.0.1:8000/build/assets/',
            $matches[1],
            'The bundle URL points at a different host than the request. When that '
            . 'happens the script 404s, window.Swal is never defined, and every '
            . 'modal degrades to a native alert box.'
        );
    }

    public function test_bundle_url_keeps_the_subfolder_when_served_from_one(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        // XAMPP — the app sits under htdocs in a nested folder.
        $script = '/ash-keisha_capstone/san-agustin-websystem/public/index.php';
        $html = $this->actingAs($officer)
            ->call('GET', 'http://localhost/ash-keisha_capstone/san-agustin-websystem/public/mswdo/budget',
                [], [], [], ['SCRIPT_FILENAME' => $script, 'SCRIPT_NAME' => $script, 'PHP_SELF' => $script])
            ->getContent();

        preg_match('/<script[^>]*src="([^"]+app-[^"]+\.js)"/', $html, $matches);

        $this->assertNotEmpty($matches, 'No app bundle <script> was emitted.');
        $this->assertStringStartsWith(
            'http://localhost/ash-keisha_capstone/san-agustin-websystem/public/build/assets/',
            $matches[1],
            'The subfolder was dropped from the bundle URL, so the script 404s under XAMPP.'
        );
    }

    public function test_every_page_reaches_the_flash_partial(): void
    {
        $officer = User::factory()->create(['role' => 'mswdo_officer']);

        // The success dialog only works where this partial is rendered.
        foreach ([
            'mswdo.dashboard',
            'mswdo.budget.index',
            'mswdo.requests.index',
            'mswdo.approvals.index',
            'mswdo.beneficiaries.index',
        ] as $route) {
            $this->actingAs($officer)
                ->get(route($route))
                ->assertOk()
                ->assertSee('swalReady', false);
        }
    }
}
