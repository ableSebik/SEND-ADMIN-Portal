@if(auth()->user()->isSuperAdmin())
<form method="post" action="/records/{{ $module }}/{{ $record->id }}" data-confirm="Delete this {{ $label }}?">
    @csrf
    @method('DELETE')
    <input type="hidden" name="version" value="{{ $record->version }}">
    <button class="button secondary danger">Delete {{ $label }}</button>
</form>
@endif
