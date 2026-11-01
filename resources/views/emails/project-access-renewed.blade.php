@extends('emails.layouts.aurora')
@section('title', mt('Megújult a projekt-hozzáférésed'))
@section('preheader', mt('Új, személyes link a #:id számú projektedhez.', ['id' => $quote->id]))
@section('eyebrow', mt('BIZTONSÁGOS ÜGYFÉLPORTÁL'))
@section('headline', mt('Új hozzáférési linked érkezett.'))
@section('content')
<x-mail.text>{{ mt('Kedves :name! A #:id számú projekted hozzáférése megújult, a korábbi link már nem használható.', ['name' => $quote->customer_name, 'id' => $quote->id]) }}</x-mail.text>
<x-mail.button :href="$portalUrl">{{ mt('Új projektlink megnyitása') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('A link hozzáférést ad az ajánlatodhoz, üzeneteidhez és dokumentumaidhoz. Ne oszd meg másokkal. Ha nem te kérted, kérlek, vedd fel velem a kapcsolatot.') }}</x-mail.note>
@endsection
