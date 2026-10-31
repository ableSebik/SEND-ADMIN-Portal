<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function form(Request $request, ?int $id = null)
    {
        abort_unless(Access::allows($request->user(), 'users'), 403);
        $account = $id ? User::findOrFail($id) : null;
        if ($account) {
            abort_unless(Access::canManageUser($request->user(), $account), 403);
        }
        $roles = $request->user()->isSuperAdmin() ? User::ROLES : array_intersect_key(User::ROLES, array_flip(['driver', 'officer']));

        return view('accounts.form', compact('account', 'roles'));
    }

    public function save(Request $request, ?int $id = null)
    {
        abort_unless(Access::allows($request->user(), 'users'), 403);
        if ($id) {
            abort_unless(Access::canManageUser($request->user(), User::findOrFail($id)), 403);
        }
        $allowed = $request->user()->isSuperAdmin() ? array_keys(User::ROLES) : ['driver', 'officer'];
        $validated = $request->validate(['name' => 'required|string|max:180', 'phone' => ['nullable', 'regex:/^\+[1-9][0-9]{7,14}$/'], 'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($id)], 'department' => ['required', Rule::in(User::DEPARTMENTS)], 'location' => ['required', Rule::in(User::OFFICES)], 'role' => ['required', Rule::in($allowed)], 'active' => $id ? 'required|boolean' : 'sometimes|boolean', 'password' => ($id ? 'nullable' : 'required').'|string|min:8|confirmed', ...($id ? ['current_password' => 'required|current_password'] : [])]);
        if ($id === $request->user()->id) {
            abort_if(! $validated['active'] || $validated['role'] !== 'super_admin', 422, 'You cannot disable or demote your own Super Admin account.');
        }
        DB::transaction(function () use ($request, $validated, $id) {
            $account = $id ? User::lockForUpdate()->findOrFail($id) : new User;
            if ($id) {
                abort_unless(Access::canManageUser($request->user(), $account), 403);
            }
            $data = $validated;
            unset($data['current_password']);
            if (empty($data['password'])) {
                unset($data['password']);
            }
            if ($id && $account->email !== $data['email']) {
                $data['email_verified_at'] = null;
            }
            if ($id) {
                $data['remember_token'] = Str::random(60);
            }
            $account->forceFill($data)->save();
            if ($id) {
                DB::table('sessions')->where('user_id', $id)->where('id', '!=', $id === $request->user()->id ? $request->session()->getId() : '')->delete();
            }
            PortalController::audit($id ? 'Updated user account' : 'Created user', 'User #'.$account->id);
        });

        return redirect('/users')->with('success', $id ? 'User account updated.' : 'User account added.');
    }
}
