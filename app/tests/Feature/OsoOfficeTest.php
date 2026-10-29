<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OsoOfficeTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle($u, $location)
    {
        return AdminRecord::create(['module' => 'vehicles', 'reference' => uniqid('VEH-'), 'name' => $location.' fleet car', 'category' => '', 'department' => '', 'location' => $location, 'status' => 'Available', 'data' => ['odometer' => 100], 'created_by' => $u->id, 'updated_by' => $u->id]);
    }

    private function payload($v)
    {
        return ['name' => 'Field trip', 'category' => 'Programs', 'from' => 'Accra', 'destination' => 'Tamale', 'vehicle_id' => $v->id, 'departure' => '2026-10-28T08:00', 'opening' => 100];
    }

    public function test_oso_sees_only_available_same_office_vehicles_and_cannot_post_other_office()
    {
        foreach (['Accra', 'Tamale'] as $office) {
            $u = User::factory()->create(['role' => 'driver', 'location' => $office]);
            $same = $this->vehicle($u, $office);
            $other = $this->vehicle($u, $office === 'Accra' ? 'Tamale' : 'Accra');
            $this->actingAs($u)->get('/records/trips/new')->assertOk()->assertSee('>OSO<', false)->assertSee($u->name)->assertSee('value="'.$same->id.'"', false)->assertDontSee('value="'.$other->id.'"', false);
            $this->post('/records/trips', $this->payload($other))->assertSessionHasErrors('vehicle_id');
            $this->post('/records/trips', [...$this->payload($same), 'driver_id' => 999, 'location' => $other->location])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('records', ['module' => 'trips', 'created_by' => $u->id]);
        }
    }

    public function test_location_is_rechecked_on_submission_and_blank_office_cannot_choose_a_vehicle()
    {
        $u = User::factory()->create(['role' => 'driver', 'location' => 'Accra']);
        $v = $this->vehicle($u, 'Accra');
        $this->actingAs($u)->get('/records/trips/new')->assertSee($v->name);
        $u->forceFill(['location' => 'Tamale'])->save();
        $this->post('/records/trips', $this->payload($v))->assertSessionHasErrors('vehicle_id');
        $u->forceFill(['location' => ''])->save();
        $this->get('/records/trips/new')->assertDontSee($v->name);
        $this->post('/records/trips', $this->payload($v))->assertSessionHasErrors('vehicle_id');
        $this->assertDatabaseMissing('records', ['module' => 'trips']);
    }

    public function test_user_dropdowns_validate_create_and_edit_and_show_oso_label()
    {
        $u = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($u)->get('/users/new')->assertOk()->assertSee('<select id="department"', false)->assertSee('<select id="location"', false)->assertSee('Operation Support Officer (OSO)');
        $p = ['name' => 'Test OSO', 'email' => 'oso@example.test', 'role' => 'driver', 'department' => 'Programs', 'location' => 'Tamale', 'password' => 'eight888', 'password_confirmation' => 'eight888'];
        $this->post('/users', [...$p, 'department' => 'IT', 'location' => 'Kumasi'])->assertSessionHasErrors(['department', 'location']);
        $this->post('/users', $p)->assertRedirect('/users')->assertSessionHasNoErrors();
        $oso = User::where('email', $p['email'])->firstOrFail();
        $this->assertSame('Operation Support Officer (OSO)', $oso->role_label);
        $this->assertSame('driver', User::normalizeRole('OSO'));
        $this->post('/users/'.$oso->id, [...$p, 'active' => 1, 'current_password' => 'password', 'department' => 'Invalid', 'location' => 'Other'])->assertSessionHasErrors(['department', 'location']);
        $this->post('/users/'.$oso->id, [...$p, 'active' => 1, 'current_password' => 'password', 'department' => 'Finance', 'location' => 'Accra'])->assertRedirect('/users')->assertSessionHasNoErrors();
        $this->assertSame('Finance', $oso->fresh()->department);
        $this->assertSame('Accra',$oso->fresh()->location);
    }
}
