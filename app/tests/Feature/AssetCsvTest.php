<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssetFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('asset_projects')->insert(['name' => 'Health Project', 'code' => 'HEALTH', 'status' => 'Active']);
    }

    public function test_templates_and_upload_forms_match_each_type(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (AssetFields::TYPES as $type => $label) {
            $response = $this->get('/records/assets/csv-template?asset_type='.$type)->assertOk();
            $csv = $response->streamedContent();
            $this->assertSame("\xEF\xBB\xBF".implode(',', AssetFields::csvColumns($type))."\n", $csv);
            $this->get('/records/assets/new?type='.$type)->assertOk()->assertSee('Download CSV template')->assertSee('name="csv"', false);
        }
        $this->get('/records/assets/csv-template?asset_type=invalid')->assertSessionHasErrors('asset_type');
    }

    public function test_bulk_upload_creates_assets_and_details_for_all_types(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (array_keys(AssetFields::TYPES) as $type) {
            $csv = "\xEF\xBB\xBFname,year,location,price,project,serial,office_id,purchase_date\nFirst asset,2026,Accra,1250.50,Health Project,S1,ID1,2026-01-01\nSecond asset,2025,Tamale,0,Health Project,S2,ID2,2025-01-01\n";
            $this->post('/records/assets/csv-upload', ['asset_type' => $type, 'csv' => UploadedFile::fake()->createWithContent('assets.csv', $csv)])
                ->assertSessionHasNoErrors()->assertRedirect('/records/assets');
            $this->assertDatabaseHas('asset_details', ['asset_type' => $type, 'price' => 1250.50]);
        }
        $this->assertDatabaseCount('records', 6);
        $this->assertDatabaseCount('asset_details', 6);
        $this->assertDatabaseCount('audit_events', 9);
    }

    public function test_invalid_rows_save_nothing_and_report_row_number(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $csv = "name,year,location,price,project,serial,office_id,purchase_date\nValid,2026,Accra,10,Health Project,S1,ID1,2026-01-01\nInvalid,2026,Unknown,-1,Missing project,S2,ID2,2026-01-01\n";
        $response = $this->from('/records/assets/new?type=office_equipment')->post('/records/assets/csv-upload', [
            'asset_type' => 'office_equipment', 'csv' => UploadedFile::fake()->createWithContent('assets.csv', $csv),
        ])->assertSessionHasErrors('csv');
        $this->assertStringContainsString('Row 3:', session('errors')->first('csv'));
        $this->assertDatabaseCount('records', 0);
    }

    public function test_bad_headers_empty_files_and_excess_rows_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'officer']));
        foreach (["name,name,location,price\na,b,Accra,1\n", "name,year,location,price\n", "name,year,location,price,project,serial,office_id,purchase_date\n".str_repeat("Asset,2026,Accra,1,Health Project,S1,ID1,2026-01-01\n", 1001), "name,year,location,price\nToo,few\n"] as $csv) {
            $this->post('/records/assets/csv-upload', ['asset_type' => 'office_equipment', 'csv' => UploadedFile::fake()->createWithContent('assets.csv', $csv)])->assertSessionHasErrors('csv');
        }
        $this->assertDatabaseCount('records', 0);
    }

    public function test_drivers_cannot_download_templates_or_upload(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'driver']));
        $this->get('/records/assets/csv-template?asset_type=office_equipment')->assertForbidden();
        $this->post('/records/assets/csv-upload', ['asset_type' => 'office_equipment'])->assertForbidden();
    }
}
