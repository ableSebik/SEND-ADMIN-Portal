<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AssetImportRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_endpoints_and_command_are_removed_for_all_roles(): void
    {
        foreach (['super_admin', 'admin', 'officer', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['/assets/imports', '/assets/imports/1', '/assets/imports/1/report'] as $url) {
                $this->get($url)->assertNotFound();
            }
            foreach (['/assets/imports/preview', '/assets/imports/commit'] as $url) {
                $this->post($url)->assertNotFound();
            }
        }
        $this->assertArrayNotHasKey('assets:import', Artisan::all());
    }

    public function test_asset_pages_have_no_import_controls(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->get('/records/assets')->assertOk()->assertDontSee('Import workbook')->assertDontSee('Imported register');
        $this->get('/assets/register')->assertOk()->assertDontSee('Import workbook')->assertDontSee('/assets/imports');
    }
}
