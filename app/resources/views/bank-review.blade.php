@if($a->action==='bank_change'&&in_array(auth()->user()->role,['super_admin','admin']))
@php $proposed=json_decode(\Illuminate\Support\Facades\Crypt::decryptString($payload['encrypted']),true);$current=\App\Models\VendorBankAccount::where('record_id',$a->record_id)->first()?->details; @endphp
<div class="notice"><strong>Proposed banking details</strong><p>{{ $proposed['bank_name'] }} · {{ $proposed['account_name'] }} · Account ending {{ substr($proposed['account_number'],-4) }} · {{ $proposed['currency'] }}</p><p>Current: {{ $current ? $current['bank_name'].' · Account ending '.substr($current['account_number'],-4) : 'No approved account' }}</p><p class="help">Verify account ownership independently before approving.</p></div>
@endif
