@extends('emails.layouts.aurora')
@section('title','Megújult a projekt-hozzáférésed')
@section('preheader','Új, személyes link a #'.$quote->id.' számú projektedhez.')
@section('eyebrow','BIZTONSÁGOS ÜGYFÉLPORTÁL')
@section('headline','Új hozzáférési linked érkezett.')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! A #{{ $quote->id }} számú projekted hozzáférése megújult, a korábbi link már nem használható.</x-mail.text>
<x-mail.button :href="$portalUrl">Új projektlink megnyitása &rarr;</x-mail.button>
<x-mail.note>A link hozzáférést ad az ajánlatodhoz, üzeneteidhez és dokumentumaidhoz. Ne oszd meg másokkal. Ha nem te kérted, kérlek, vedd fel velem a kapcsolatot.</x-mail.note>
@endsection
