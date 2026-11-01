@extends('emails.layouts.aurora')
@section('title', $forClient ? mt('Konzultáció lemondva') : 'Konzultáció lemondva')
@section('preheader', ($forClient ? mt('Lemondtad:') : 'Lemondva:').' '.\App\Support\MailText::longDate($start).' '.$start->format('H:i'))
@section('eyebrow', $forClient ? mt('KONZULTÁCIÓ / LEMONDVA') : 'ADMIN / LEMONDÁS')
@section('headline'){{ $forClient ? mt('A foglalásodat lemondtad.') : 'Lemondtak egy konzultációt.' }}@endsection
@section('content')
<x-mail.text>{{ $forClient ? mt('Kedves :name! Visszaigazolom, hogy az alábbi konzultációs időpontot lemondtad. Az időpont felszabadult.', ['name' => $booking->customer_name]) : $booking->customer_name.' lemondta a foglalását; az időpont újra foglalható.' }}</x-mail.text>
<div style="opacity:.7;">@include('emails.partials.date-card')</div>
@if ($forClient)
<x-mail.button :href="$bookUrl">{{ mt('Új időpont foglalása') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('Ha nem te mondtad le, vagy kérdésed van, egyszerűen válaszolj erre a levélre.') }}</x-mail.note>
@else
<x-mail.note>Erre a levélre válaszolva közvetlenül az ügyfélnek írsz ({{ $booking->email }}).</x-mail.note>
@endif
@endsection
