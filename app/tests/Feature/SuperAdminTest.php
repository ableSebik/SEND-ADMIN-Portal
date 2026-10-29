<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function vehicle(User $owner): AdminRecord
    {
        return AdminRecord::create(['module' => 'vehicles', 'reference' => uniqid('VEH-'), 'name' => 'Other office vehicle', 'category' => 'SUV', 'department' => 'Programmes', 'location' => 'Tamale', 'status' => 'Available', 'data' => ['driver_id' => (string) $owner->id, 'odometer' => 1000], 'created_by' => $owner->id, 'updated_by' => $owner->id]);
    }

    public function test_superadmin_and_superuser_names_have_all_permissions_and_records(): void
    {
        $owner = $this->user('driver');
        $vehicle = $this->vehicle($owner);
        foreach (['super_admin', 'superadmin', 'superuser', 'super_user'] as $role) {
            $admin = $this->user($role);
            $this->assertSame('super_admin', $admin->fresh()->role);
            foreach (['view', 'create', 'approve', 'audit', 'users', 'export', 'trip'] as $action) {
                $this->assertTrue(Access::allows($admin, $action));
            }
            $this->assertTrue(Access::scope(AdminRecord::query(), $admin)->whereKey($vehicle->id)->exists());
            $this->actingAs($admin);
            foreach (['/', '/records/vendors', '/records/vehicles', '/records/assets', '/records/trips', '/records/trips/new', '/approvals', '/reports', '/audit', '/users', '/notifications', '/security'] as $page) {
                $this->get($page)->assertOk();
            }
            $this->get('/records/trips')->assertSee('Open a trip');
        }
        $admin->forceFill(['active' => false])->save();
        $this->assertFalse(Access::allows($admin, 'trip'));
        $this->actingAs($admin)->get('/')->assertRedirect('/login');
    }

    public function test_legacy_superuser_value_is_recognized_without_changing_account_data(): void
    {
        $user = $this->user('officer');
        DB::table('users')->where('id', $user->id)->update(['role' => 'superuser']);
        $this->assertTrue($user->fresh()->isSuperAdmin());
        $this->actingAs($user->fresh())->get('/users')->assertOk()->assertSee('Add User')->assertSee('/users/'.$user->id.'/edit', false);
    }

    public function test_superadmin_can_open_for_a_driver_using_any_vehicle_and_actual_actor_is_audited(): void
    {
        $driver = $this->user('driver');
        $other = $this->user('driver');
        $admin = $this->user('superuser');
        $vehicle = $this->vehicle($other);
        $this->actingAs($admin)->get('/records/trips/new')->assertOk()->assertSee('name="driver_id"', false)->assertSee($driver->name)->assertSee($vehicle->name);
        $opening = ['name' => 'Programme visit', 'category' => 'Health', 'from' => 'Accra', 'destination' => 'Tamale', 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'departure' => '2026-10-27T08:00', 'opening' => 1000];
        $this->post('/records/trips', $opening)->assertRedirect()->assertSessionHasNoErrors();
        $trip = AdminRecord::where('module', 'trips')->firstOrFail();
        $this->assertSame($admin->id, $trip->created_by);
        $this->assertSame((string) $admin->id, $trip->data['opened_by']);
        $this->assertSame((string) $driver->id, $trip->data['driver_id']);
        $this->assertDatabaseHas('audit_events', ['user_id' => $admin->id, 'action' => 'Opened trip', 'subject' => $trip->reference]);
        $this->actingAs($driver)->get('/records/trips/'.$trip->id)->assertOk()->assertSee('Add closing details');
        $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1125, 'return_at' => '2026-10-27T12:00', 'version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Completed', $trip->fresh()->status);
    }

    public function test_superadmin_selects_real_driver_and_cannot_bypass_data_integrity(): void
    {
        $driver = $this->user('driver');
        $admin = $this->user('super_admin');
        $vehicle = $this->vehicle($driver);
        $opening = ['name' => 'Visit', 'category' => 'Admin', 'from' => 'Accra', 'destination' => 'Tamale', 'vehicle_id' => $vehicle->id, 'departure' => '2026-10-27T08:00', 'opening' => 1000];
        $this->actingAs($admin)->post('/records/trips', $opening)->assertSessionHasErrors('driver_id');
        $this->post('/records/trips', [...$opening, 'driver_id' => $admin->id])->assertSessionHasErrors('driver_id');
        $this->post('/records/trips', [...$opening, 'driver_id' => $driver->id, 'opening' => 999])->assertSessionHasErrors('opening');
        $driver->forceFill(['active' => false])->save();
        $this->post('/records/trips', [...$opening, 'driver_id' => $driver->id])->assertSessionHasErrors('driver_id');
        $this->assertSame(0, AdminRecord::where('module', 'trips')->count());
    }

    public function test_superadmin_still_cannot_self_approve_or_acknowledge_for_another_recipient(): void
    {
        $admin = $this->user('super_admin');
        $staff = $this->user('staff');
        $this->actingAs($admin)->post('/records/vendors', ['name' => 'Vendor', 'category' => 'ICT', 'department' => 'Admin', 'location' => 'Accra'])->assertRedirect();
        $this->post('/approvals/'.DB::table('approvals')->value('id'), ['decision' => 'Approved', 'reason' => 'Self approval'])->assertForbidden();
        $asset = AdminRecord::create(['module' => 'assets', 'reference' => 'ASSET-ACK', 'name' => 'Laptop', 'category' => 'ICT', 'department' => 'Admin', 'location' => 'Accra', 'status' => 'Awaiting acknowledgement', 'data' => ['custodian_id' => (string) $staff->id], 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->post('/assets/'.$asset->id.'/acknowledge')->assertForbidden();
    }
}
