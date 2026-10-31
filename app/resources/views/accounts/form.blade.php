@extends('layout')
@section('title', $account ? 'Edit ' . $account->name : 'Add User')
@section('subtitle', $account ? 'Update this organization account.' : 'Create an individual account for your
    organization.')
@section('actions')
    <a class="button secondary" href="/users">
        Back to users</a>
@endsection
@section('content')
    <form class="panel" method="post" action="{{ $account ? '/users/' . $account->id : '/users' }}">@csrf
        <div class="form-section">
            <h2>{{ $account ? 'Account details' : 'New user' }}</h2>
            <div class="form-grid">
                <div><label for="phone">SMS number (international format)</label><input id="phone" name="phone"
                        type="tel" placeholder="+233…" maxlength="20" value="{{ old('phone', $account?->phone) }}"></div>
                @foreach (['name' => ['Full name', 'text'], 'email' => ['Official email', 'email']] as $key => [$label, $type])
                    <div><label for="{{ $key }}">{{ $label }} *</label><input id="{{ $key }}"
                            type="{{ $type }}" name="{{ $key }}" required maxlength="180"
                            value="{{ old($key, $account?->$key ?? ($key === 'location' ? 'Accra' : '')) }}"></div>
                @endforeach
                @foreach (['department' => ['Department', \App\Models\User::DEPARTMENTS], 'location' => ['Office / location', \App\Models\User::OFFICES]] as $key => [$label, $options])
                    <div><label for="{{ $key }}">{{ $label }} *</label><select id="{{ $key }}"
                            name="{{ $key }}" required>
                            <option value="">Choose {{ strtolower($label) }}</option>
                            @foreach ($options as $option)
                                <option value="{{ $option }}" @selected(old($key, $account?->$key ?? '') === $option)>{{ $option }}
                                </option>
                            @endforeach
                        </select>
                        @if ($account && !in_array($account->$key, $options, true))
                            <p class="help">Current value: {{ $account->$key }}. Choose an option to update this account.
                            </p>
                        @endif
                    </div>
                @endforeach
                <div><label for="role">Role *</label><select id="role" name="role" required>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $account?->role ?? 'officer') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($account)
                    <div><label for="active">Account status *</label><select id="active" name="active">
                            <option value="1" @selected(old('active', $account->active) == '1')>Active</option>
                            <option value="0" @selected(old('active', $account->active) == '0')>Disabled</option>
                        </select></div>
                @endif
                <div><label
                        for="password">{{ $account ? 'New password (optional, 8+ characters)' : 'Password (8+ characters) *' }}</label><input
                        id="password" type="password" name="password" minlength="8" autocomplete="new-password"
                        @required(!$account)>
                    @if ($account)
                        <p class="help">Leave blank to keep the current password.</p>
                    @endif
                </div>
                <div><label for="password_confirmation">Confirm password</label><input id="password_confirmation"
                        type="password" name="password_confirmation" minlength="8" autocomplete="new-password"
                        @required(!$account)></div>
                @if ($account)
                    <div><label for="current_password">Your current password *</label><input id="current_password"
                            type="password" name="current_password" required autocomplete="current-password"></div>
                @endif
            </div>
        </div>
        <div class="form-footer"><a class="button secondary" href="/users">Cancel</a><button
                class="button">{{ $account ? 'Save user' : 'Add User' }}</button></div>
    </form>
@endsection
