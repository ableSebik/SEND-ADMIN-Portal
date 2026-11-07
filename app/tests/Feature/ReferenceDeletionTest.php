<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_lists_require_confirmation_and_the_acting_admin_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password' => 'admin-password']));
        foreach (['donor', 'project'] as $kind) {
            $table = $kind === 'donor' ? 'asset_donors' : 'asset_projects';
            $id = DB::table($table)->insertGetId($kind === 'donor'
                ? ['name' => 'Test Donor', 'normalized_name' => 'test donor']
                : ['name' => 'Test Project', 'code' => 'TEST']);
            $url = '/reference-data/'.$kind.'/'.$id;
            $this->get('/reference-data')->assertOk()->assertSee($url.'/delete');
            $this->get($url.'/delete')->assertOk()->assertSee('Type DELETE to confirm')->assertSee('Your administrator password');
            $this->delete($url, ['confirmation' => 'wrong', 'current_password' => 'wrong'])->assertSessionHasErrors(['confirmation', 'current_password']);
            $this->assertDatabaseHas($table, ['id' => $id]);
            $this->delete($url, ['confirmation' => 'DELETE', 'current_password' => 'admin-password'])->assertRedirect('/reference-data');
            $this->assertDatabaseMissing($table, ['id' => $id]);
            $this->assertDatabaseHas('audit_events', ['action' => 'Deleted '.$kind]);
        }
    }

    public function test_officers_cannot_delete_and_linked_donors_are_protected(): void
    {
        $id = DB::table('asset_donors')->insertGetId(['name' => 'Linked donor', 'normalized_name' => 'linked donor']);
        DB::table('asset_projects')->insert(['name' => 'Linked project', 'code' => 'LINK', 'donor_id' => $id]);
        $url = '/reference-data/donor/'.$id;
        $this->actingAs(User::factory()->create(['role' => 'officer']))->get($url.'/delete')->assertForbidden();
        $this->delete($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'password' => 'admin-password']));
        $this->delete($url, ['confirmation' => 'DELETE', 'current_password' => 'admin-password'])->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('asset_donors', ['id' => $id]);
    }
}
