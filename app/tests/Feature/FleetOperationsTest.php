<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use App\Services\RenewalReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FleetOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(User $actor): AdminRecord
    {
        return AdminRecord::create(['module' => 'vehicles', 'reference' => uniqid('V-'), 'name' => 'Fleet pickup',
            'category' => '', 'department' => '', 'location' => 'Accra', 'status' => 'Available',
            'data' => ['registration' => 'TEST-01', 'odometer' => 1000], 'created_by' => $actor->id, 'updated_by' => $actor->id]);
    }

    private function operation(AdminRecord $vehicle, User $actor, string $due): int
    {
        return DB::table('vehicle_operations')->insertGetId(['vehicle_id' => $vehicle->id, 'kind' => 'insurance',
            'completed_on' => '2026-01-01', 'due_on' => $due, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_servicing_changes_availability_and_completed_service_can_release_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $this->actingAs($admin)->post('/vehicles/'.$vehicle->id.'/status', ['status' => 'Maintenance', 'reason' => 'Sent to garage', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Maintenance', $vehicle->fresh()->status);
        $this->get('/operational-history?vehicle='.$vehicle->id)->assertOk()->assertSee('Sent to garage');
        $this->post('/operational-history', ['vehicle_id' => $vehicle->id, 'version' => 2, 'kind' => 'service',
            'completed_on' => now()->toDateString(), 'due_on' => now()->addMonths(3)->toDateString(), 'release_vehicle' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Available', $vehicle->fresh()->status);
        $this->assertDatabaseCount('vehicle_events', 3);
        $this->post('/vehicles/'.$vehicle->id.'/status', ['status' => 'Unavailable', 'reason' => 'Unusable', 'version' => 1])->assertStatus(409);
    }

    public function test_non_available_vehicles_are_excluded_and_server_rejects_trip_creation(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = $this->vehicle($driver);
        foreach (['Maintenance', 'Unavailable'] as $status) {
            $vehicle->update(['status' => $status]);
            $this->actingAs($driver)->get('/records/trips/new')->assertOk()->assertDontSee('TEST-01');
            $this->post('/records/trips', $this->opening($vehicle))->assertSessionHasErrors('vehicle_id');
        }
        $this->assertSame(0, AdminRecord::where('module', 'trips')->count());
        $this->post('/vehicles/'.$vehicle->id.'/status', ['status' => 'Available', 'reason' => 'Bypass', 'version' => 1])->assertForbidden();
    }

    private function opening(AdminRecord $vehicle): array
    {
        return ['name' => 'Field visit', 'category' => 'Programs', 'vehicle_id' => $vehicle->id,
            'from' => 'Accra', 'destination' => 'Tema', 'departure' => '2026-10-31T08:00',
            'opening' => 1000, 'expected_mileage' => 1000, 'mileage_verified' => 1];
    }

    public function test_mileage_verification_gap_logging_and_closing_update_current_mileage(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = $this->vehicle($driver);
        $this->actingAs($driver)->get('/records/trips/new')->assertSee('data-mileage="1000"', false)->assertSee('verified the starting mileage');
        $this->post('/records/trips', [...$this->opening($vehicle), 'mileage_verified' => 0])->assertSessionHasErrors('mileage_verified');
        $this->post('/records/trips', [...$this->opening($vehicle), 'expected_mileage' => 900])->assertSessionHasErrors('opening');
        $this->post('/records/trips', [...$this->opening($vehicle), 'opening' => 1025])->assertSessionHasErrors('mileage_note');
        $this->post('/records/trips', [...$this->opening($vehicle), 'opening' => 1025, 'mileage_note' => 'Garage test drive'])->assertSessionHasNoErrors();
        $trip = AdminRecord::where('module', 'trips')->firstOrFail();
        $this->assertSame(25, $trip->data['mileage_gap']);
        $this->assertDatabaseHas('vehicle_events', ['vehicle_id' => $vehicle->id, 'trip_id' => $trip->id, 'kind' => 'Mileage gap']);
        $this->post('/records/trips/'.$trip->id.'/close', ['closing' => 1108, 'return_at' => '2026-10-31T17:00', 'version' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(1108, $vehicle->fresh()->data['odometer']);
        $this->assertDatabaseHas('vehicle_events', ['trip_id' => $trip->id, 'kind' => 'Trip completed']);
    }

    public function test_reminders_follow_exact_schedule_recipient_roles_and_do_not_duplicate(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'phone' => '+233201234567']);
        User::factory()->create(['role' => 'officer', 'phone' => '+233201234568']);
        User::factory()->create(['role' => 'driver', 'phone' => '+233201234569']);
        User::factory()->create(['role' => 'admin', 'active' => false]);
        $vehicle = $this->vehicle($admin);
        $this->operation($vehicle, $admin, '2026-11-30');
        config(['fleet.email_enabled' => true, 'mail.default' => 'smtp', 'fleet.sms_url' => 'https://sms.example.test/send', 'fleet.sms_token' => 'test']);
        Mail::fake();
        Http::fake(['sms.example.test/*' => Http::response(['accepted' => true])]);
        foreach (range(22, -1) as $days) {
            $this->travelTo(\Carbon\Carbon::parse('2026-11-30 08:00')->subDays($days));
            app(RenewalReminders::class)->send();
            app(RenewalReminders::class)->send();
        }
        $this->assertDatabaseCount('renewal_deliveries', 28);
        $this->assertSame(28, DB::table('renewal_deliveries')->where('status', 'Sent')->count());
        Mail::assertSentCount(14);
        Http::assertSentCount(14);
    }

    public function test_renewal_cancels_old_alerts_and_provider_setup_is_not_reported_as_sent(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31 08:00'));
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '+233201234567']);
        $vehicle = $this->vehicle($admin);
        $id = $this->operation($vehicle, $admin, '2026-11-21');
        config(['fleet.email_enabled' => false, 'fleet.sms_url' => null]);
        Mail::fake(); Http::fake();
        app(RenewalReminders::class)->send();
        $this->assertSame(2, DB::table('renewal_deliveries')->where('status', 'Awaiting setup')->count());
        $this->actingAs($admin)->post('/operational-history', ['vehicle_id' => $vehicle->id, 'version' => 1,
            'kind' => 'insurance', 'completed_on' => '2026-10-31', 'due_on' => '2027-10-31'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('vehicle_operations')->where('id', $id)->value('superseded_at'));
        $this->assertSame(2, DB::table('renewal_deliveries')->where('status', 'Cancelled')->count());
        $this->travelTo(\Carbon\Carbon::parse('2026-11-07 08:00'));
        app(RenewalReminders::class)->send();
        $this->assertDatabaseCount('renewal_deliveries', 2);
        Mail::assertNothingSent(); Http::assertNothingSent();
    }

    public function test_reference_lists_prevent_unapproved_values_and_duplicate_names(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/reference-data', ['kind' => 'donor', 'name' => 'Example Foundation'])->assertSessionHasNoErrors();
        $this->post('/reference-data', ['kind' => 'donor', 'name' => ' example   foundation '])->assertSessionHasErrors('name');
        $this->post('/reference-data', ['kind' => 'project', 'name' => 'Community Health', 'code' => 'CH-26',
            'donor_id' => DB::table('asset_donors')->value('id')])->assertSessionHasNoErrors();
        $this->get('/reference-data')->assertOk()->assertSee('Community Health')->assertSee('Example Foundation');
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = $this->vehicle($driver);
        $this->actingAs($driver)->get('/records/trips/new')->assertSee('Community Health');
        $this->post('/records/trips', [...$this->opening($vehicle), 'category' => 'Unlisted project'])->assertSessionHasErrors('category');
        $this->get('/reference-data')->assertForbidden();
        $this->post('/operational-history', [])->assertForbidden();
        $this->get('/operational-history')->assertForbidden();
    }

    public function test_service_request_records_form_details_and_completes_with_renewal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle($admin);
        $this->actingAs($admin)->get('/vehicles/'.$vehicle->id.'/service')->assertOk()->assertSee('Procurement method')->assertSee('1,000.0 km');
        $payload = ['version' => 1, 'project' => 'HR/Admin', 'requested_on' => now()->toDateString(),
            'service_type' => 'Routine', 'reason' => 'Oil and filter replacement', 'provider' => 'Example Garage',
            'procurement_method' => 'RFQ', 'diagnostic_report' => 'N/A'];
        $this->post('/vehicles/'.$vehicle->id.'/service', $payload)->assertSessionHasNoErrors()->assertRedirect('/operational-history?vehicle='.$vehicle->id);
        $this->assertSame('Maintenance', $vehicle->fresh()->status);
        $this->assertDatabaseHas('vehicle_service_requests', ['vehicle_id' => $vehicle->id, 'mileage' => 1000,
            'service_type' => 'Routine', 'requested_by' => $admin->id, 'completed_at' => null]);
        $this->post('/vehicles/'.$vehicle->id.'/service', [...$payload, 'version' => 2])->assertSessionHasErrors('vehicle');
        $this->post('/vehicles/'.$vehicle->id.'/status', ['version' => 2, 'status' => 'Available', 'reason' => 'Bypass completion'])->assertSessionHasErrors('status');
        $this->post('/operational-history', ['vehicle_id' => $vehicle->id, 'version' => 2, 'kind' => 'service',
            'completed_on' => now()->toDateString(), 'due_on' => now()->addMonths(3)->toDateString(),
            'completion_notes' => 'Oil replaced and road test completed', 'release_vehicle' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Available', $vehicle->fresh()->status);
        $this->assertNotNull(DB::table('vehicle_service_requests')->value('completed_at'));
        $this->assertNotNull(DB::table('vehicle_service_requests')->value('operation_id'));
        $this->get('/operational-history')->assertOk()->assertSee('Oil replaced and road test completed')->assertSee('Oil and filter replacement');
    }

    public function test_sms_failure_retries_without_resending_successful_email(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-31 08:00'));
        $admin = User::factory()->create(['role' => 'admin', 'phone' => '+233201234567']);
        $vehicle = $this->vehicle($admin);
        $this->operation($vehicle, $admin, '2026-11-21');
        config(['fleet.email_enabled' => true, 'mail.default' => 'smtp', 'fleet.sms_url' => 'https://sms.example.test/send', 'fleet.sms_token' => 'test']);
        Mail::fake();
        Http::fake(['sms.example.test/*' => Http::sequence()->push([], 503)->push(['accepted' => true])]);
        app(RenewalReminders::class)->send();
        $this->assertDatabaseHas('renewal_deliveries', ['channel' => 'sms', 'status' => 'Failed']);
        app(RenewalReminders::class)->send();
        Mail::assertSentCount(1);
        Http::assertSentCount(2);
        $this->assertSame(2, DB::table('renewal_deliveries')->where('status', 'Sent')->count());
    }
}
