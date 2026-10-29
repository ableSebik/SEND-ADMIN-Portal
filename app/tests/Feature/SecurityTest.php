<?php

namespace Tests\Feature;

use App\Models\AdminRecord;
use App\Models\User;
use App\Models\VendorBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'super_admin'): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role])->save();

        return $u;
    }

    private function record(User $u, string $module = 'vendors'): AdminRecord
    {
        return AdminRecord::create(['name' => 'Security test', 'reference' => uniqid('TEST-'), 'module' => $module, 'category' => 'ICT', 'department' => 'Admin', 'location' => 'Accra', 'status' => 'Available', 'data' => [], 'created_by' => $u->id, 'updated_by' => $u->id]);
    }

    public function test_disabled_account_is_denied(): void
    {
        $u = $this->account('driver');
        $u->forceFill(['active' => false])->save();
        $this->actingAs($u)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_document_download_is_scoped_and_upload_rejects_scripts(): void
    {
        Storage::fake('local');
        $u = $this->account('officer');
        $record = $this->record($u);
        $this->actingAs($u)->post('/records/'.$record->id.'/documents', ['type' => 'Certificate', 'file' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf')])->assertRedirect();
        $doc = DB::table('documents')->first();
        Storage::disk('local')->assertExists($doc->path);
        $this->get('/documents/'.$doc->id)->assertOk();
        $this->actingAs($this->account('driver'))->get('/documents/'.$doc->id)->assertNotFound();
        $this->actingAs($u)->post('/records/'.$record->id.'/documents', ['type' => 'Script', 'file' => UploadedFile::fake()->create('exploit.php', 1, 'application/x-php')])->assertSessionHasErrors('file');
    }

    public function test_bank_values_are_encrypted_and_require_independent_approval(): void
    {
        $requester = $this->account();
        $approver = $this->account();
        $record = $this->record($requester);
        $this->actingAs($requester)->post('/vendors/'.$record->id.'/bank', ['bank_name' => 'Test Bank', 'account_name' => 'Test Vendor', 'account_number' => '1234567890123', 'currency' => 'GHS', 'current_password' => 'password'])->assertRedirect();
        $approval = DB::table('approvals')->first();
        $this->assertStringNotContainsString('1234567890123', $approval->payload);
        $this->post('/approvals/'.$approval->id, ['decision' => 'Approved', 'reason' => 'Verified'])->assertForbidden();
        $this->actingAs($approver)->post('/approvals/'.$approval->id, ['decision' => 'Approved', 'reason' => 'Verified independently'])->assertRedirect();
        $this->assertEquals('1234567890123', VendorBankAccount::first()->details['account_number']);
        $this->assertStringNotContainsString('1234567890123', DB::table('vendor_bank_accounts')->value('details'));
    }

    public function test_it_provisioning_cannot_create_privileged_accounts(): void
    {
        $u = $this->account('admin');
        $this->actingAs($u)->post('/users', ['name' => 'Escalation', 'email' => 'escalation@example.test', 'role' => 'super_admin', 'password' => 'A-long-password-123', 'password_confirmation' => 'A-long-password-123', 'department' => 'IT', 'location' => 'Accra'])->assertSessionHasErrors('role');
    }

    public function test_record_version_prevents_stale_updates(): void
    {
        $u = $this->account('officer');
        $record = $this->record($u, 'assets');
        $this->actingAs($u)->post('/records/assets/'.$record->id, ['action' => 'dispose', 'reason' => 'End of life', 'version' => 99])->assertStatus(409);
    }

    public function test_record_changes_apply_only_after_approval(): void
    {
        $officer = $this->account('officer');
        $manager = $this->account('admin');
        $record = $this->record($officer, 'assets');
        $this->actingAs($officer)->get('/records/assets/'.$record->id.'/edit')->assertOk();
        $this->post('/records/assets/'.$record->id.'/edit', ['name' => 'Updated asset', 'asset_type'=>'office_equipment', 'year'=>2026, 'price'=>100, 'department' => 'HR/Admin', 'location' => 'Tamale', 'version' => '1'])->assertRedirect();
        $this->assertEquals('Security test', $record->fresh()->name);
        $this->actingAs($manager)->post('/approvals/'.DB::table('approvals')->value('id'), ['decision' => 'Approved', 'reason' => 'Checked'])->assertRedirect();
        $this->assertEquals('Updated asset', $record->fresh()->name);
    }

    public function test_rejected_bank_change_preserves_active_values(): void
    {
        $owner = $this->account();
        $approver = $this->account();
        $record = $this->record($owner);
        VendorBankAccount::create(['record_id' => $record->id, 'details' => ['account_number' => 'original']]);
        $this->actingAs($owner)->post('/vendors/'.$record->id.'/bank', ['bank_name' => 'New bank', 'account_name' => 'Vendor', 'account_number' => 'replacement', 'currency' => 'GHS', 'current_password' => 'password'])->assertRedirect();
        $this->actingAs($approver)->post('/approvals/'.DB::table('approvals')->value('id'), ['decision' => 'Rejected', 'reason' => 'Ownership not verified'])->assertRedirect();
        $this->assertEquals('original', VendorBankAccount::first()->details['account_number']);
    }

    public function test_privileged_login_goes_directly_to_dashboard_without_mfa(): void
    {
        $u = $this->account();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk();
        $this->get('/security')->assertOk()->assertDontSee('Authenticator');
        $this->post('/security/verify', ['code' => '123456'])->assertNotFound();
    }

    public function test_password_creation_and_change_accept_eight_characters_but_reject_seven(): void
    {
        $u = $this->account();
        $this->actingAs($u);
        $data = ['name' => 'New Driver', 'email' => 'newdriver@example.test', 'role' => 'driver', 'department' => 'HR/Admin', 'location' => 'Accra', 'password' => 'eight888', 'password_confirmation' => 'eight888'];
        $this->post('/users', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('eight888', User::where('email', $data['email'])->firstOrFail()->password));
        $this->post('/users', [...$data, 'email' => 'short@example.test', 'password' => 'seven77', 'password_confirmation' => 'seven77'])->assertSessionHasErrors('password');
        $this->post('/security/password', ['current_password' => 'password', 'password' => 'eight888', 'password_confirmation' => 'eight888'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('eight888', $u->fresh()->password));
    }

    public function test_password_reset_accepts_eight_characters_and_rejects_seven(): void
    {
        $user = $this->account();
        $token = Password::createToken($user);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'seven77', 'password_confirmation' => 'seven77'])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'eight888', 'password_confirmation' => 'eight888'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('eight888', $user->fresh()->password));
    }
}


