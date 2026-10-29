<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleVehicleUserTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    private function vehicle(User $owner): AdminRecord
    {
        return AdminRecord::create(['module' => 'vehicles', 'reference' => uniqid('VEH-'), 'unique_key' => uniqid('vehicle:'), 'name' => 'Shared fleet car', 'category' => 'Legacy category', 'department' => 'Legacy department', 'location' => 'Accra', 'status' => 'Available', 'data' => ['registration' => 'GR-22-26', 'driver_id' => (string) $owner->id, 'odometer' => 1000, 'cost' => 50000, 'manufacturer' => 'Legacy make'], 'created_by' => $owner->id, 'updated_by' => $owner->id]);
    }

    public function test_driver_has_only_trips_in_sidebar_and_lands_on_trips(): void
    {
        $driver = $this->user('driver');
        $this->post('/login', ['email' => $driver->email, 'password' => 'password'])->assertRedirect('/records/trips');
        $this->get('/')->assertRedirect('/records/trips');
        $response = $this->get('/records/trips')->assertOk();
        preg_match('/<nav aria-label="Main navigation">(.*?)<\/nav>/s', $response->getContent(), $match);
        $this->assertSame(1, substr_count($match[1], '<a '));
        $this->assertStringContainsString('Trips', $match[1]);
        foreach (['Dashboard', 'Vehicles', 'Users', 'Notifications', 'Driver Logs'] as $label) {
            $this->assertStringNotContainsString($label, $match[1]);
        }
    }

    public function test_all_roles_read_all_vehicles_but_only_super_admin_and_admin_can_write(): void
    {
        $owner = $this->user('driver');
        $vehicle = $this->vehicle($owner);
        $payload = ['name' => 'New fleet vehicle', 'registration' => 'GR-44-26', 'location' => 'Tamale'];
        foreach (['driver', 'officer', 'admin', 'super_admin'] as $role) {
            $vehicle->refresh();
            $actor = $this->user($role);
            $this->actingAs($actor)->get('/records/vehicles')->assertOk()->assertSee($vehicle->name);
            $this->get('/records/vehicles/'.$vehicle->id)->assertOk()->assertDontSee('Legacy category')->assertDontSee('Legacy department')->assertDontSee('50000')->assertDontSee('Legacy make');
            if (in_array($role, ['admin', 'super_admin'])) {
                $this->get('/records/vehicles')->assertSee('Add vehicle')->assertDontSee('Add record');
                $this->get('/records/vehicles/'.$vehicle->id.'/edit')->assertOk();
                $this->post('/records/vehicles/'.$vehicle->id.'/edit', [...$payload, 'registration' => $role.'-22', 'version' => $vehicle->fresh()->version])->assertRedirect()->assertSessionHasNoErrors();
            } else {
                if ($role === 'officer') {
                    $this->get('/')->assertOk()->assertDontSee('/records/vehicles/new', false);
                }
                $this->get('/records/vehicles')->assertDontSee('Add vehicle');
                $this->get('/records/vehicles/new')->assertForbidden();
                $this->post('/records/vehicles', $payload)->assertForbidden();
                $this->get('/records/vehicles/'.$vehicle->id.'/edit')->assertForbidden();
                $this->post('/records/vehicles/'.$vehicle->id.'/edit', [...$payload, 'version' => 1])->assertForbidden();
                $this->post('/records/'.$vehicle->id.'/entries', ['type' => 'Maintenance', 'title' => 'Unauthorized', 'date' => '2026-10-27'])->assertForbidden();
                $this->post('/records/'.$vehicle->id.'/documents', ['type' => 'Certificate', 'file' => UploadedFile::fake()->create('proof.pdf', 20)])->assertForbidden();
            }
        }
    }

    public function test_vehicle_form_omits_removed_fields_and_creates_without_them(): void
    {
        $this->actingAs($this->user('admin'));
        $page = $this->get('/records/vehicles/new')->assertOk();
        foreach (['name="department"', 'name="category"', 'name="cost"', 'Additional information'] as $removed) {
            $page->assertDontSee($removed, false);
        }
        $this->post('/records/vehicles', ['name' => 'Toyota', 'registration' => 'GR-2026', 'location' => 'Accra', 'category' => 'Injected', 'department' => 'Injected', 'cost' => 100, 'manufacturer' => 'Injected'])->assertRedirect()->assertSessionHasNoErrors();
        $record = AdminRecord::firstOrFail();
        $this->assertSame('', $record->category);
        $this->assertSame('', $record->department);
        foreach (['category', 'department', 'cost', 'manufacturer'] as $field) {
            $this->assertArrayNotHasKey($field, $record->data);
        }
        $csv = $this->get('/records/vehicles/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Registration', $csv);
        foreach (['Category', 'Department', 'Acquisition cost'] as $removed) {
            $this->assertStringNotContainsString($removed, $csv);
        }
    }

    public function test_oso_can_select_same_office_vehicle_without_licence_verification(): void
    {
        $owner = $this->user('driver');
        $driver = $this->user('driver');
        $vehicle = $this->vehicle($owner);
        $this->assertNull($driver->licence_expiry);
        $this->actingAs($driver)->get('/records/trips/new')->assertOk()->assertSee($vehicle->name);
        $this->post('/records/trips', ['name' => 'Shared vehicle trip', 'category' => 'Admin', 'from' => 'Accra', 'destination' => 'Tamale', 'vehicle_id' => $vehicle->id, 'departure' => '2026-10-27T08:00', 'opening' => 1000])->assertRedirect()->assertSessionHasNoErrors();
        $trip = AdminRecord::where('module', 'trips')->firstOrFail();
        $this->assertSame((string) $driver->id, $trip->data['driver_id']);
    }

    public function test_user_list_has_add_button_and_clickable_accounts_and_four_roles(): void
    {
        $admin = $this->user('super_admin');
        $driver = $this->user('driver');
        $this->actingAs($admin);
        $this->get('/users')->assertOk()->assertSee('Add User')->assertSee('/users/'.$driver->id.'/edit', false)->assertDontSee('Update account access or driver authorization')->assertDontSee('licence_expiry');
        foreach (['/users/new', '/users/'.$driver->id.'/edit'] as $url) {
            $page = $this->get($url)->assertOk()->assertDontSee('licence_expiry');
            foreach (User::ROLES as $key => $label) {
                $page->assertSee('value="'.$key.'"', false)->assertSee($label);
            }
            $this->assertSame(4, preg_match_all('/<option value="(super_admin|driver|admin|officer)"/', $page->getContent()));
        }
        $this->post('/users/'.$driver->id, ['name' => 'Updated driver', 'email' => 'updated@example.test', 'department' => 'Programs', 'location' => 'Tamale', 'role' => 'officer', 'active' => 1, 'password' => 'eight888', 'password_confirmation' => 'eight888', 'current_password' => 'password', 'licence_expiry' => '1900-01-01'])->assertRedirect('/users')->assertSessionHasNoErrors();
        $driver->refresh();
        $this->assertSame('Updated driver', $driver->name);
        $this->assertSame('officer', $driver->role);
        $this->assertNull($driver->licence_expiry);
        $this->assertTrue(Hash::check('eight888', $driver->password));
    }

    public function test_admin_cannot_edit_or_create_privileged_accounts_and_other_roles_cannot_manage_users(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');
        $driver = $this->user('driver');
        $this->actingAs($admin)->get('/users/'.$driver->id.'/edit')->assertOk();
        $this->get('/users/'.$super->id.'/edit')->assertForbidden();
        $this->post('/users/'.$super->id, [])->assertForbidden();
        $this->post('/users', ['name' => 'New admin', 'email' => 'escalate@example.test', 'department' => 'Admin', 'location' => 'Accra', 'role' => 'super_admin', 'password' => 'eight888', 'password_confirmation' => 'eight888'])->assertSessionHasErrors('role');
        foreach (['driver', 'officer'] as $role) {
            $this->actingAs($this->user($role))->get('/users')->assertForbidden();
            $this->get('/users/'.$driver->id.'/edit')->assertForbidden();
            $this->post('/users/'.$driver->id, [])->assertForbidden();
        }
    }

    public function test_only_four_roles_are_accepted_and_legacy_roles_are_migrated(): void
    {
        $admin = $this->user('super_admin');
        $this->actingAs($admin);
        foreach (['staff', 'it_admin', 'admin_manager', 'finance', 'auditor', 'management'] as $old) {
            $this->post('/users', ['name' => 'Legacy', 'email' => $old.'@example.test', 'department' => 'Admin', 'location' => 'Accra', 'role' => $old, 'password' => 'eight888', 'password_confirmation' => 'eight888'])->assertSessionHasErrors('role');
        }
        $legacy = $this->user('officer');
        DB::table('users')->where('id', $legacy->id)->update(['role' => 'it_admin']);
        $migration = require database_path('migrations/2026_10_27_000002_simplify_user_roles.php');
        $migration->up();
        $this->assertSame('admin', DB::table('users')->where('id', $legacy->id)->value('role'));
        $this->assertSame($legacy->id, $legacy->fresh()->id);
    }
}

