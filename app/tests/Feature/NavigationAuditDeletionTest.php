<?php

namespace Tests\Feature;

use App\Http\Controllers\PortalController;
use App\Models\AdminRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NavigationAuditDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function user($role = 'super_admin')
    {
        return User::factory()->create(['role' => $role, 'password' => Hash::make('Password123')]);
    }

    private function record($u, $module = 'vehicles', $data = [], $status = 'Available')
    {
        return AdminRecord::create(['module' => $module, 'reference' => uniqid('QA-'), 'name' => 'QA vehicle', 'category' => '', 'department' => '', 'location' => 'Accra', 'status' => $status, 'version' => 1, 'data' => $data, 'created_by' => $u->id, 'updated_by' => $u->id]);
    }

    public function test_menu_account_and_audit_filters(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/')->assertOk()->assertSee('Toggle sidebar')->assertSee('account-menu')->assertSee('href="/account"', false)->assertDontSee('href="/security"', false);
        $this->get('/account')->assertOk()->assertSee('Change password');
        $v = $this->record($u);
        PortalController::audit('Created vehicle', $v->reference);
        PortalController::audit('Signed in', 'Account');
        PortalController::audit('Signed out', 'Account');
        PortalController::audit('Changed password', 'Account');
        $this->get('/audit?category=account')->assertOk()->assertSee('Signed in')->assertSee('Signed out')->assertDontSee('Created vehicle')->assertDontSee('Changed password');
        $this->get('/audit?category=vehicle')->assertOk()->assertSee('Created vehicle')->assertDontSee('Signed in');
        $day = now()->toDateString();
        $this->get('/audit?from_date='.$day.'&to_date='.$day)->assertOk()->assertSee('Created vehicle');
        $this->get('/audit?to_date=2000-01-01')->assertOk()->assertDontSee('Created vehicle');
        $this->get('/audit?from_date=2026-10-29&to_date=2026-10-28')->assertSessionHasErrors('to_date');
    }

    public function test_vehicle_deletion_requires_super_admin_password_and_server_word(): void
    {
        $u = $this->user();
        $v = $this->record($u);
        foreach (['admin', 'driver', 'officer'] as $role) {
            $this->actingAs($this->user($role))->get('/records/vehicles/'.$v->id.'/delete')->assertForbidden();
            $this->post('/records/vehicles/'.$v->id.'/delete')->assertForbidden();
        }
        $this->actingAs($u)->get('/records/vehicles/'.$v->id.'/delete')->assertOk();
        $c = session('vehicle_delete.'.$v->id);
        $p = ['current_password' => 'wrong', 'confirmation_word' => $c['word'], 'version' => $v->version];
        $this->post('/records/vehicles/'.$v->id.'/delete', $p)->assertSessionHasErrors('current_password');
        $p['current_password'] = 'Password123';
        $p['confirmation_word'] = 'WRONG';
        $this->post('/records/vehicles/'.$v->id.'/delete', $p)->assertSessionHasErrors('confirmation_word');
        $p['confirmation_word'] = $c['word'];
        $this->post('/records/vehicles/'.$v->id.'/delete', $p)->assertRedirect('/records/vehicles');
        $this->assertSoftDeleted($v);
        $this->assertDatabaseHas('audit_events', ['action' => 'Deleted vehicle', 'category' => 'vehicle']);
        $this->get('/records/vehicles/'.$v->id)->assertNotFound();
    }

    public function test_pending_trips_block_deletion_and_completed_history_is_retained(): void
    {
        $u = $this->user();
        $v = $this->record($u);
        $t = $this->record($u, 'trips', ['vehicle_id' => (string) $v->id, 'driver_id' => (string) $u->id], 'Pending');
        $this->actingAs($u)->get('/records/vehicles/'.$v->id.'/delete');
        $c = session('vehicle_delete.'.$v->id);
        $p = ['current_password' => 'Password123', 'confirmation_word' => $c['word'], 'version' => $v->version];
        $this->post('/records/vehicles/'.$v->id.'/delete', $p)->assertSessionHasErrors('vehicle');
        $this->assertNotSoftDeleted($v);
        $t->update(['status' => 'Completed', 'data' => array_merge($t->data, ['closing' => 1100, 'return_at' => now()->toDateTimeString(), 'distance' => 100])]);
        $this->post('/records/vehicles/'.$v->id.'/delete', $p)->assertRedirect('/records/vehicles');
        $this->get('/records/trips/'.$t->id)->assertOk()->assertSee('QA vehicle');
        $this->assertDatabaseHas('records', ['id' => $t->id]);
    }

    public function test_trip_notes_and_documents_are_scoped_and_expiry_is_removed(): void
    {
        Storage::fake('local');
        $d = $this->user('driver');
        $t = $this->record($d, 'trips', ['driver_id' => (string) $d->id], 'Pending');
        $this->actingAs($d)->get('/records/trips/'.$t->id)->assertOk()->assertSee('Additional notes')->assertSee('Attach a document')->assertDontSee('Operational history')->assertDontSee('name="expires_at"', false);
        $this->post('/records/trips/'.$t->id.'/notes', ['note' => 'Route adjusted.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/records/trips/'.$t->id)->assertSee('Route adjusted.');
        $this->post('/records/'.$t->id.'/documents', ['type' => 'Trip note', 'expires_at' => '2030-01-01', 'file' => UploadedFile::fake()->create('trip.pdf', 2, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documents', ['record_id' => $t->id, 'expires_at' => null]);
        $this->get('/documents/'.DB::table('documents')->first()->id)->assertOk();
        $this->actingAs($this->user('driver'))->post('/records/trips/'.$t->id.'/notes', ['note' => 'Other'])->assertNotFound();
        $this->actingAs($this->user('admin'))->post('/records/trips/'.$t->id.'/notes', ['note' => 'Other'])->assertForbidden();
        $this->actingAs($this->user())->post('/records/trips/'.$t->id.'/notes', ['note' => 'Super note'])->assertRedirect();
        $this->assertDatabaseCount('trip_notes', 2);
        $this->assertEquals('Pending', $t->fresh()->status);
    }

    public function test_expired_and_stale_delete_confirmations_are_rejected(): void
    {
        $u = $this->user();
        $v = $this->record($u);
        $this->actingAs($u)->get('/records/vehicles/'.$v->id.'/delete');
        $c = session('vehicle_delete.'.$v->id);
        $p = ['current_password' => 'Password123', 'confirmation_word' => $c['word'], 'version' => $v->version];
        $c['expires'] = time() - 1;
        $this->withSession(['vehicle_delete' => [$v->id => $c]])->post('/records/vehicles/'.$v->id.'/delete', $p)->assertSessionHasErrors('confirmation_word');
        $this->get('/records/vehicles/'.$v->id.'/delete');
        $c = session('vehicle_delete.'.$v->id);
        $p['confirmation_word'] = $c['word'];
        $v->increment('version');
        $this->post('/records/vehicles/'.$v->id.'/delete',$p)->assertStatus(409);
        $this->assertNotSoftDeleted($v);
    }
}
