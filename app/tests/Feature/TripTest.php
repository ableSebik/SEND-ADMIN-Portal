<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'driver'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function vehicle(User $driver): AdminRecord
    {
        return AdminRecord::create(['module' => 'vehicles', 'reference' => uniqid('VEH-'), 'name' => 'Test vehicle', 'category' => 'SUV', 'department' => 'Admin', 'location' => 'Accra', 'status' => 'Available', 'data' => ['driver_id' => (string) $driver->id, 'odometer' => 1000], 'created_by' => $driver->id, 'updated_by' => $driver->id]);
    }

    private function opening(AdminRecord $vehicle): array
    {
        return ['name' => 'Field visit', 'category' => 'Health project', 'from' => 'Accra', 'destination' => 'Tamale', 'vehicle_id' => $vehicle->id, 'departure' => '2026-10-27T08:00', 'opening' => 1000];
    }

    private function open(User $driver): AdminRecord
    {
        $vehicle = $this->vehicle($driver);
        $this->actingAs($driver)->post('/records/trips', $this->opening($vehicle))->assertRedirect()->assertSessionHasNoErrors();

        return AdminRecord::where('module', 'trips')->firstOrFail();
    }

    public function test_only_drivers_and_superadmins_can_open_trips_including_direct_requests(): void
    {
        foreach (['admin', 'officer'] as $role) {
            $this->actingAs($this->user($role))->get('/records/trips/new')->assertForbidden();
            $this->post('/records/trips', [])->assertForbidden();
        }
    }

    public function test_opening_form_has_fixed_driver_and_no_closing_or_department_field(): void
    {
        $driver = $this->user();
        $this->vehicle($driver);
        $this->actingAs($driver)->get('/records/trips/new')->assertOk()->assertSee('readonly', false)
            ->assertSee($driver->name)->assertSee('Department/Project')->assertSee('name="from"', false)
            ->assertSee('name="destination"', false)->assertSee('Mileage Before')
            ->assertDontSee('name="department"', false)->assertDontSee('name="driver_id"', false)
            ->assertDontSee('name="return_at"', false)->assertDontSee('name="closing"', false);
    }

    public function test_opening_uses_authenticated_driver_and_remains_pending_without_closing_details(): void
    {
        $driver = $this->user();
        $other = $this->user();
        $vehicle = $this->vehicle($driver);
        $this->actingAs($driver)->post('/records/trips', [...$this->opening($vehicle), 'driver_id' => $other->id, 'closing' => 1500, 'return_at' => '2026-10-27T18:00'])->assertRedirect()->assertSessionHasNoErrors();
        $trip = AdminRecord::where('module', 'trips')->firstOrFail();
        $this->assertSame('Pending', $trip->status);
        $this->assertSame((string) $driver->id, $trip->data['driver_id']);
        $this->assertSame($driver->id, $trip->created_by);
        $this->assertArrayNotHasKey('closing', $trip->data);
        $this->assertArrayNotHasKey('return_at', $trip->data);
        $this->assertDatabaseCount('approvals', 0);
        $this->get('/records/trips')->assertOk()->assertSee('badge pending', false)->assertSee('Add closing details');
        $this->get('/records/trips/'.$trip->id)->assertOk()->assertSee('Opening details')->assertSee('Closing details');
    }

    public function test_opening_requires_route_and_mileage_fields(): void
    {
        $driver = $this->user();
        $vehicle = $this->vehicle($driver);
        $data = $this->opening($vehicle);
        unset($data['from'],$data['destination'],$data['opening']);
        $this->actingAs($driver)->post('/records/trips', $data)->assertSessionHasErrors(['from', 'destination', 'opening']);
    }

    public function test_driver_can_complete_own_trip_and_mileage_updates(): void
    {
        $driver = $this->user();
        $trip = $this->open($driver);
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(17, 35));
        $this->get('/records/trips/'.$trip->id.'/close')->assertOk()->assertSee('value="2026-10-27T17:35"', false)->assertSee('Mileage After');
        $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1125, 'return_at' => '2026-10-27T17:20', 'version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $trip->refresh();
        $this->assertSame('Completed', $trip->status);
        $this->assertEquals(125, $trip->data['distance']);
        $this->assertSame('2026-10-27T17:20', $trip->data['return_at']);
        $this->assertEquals(1125, AdminRecord::findOrFail($trip->data['vehicle_id'])->data['odometer']);
        $this->get('/records/trips/'.$trip->id)->assertOk()->assertSee('Completed')->assertDontSee('Add closing details');
        $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1200, 'return_at' => '2026-10-27T18:00', 'version' => 2])->assertStatus(409);
    }

    public function test_superadmin_can_close_but_other_users_cannot(): void
    {
        $driver = $this->user();
        $trip = $this->open($driver);
        foreach (['driver', 'officer', 'admin'] as $role) {
            $this->actingAs($this->user($role))->get('/records/trips/'.$trip->id.'/close')->assertForbidden();
            $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1100, 'return_at' => '2026-10-27T12:00', 'version' => 1])->assertForbidden();
        }
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->get('/records/trips/'.$trip->id.'/close')->assertOk();
        $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1100, 'return_at' => '2026-10-27T12:00', 'version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame((string) $admin->id, $trip->fresh()->data['closed_by']);
    }

    public function test_invalid_or_incomplete_closing_and_stale_versions_do_not_complete_trip(): void
    {
        $driver = $this->user();
        $trip = $this->open($driver);
        $url = '/records/trips/'.$trip->id.'/close';
        $this->post($url, ['version' => 1])->assertSessionHasErrors(['closing', 'return_at']);
        $this->post($url, ['closing' => 999, 'return_at' => '2026-10-27T12:00', 'version' => 1])->assertSessionHasErrors('closing');
        $this->post($url, ['closing' => 1100, 'return_at' => '2026-10-27T07:59', 'version' => 1])->assertSessionHasErrors('return_at');
        $this->post($url, ['closing' => 1100, 'return_at' => '2026-10-27T12:00', 'version' => 99])->assertStatus(409);
        $this->assertSame('Pending', $trip->fresh()->status);
    }

    public function test_pending_trip_blocks_another_opening_and_completion_releases_vehicle(): void
    {
        $driver = $this->user();
        $trip = $this->open($driver);
        $vehicle = AdminRecord::findOrFail($trip->data['vehicle_id']);
        $next = [...$this->opening($vehicle), 'departure' => '2026-10-28T08:00', 'opening' => 1100];
        $this->post('/records/trips', $next)->assertSessionHasErrors('vehicle_id');
        $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1100, 'return_at' => '2026-10-27T17:00', 'version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/records/trips', $next)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(2, AdminRecord::where('module', 'trips')->count());
    }

    public function test_driver_without_licence_verification_can_open_but_invalid_mileage_is_rejected(): void
    {
        $driver = $this->user();
        $vehicle = $this->vehicle($driver);
        $this->actingAs($driver)->post('/records/trips', [...$this->opening($vehicle), 'opening' => 999])->assertSessionHasErrors('opening');
        $this->post('/records/trips', $this->opening($vehicle))->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_old_action_and_approval_routes_cannot_bypass_closing_permissions(): void
    {
        $driver = $this->user();
        $trip = $this->open($driver);
        $this->actingAs($this->user('admin'));
        $this->post('/records/trips/'.$trip->id, ['action' => 'close', 'closing' => 1100, 'reason' => 'Bypass', 'version' => 1])->assertForbidden();
        $id = DB::table('approvals')->insertGetId(['record_id' => $trip->id, 'action' => 'close', 'payload' => '{"closing":1100}', 'requested_by' => $driver->id, 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/approvals/'.$id, ['decision' => 'Approved', 'reason' => 'Bypass'])->assertStatus(409);
        $this->assertSame('Pending', $trip->fresh()->status);
    }

    public function test_export_contains_both_trip_phases(): void
    {
        $driver = $this->user();
        $trip = $this->open($driver);
        $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1100, 'return_at' => '2026-10-27T12:00', 'version' => 1])->assertRedirect();
        $this->actingAs($this->user('super_admin'));
        $response = $this->get('/records/trips/export')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Mileage Before', $csv);
        $this->assertStringContainsString('Mileage After', $csv);
        $this->assertStringContainsString('Department/Project', $csv);
        $this->assertStringContainsString('Tamale', $csv);
        $this->assertStringContainsString('Completed', $csv);
    }

    public function test_legacy_trip_migration_preserves_history_and_retires_pending_approvals(): void
    {
        $driver = $this->user();
        $vehicle = $this->vehicle($driver);
        $trip = AdminRecord::create(['module' => 'trips', 'reference' => 'LEGACY-TRIP', 'name' => 'Legacy journey', 'category' => 'Programme', 'department' => 'Admin', 'location' => 'Accra → Tamale', 'status' => 'Approved', 'data' => ['driver_id' => (string) $driver->id, 'vehicle_id' => (string) $vehicle->id, 'departure' => '2026-10-27T08:00', 'opening' => 1000, 'return_at' => '2026-10-27T18:00'], 'created_by' => $driver->id, 'updated_by' => $driver->id]);
        DB::table('approvals')->insert(['record_id' => $trip->id, 'action' => 'activate', 'payload' => '[]', 'status' => 'Pending', 'requested_by' => $driver->id, 'created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_10_27_000001_update_trip_workflow.php');
        $migration->up();
        $trip->refresh();
        $this->assertSame('Pending', $trip->status);
        $this->assertSame('Accra', $trip->data['from']);
        $this->assertSame('Tamale', $trip->data['destination']);
        $this->assertSame('2026-10-27T18:00', $trip->data['legacy_expected_return_at']);
        $this->assertArrayNotHasKey('return_at', $trip->data);
        $this->assertDatabaseHas('approvals', ['record_id' => $trip->id, 'status' => 'Superseded']);
    }
}
