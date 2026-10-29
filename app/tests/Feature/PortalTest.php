<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'officer'): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    private function payload(): array
    {
        return ['name' => 'Test supplier', 'category' => 'ICT', 'department' => 'Administration', 'location' => 'Accra'];
    }

    public function test_guests_cannot_read_registers(): void
    {
        $this->get('/records/vendors')->assertRedirect('/login');
    }

    public function test_login_rate_limiting(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong']);
        }$response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_vendor_requires_independent_approval(): void
    {
        $creator = $this->user();
        $manager = $this->user('admin');
        $this->actingAs($creator)->post('/records/vendors', $this->payload())->assertRedirect();
        $record = AdminRecord::first();
        $this->assertEquals('Pending review', $record->status);
        $id = DB::table('approvals')->value('id');
        $creator->forceFill(['role' => 'admin'])->save();
        $this->actingAs($creator)->post('/approvals/'.$id, ['decision' => 'Approved', 'reason' => 'Checked'])->assertForbidden();
        $this->actingAs($manager)->post('/approvals/'.$id, ['decision' => 'Approved', 'reason' => 'Verified'])->assertRedirect();
        $this->assertEquals('Active', $record->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'Approved activate']);
    }

    public function test_drivers_cannot_read_or_export_unrelated_records(): void
    {
        $creator = $this->user();
        $this->actingAs($creator)->post('/records/vendors', $this->payload());
        $staff = $this->user('driver');
        $this->actingAs($staff)->get('/records/vendors/1')->assertNotFound();
        $this->get('/records/vendors/export')->assertForbidden();
        $this->post('/records/assets', $this->payload())->assertForbidden();
    }

    public function test_admin_can_read_business_records(): void
    {
        $creator = $this->user();
        $this->actingAs($creator)->post('/records/vendors', $this->payload());
        $this->actingAs($this->user('admin'))->get('/records/vendors/1')->assertOk();
    }

    public function test_duplicate_vehicle_registration_rejected(): void
    {
        $this->actingAs($this->user('admin'));
        $data = [...$this->payload(), 'registration' => 'GR-123-26'];
        $this->post('/records/vehicles', $data)->assertRedirect();
        $this->post('/records/vehicles', $data)->assertSessionHasErrors('registration');
        $this->assertEquals(1, AdminRecord::count());
    }

    public function test_asset_assignment_needs_approval_and_recipient_acknowledgement(): void
    {
        $creator = $this->user();
        $approver = $this->user('admin');
        $recipient = $this->user('officer');
        $this->actingAs($creator)->post('/records/assets', [...$this->payload(), 'asset_type'=>'office_equipment', 'year'=>2026, 'price'=>100, 'department'=>'HR/Admin'])->assertSessionHasNoErrors();
        $record = AdminRecord::first();
        $this->post('/records/assets/'.$record->id, ['action' => 'assign', 'custodian_id' => $recipient->id, 'reason' => 'New starter', 'version' => 1])->assertRedirect();
        $this->actingAs($approver)->post('/approvals/1', ['decision' => 'Approved', 'reason' => 'Authorized'])->assertRedirect();
        $this->assertEquals('Awaiting acknowledgement', $record->fresh()->status);
        $this->actingAs($recipient)->post('/assets/'.$record->id.'/acknowledge')->assertRedirect();
        $this->assertEquals('Assigned', $record->fresh()->status);
        $this->actingAs($creator)->post('/records/assets/'.$record->id, ['action' => 'assign', 'custodian_id' => $creator->id, 'reason' => 'Duplicate', 'version' => $record->fresh()->version])->assertStatus(422);
    }

    public function test_pages_render_for_authorized_user(): void
    {
        $this->actingAs($this->user('super_admin'));
        foreach (['/', '/records/vendors', '/records/vehicles', '/records/assets', '/records/trips', '/records/vendors/new', '/users', '/audit', '/approvals', '/reports'] as $path) {
            $this->get($path)->assertOk();
        }
    }
}

