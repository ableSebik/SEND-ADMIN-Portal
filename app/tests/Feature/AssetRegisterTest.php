<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use App\Services\AssetFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload($type = 'office_equipment', $name = 'Laptop A'): array
    {
        return ['asset_type' => $type, 'name' => $name, 'year' => 2026, 'description' => 'Asset description', 'office_id' => 'SG/AO/LP/1/26', 'purchase_date' => '2026-03-10', 'supplier' => 'Test Supplier', 'origin' => 'Japan', 'model' => 'Model X', 'manufacturer' => 'Example', 'manufacture_year' => 2025, 'serial' => 'SERIAL-'.$name, 'location' => 'Accra', 'staff_responsible' => 'Test Officer', 'invoice' => 'INV-100', 'price' => 1250.50, 'project' => 'Health Project', 'donor' => 'Example Donor', 'department' => 'Programs'];
    }

    public function test_type_selection_and_forms_cover_all_three_groups(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/records/assets/new')->assertOk()->assertSee('Step 1 of 2')->assertSee('Continue')->assertDontSee('name="price"', false);
        foreach (AssetFields::TYPES as $type => $label) {
            $page = $this->get('/records/assets/new?type='.$type)->assertOk()->assertSee($label)->assertSee('Step 2 of 2')->assertSee('<select id="location"', false)->assertSee('name="project"', false)->assertSee('name="donor"', false);
            if ($type === 'motor_vehicle') {
                $page->assertSee('name="origin"', false);
            } else {
                $page->assertDontSee('name="origin"', false);
            }
        }
        $this->get('/records/assets/new?type=invalid')->assertSessionHasErrors('type');
    }

    public function test_all_types_share_one_detail_table_with_the_screenshot_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'officer']));
        foreach (array_keys(AssetFields::TYPES) as $type) {
            $this->post('/records/assets', $this->payload($type, $type))->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('asset_details', 3);
        $this->assertDatabaseCount('records', 3);
        $this->assertDatabaseHas('asset_details', ['asset_type' => 'motor_vehicle', 'origin' => 'Japan', 'project' => 'Health Project', 'donor' => 'Example Donor', 'supplier' => 'Test Supplier', 'price' => 1250.50]);
        $this->assertDatabaseHas('asset_details', ['asset_type' => 'office_equipment', 'origin' => null]);
        $this->get('/records/assets')->assertOk()->assertSee('Add Asset')->assertSee('Invoice no.')->assertSee('Project')->assertSee('Donor')->assertSee('Department')->assertSee('Office Equipment');
    }

    public function test_filters_combine_and_export_contains_only_matching_assets(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/records/assets', $this->payload('motor_vehicle', 'Fleet pickup'));
        $this->post('/records/assets', [...$this->payload('office_equipment', 'Other laptop'), 'donor' => 'Another Donor']);
        $this->post('/records/assets', [...$this->payload('motor_vehicle', 'Other project vehicle'), 'project' => 'Another Project']);
        $url = '?type=motor_vehicle&donor=Example%20Donor&project=Health%20Project';
        $this->get('/records/assets'.$url)->assertOk()->assertSee('Fleet pickup')->assertDontSee('Other laptop')->assertDontSee('Other project vehicle');
        $csv = $this->get('/records/assets/export'.$url)->assertOk()->streamedContent();
        $this->assertStringContainsString('Fleet pickup', $csv);
        $this->assertStringNotContainsString('Other laptop', $csv);
        $this->assertStringNotContainsString('Other project vehicle', $csv);
        $this->assertStringContainsString('Donor', $csv);
    }

    public function test_edit_updates_detail_projection_only_after_independent_approval(): void
    {
        $u = User::factory()->create(['role' => 'officer']);
        $manager = User::factory()->create(['role' => 'admin']);
        $this->actingAs($u)->post('/records/assets', $this->payload());
        $a = AdminRecord::firstOrFail();
        $this->post('/records/assets/'.$a->id.'/edit', [...$this->payload(), 'project' => 'New Project', 'location' => 'Tamale', 'version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asset_details', ['record_id' => $a->id, 'project' => 'Health Project']);
        $this->actingAs($manager)->post('/approvals/'.DB::table('approvals')->value('id'), ['decision' => 'Approved', 'reason' => 'Confirmed'])->assertRedirect();
        $this->assertDatabaseHas('asset_details', ['record_id' => $a->id, 'project' => 'New Project']);
        $this->assertSame('Tamale', $a->fresh()->location);
    }

    public function test_server_rejects_invalid_type_office_department_and_price(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/records/assets', [...$this->payload(), 'asset_type' => 'anything', 'location' => 'Kumasi', 'department' => 'Other', 'price' => -1])->assertSessionHasErrors(['asset_type', 'location', 'department', 'price']);
        $this->assertDatabaseCount('asset_details', 0);
        $this->actingAs(User::factory()->create(['role' => 'driver']))->post('/records/assets', $this->payload())->assertForbidden();
    }

    public function test_department_is_optional_for_all_asset_types(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (AssetFields::TYPES as $type => $label) {
            foreach (['', null] as $department) {
                $this->post('/records/assets', [...$this->payload($type), 'department' => $department])->assertRedirect()->assertSessionHasNoErrors();
            }
            $payload = $this->payload($type);
            unset($payload['department']);
            $this->post('/records/assets', $payload)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('records', ['category' => $label, 'department' => '']);
            $this->get('/records/assets/new?type='.$type)->assertOk()->assertSee('Department (optional)')->assertDontSee('name="department" required', false);
        }
        $this->assertDatabaseCount('records', 9);
        $this->post('/records/assets', [...$this->payload('motor_vehicle'), 'department' => 'Other'])->assertSessionHasErrors('department');
    }

    public function test_zero_price_and_legacy_records_are_preserved(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $a = AdminRecord::create(['module' => 'assets', 'name' => 'Legacy asset', 'reference' => 'OLD-1', 'category' => 'ICT', 'department' => 'Legacy department', 'location' => 'Accra', 'status' => 'Available', 'data' => ['cost' => 0, 'supplier' => 'Legacy supplier'], 'created_by' => $u->id, 'updated_by' => $u->id]);
        $this->assertDatabaseHas('asset_details', ['record_id' => $a->id, 'price' => 0, 'supplier' => 'Legacy supplier', 'asset_type' => 'office_equipment']);
        $this->assertSame('ICT', $a->fresh()->category);
        $this->actingAs($u)->get('/records/assets?type=office_equipment')->assertOk()->assertSee('Legacy asset');
    }
}
