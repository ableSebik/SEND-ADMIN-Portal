@extends('layout')
@section('title', 'Operational history')
@section('subtitle', 'Maintenance, roadworthiness, insurance and mileage history.')
@section('content')
    <section class="panel">
        <form method="get" class="panel-body form-grid">
            <div><label for="vehicle-filter">Vehicle</label><select id="vehicle-filter" name="vehicle">
                    <option value="">All vehicles</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected($selected == $vehicle->id)>{{ $vehicle->name }} ·
                            {{ $vehicle->data['registration'] ?? $vehicle->reference }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button class="button">Filter history</button>
                <a class="button secondary" href="/operational-history">Clear
                </a>
            </div>
        </form>
    </section>
    @if (!config('fleet.email_enabled') || !config('fleet.sms_url'))
        <p class="help">Notification providers are awaiting setup. Delivery attempts appear below; no messages are sent
            through unconfigured channels.</p>
    @endif
    @if (\App\Services\Access::allows(auth()->user(), 'vehicles.write'))
        <section class="panel">
            <div class="panel-header">
                <h2>Record servicing or renewal</h2>
            </div>
            <form method="post" action="/operational-history" class="panel-body stack">@csrf<div class="form-grid">
                    <div><label for="operation-vehicle">Vehicle *</label><select id="operation-vehicle" name="vehicle_id"
                            required>
                            <option value="">Choose vehicle</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" data-version="{{ $vehicle->version }}"
                                    @selected(old('vehicle_id', $selected) == $vehicle->id)>{{ $vehicle->name }} ·
                                    {{ $vehicle->data['registration'] ?? $vehicle->reference }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" id="operation-version" name="version"
                            value="{{ $vehicles->firstWhere('id', old('vehicle_id', $selected))?->version }}">
                    </div>
                    <div><label for="kind">Type *</label><select id="kind" name="kind">
                            @foreach (\App\Services\FleetOperations::KINDS as $key => $label)
                                <option value="{{ $key }}" @selected(old('kind') === $key)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="completed_on">Serviced / renewed on *</label><input id="completed_on"
                            name="completed_on" type="date" required
                            value="{{ old('completed_on', now()->format('Y-m-d')) }}"></div>
                    <div><label for="due_on">Next service / renewal due *</label><input id="due_on" name="due_on"
                            type="date" required value="{{ old('due_on') }}"></div>
                    @foreach (['provider' => 'Garage / insurer / issuing authority', 'reference' => 'Policy / certificate / invoice reference'] as $key => $label)
                        <div><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}"
                                name="{{ $key }}" maxlength="180" value="{{ old($key) }}"></div>
                    @endforeach
                    <div><label for="cost">Cost (GHS)</label><input id="cost" name="cost" type="number"
                            step="0.01" min="0" value="{{ old('cost') }}"></div>
                    <div class="full"><label for="operation-notes">Notes</label>
                        <textarea id="operation-notes" name="notes" maxlength="3000">{{ old('notes') }}</textarea>
                    </div>
                    <div class="full"><label for="completion_notes">Work completion certification / details</label>
                        <textarea id="completion_notes" name="completion_notes" maxlength="3000">{{ old('completion_notes') }}</textarea>
                        <p class="help">For completed servicing, record the work performed and any final checks. Your
                            account is retained as the person recording completion.</p>
                    </div>
                    <div class="full"><label class="check-field"><input type="checkbox" name="release_vehicle"
                                value="1" @checked(old('release_vehicle'))> Servicing completed: release this vehicle from
                            Maintenance to Available.</label>
                        <p class="help">This applies only to servicing entries. Unavailable vehicles require a separate
                            status update.</p>
                    </div>
                </div><button class="button">Save renewal details</button></form>
        </section>
    @endif
    <section class="panel">
        <div class="panel-header">
            <h2>Fleet availability</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Mileage (km)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles->when($selected,fn($rows)=>$rows->where('id',$selected)) as $vehicle)
                        <tr>
                            <td><a class="link"
                                    href="/records/vehicles/{{ $vehicle->id }}">{{ $vehicle->name }}</a><small>{{ $vehicle->data['registration'] ?? $vehicle->reference }}</small>
                            </td>
                            <td>{{ number_format($vehicle->data['odometer'] ?? 0, 1) }}</td>
                            <td><span class="badge">{{ $vehicle->status }}</span></td>
                            <td>
                                @if (\App\Services\Access::allows(auth()->user(), 'vehicles.write'))
                                    <a class="button secondary" href="/vehicles/{{ $vehicle->id }}/service">Send for
                                        servicing</a> <a class="link" href="/records/vehicles/{{ $vehicle->id }}">Change
                                        status</a>
                                @endif
                            </td>
                    </tr>@empty<tr>
                            <td colspan="4">No vehicles yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header">
            <h2>Servicing & renewal history</h2>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Type</th>
                        <th>Completed</th>
                        <th>Due</th>
                        <th>Provider / reference</th>
                        <th>Cost (GHS)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($operations as $entry)
                        <tr>
                            <td>{{ $entry->vehicle_name }}@if ($entry->notes)
                                    <small>{{ $entry->notes }}</small>
                                    @endif @if ($entry->completion_notes)
                                        <small>Completion: {{ $entry->completion_notes }}</small>
                                    @endif
                            </td>
                            <td>{{ \App\Services\FleetOperations::KINDS[$entry->kind] }}</td>
                            <td>{{ $entry->completed_on }}</td>
                            <td>{{ $entry->due_on }}</td>
                            <td>{{ $entry->provider ?? '—' }}<small>{{ $entry->reference ?? '—' }}</small></td>
                            <td>{{ $entry->cost === null ? '—' : number_format($entry->cost, 2) }}</td>
                            <td><span
                                    class="badge">{{ $entry->superseded_at ? 'Renewed' : ($entry->due_on < now()->format('Y-m-d') ? 'Overdue' : 'Current') }}</span>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="7">No servicing or renewals recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $operations->links() }}</div>
    </section>
    <section class="panel">
        <div class="panel-header">
            <h2>Status & mileage events</h2><small>Latest 50</small>
        </div>
        <div class="panel-body">
            @forelse($events as $event)
                <article><strong>{{ $event->kind }} · {{ $event->vehicle_name }}</strong>
                    <p>{{ $event->details }}</p><small>{{ $event->actor }} · {{ $event->created_at }}</small>
                    @if ($event->trip_id)
                        <p><a class="link" href="/records/trips/{{ $event->trip_id }}">View trip</a></p>
                    @endif
                </article>
            <hr>@empty<p>No status changes or mileage events yet.</p>
            @endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-header">
            <h2>Servicing requests</h2><small>Latest 50</small>
        </div>
        <div class="panel-body">
            @forelse($serviceRequests as $service)
                <details>
                    <summary>{{ $service->vehicle_name }} · {{ $service->service_type }} · {{ $service->requested_on }} ·
                        {{ $service->completed_at ? 'Completed' : 'In servicing' }}</summary>
                    <dl class="detail-list">
                        <div>
                            <dt>Project / Admin</dt>
                            <dd>{{ $service->project }}</dd>
                        </div>
                        <div>
                            <dt>Mileage at request</dt>
                            <dd>{{ number_format($service->mileage, 1) }} km</dd>
                        </div>
                        <div>
                            <dt>Reason / problem identified</dt>
                            <dd>{{ $service->reason }}</dd>
                        </div>
                        <div>
                            <dt>Service provider</dt>
                            <dd>{{ $service->provider ?? 'Not yet assigned' }}</dd>
                        </div>
                        <div>
                            <dt>Procurement method</dt>
                            <dd>{{ $service->procurement_method }}</dd>
                        </div>
                        <div>
                            <dt>Diagnostic report available</dt>
                            <dd>{{ $service->diagnostic_report }}</dd>
                        </div>
                        <div>
                            <dt>Requested by</dt>
                            <dd>{{ $service->requester }}</dd>
                        </div>
                    </dl>
                </details>
            <hr>@empty<p>No servicing requests recorded yet.</p>
            @endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-header">
            <h2>Reminder deliveries</h2><small>Latest 30</small>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle / renewal</th>
                        <th>Recipient</th>
                        <th>Scheduled</th>
                        <th>Channel</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                        <tr>
                            <td>{{ $delivery->vehicle_name }}<small>{{ \App\Services\FleetOperations::KINDS[$delivery->kind] }}</small>
                            </td>
                            <td>{{ $delivery->recipient }}</td>
                            <td>{{ $delivery->scheduled_on }}</td>
                            <td>{{ ucfirst($delivery->channel) }}</td>
                            <td>{{ $delivery->status }}<small>{{ $delivery->error }}</small></td>
                    </tr>@empty<tr>
                            <td colspan="5">No scheduled attempts yet. Reminders run on the dates listed above.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
