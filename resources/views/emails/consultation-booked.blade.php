@extends('emails.layouts.aurora')
@section('title','Konzultációs időpont')
@section('eyebrow','KONZULTÁCIÓ / IDŐPONT')
@section('headline'){{ $forClient ? 'Sikeresen lefoglaltad az időpontot.' : 'Új konzultációfoglalás érkezett.' }}@endsection
@section('content')
<p style="color:#c7d0ec;line-height:1.8;">{{ $forClient ? 'Köszönöm a foglalásod!' : 'Új ügyfél foglalt időpontot.' }}</p>
<table role="presentation" style="background:#152344;border:1px solid #344578;border-radius:12px;" width="100%"><tr><td style="padding:20px;color:#cad4ed;font-size:14px;line-height:2.1;">
<strong style="color:white;">{{ $booking->customer_name }}</strong><br>
Időpont: <strong style="color:#fff;">{{ $booking->slot->starts_at->timezone('Europe/Budapest')->format('Y. m. d. H:i') }}</strong><br>
Időtartam: {{ $booking->slot->duration_minutes }} perc<br>Foglalási azonosító: #{{ $booking->id }}
@if(! $forClient)<br>E-mail: {{ $booking->email }}<br>Telefon: {{ $booking->phone ?: 'Nincs' }}<br>Megjegyzés: {{ $booking->notes ?: 'Nincs' }}@endif
</td></tr></table>
<p style="color:#c7d0ec;line-height:1.8;">{{ $forClient ? 'A találkozó további részleteit egyeztetjük.' : 'Vedd fel a kapcsolatot az ügyféllel a találkozó részleteiért.' }}</p>
@endsection