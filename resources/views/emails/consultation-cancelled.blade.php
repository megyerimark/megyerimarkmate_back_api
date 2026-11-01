@extends('emails.layouts.aurora')
@section('title','Konzultáció lemondva')
@section('preheader', ($forClient ? 'Lemondtad: ' : 'Lemondva: ').$start->translatedFormat('Y. F j.').' '.$start->format('H:i'))
@section('eyebrow', $forClient ? 'KONZULTÁCIÓ / LEMONDVA' : 'ADMIN / LEMONDÁS')
@section('headline'){{ $forClient ? 'A foglalásodat lemondtad.' : 'Lemondtak egy konzultációt.' }}@endsection
@section('content')
<x-mail.text>{{ $forClient ? 'Kedves '.$booking->customer_name.'! Visszaigazolom, hogy az alábbi konzultációs időpontot lemondtad. Az időpont felszabadult.' : $booking->customer_name.' lemondta a foglalását; az időpont újra foglalható.' }}</x-mail.text>
<div style="opacity:.7;">@include('emails.partials.date-card')</div>
@if ($forClient)
<x-mail.button :href="$bookUrl">Új időpont foglalása &rarr;</x-mail.button>
<x-mail.note>Ha nem te mondtad le, vagy kérdésed van, egyszerűen válaszolj erre a levélre.</x-mail.note>
@else
<x-mail.note>Erre a levélre válaszolva közvetlenül az ügyfélnek írsz ({{ $booking->email }}).</x-mail.note>
@endif
@endsection
