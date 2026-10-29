@php
    $variant = ($variant ?? 'full') === 'mark' ? 'mark' : 'full';
    $logo = $variant === 'mark' ? 'images/logo-mark.png' : 'images/logo.png';
@endphp
@if(file_exists(public_path($logo)))
<a class="brand brand-image brand-image--{{ $variant }}" href="/" aria-label="SEND Ghana home"><img src="{{ asset($logo) }}" alt="SEND Ghana"></a>
@else
<a class="brand" href="/">SEND<span><i></i>Ghana</span></a>
@endif
