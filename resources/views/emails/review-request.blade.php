@extends('emails.layouts.aurora')
@section('title','Mit gondolsz az új weboldaladról?')
@section('preheader','Egy rövid Google-értékelés sokat segít – köszönöm, ha megírod a véleményed.')
@section('eyebrow','KÖSZÖNÖM / VISSZAJELZÉS')
@section('headline','Remélem, örömöd telik benne.')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! Néhány napja átadtam a projektedet – remélem, minden úgy működik, ahogy szeretted volna.</x-mail.text>
<x-mail.text>Az új ügyfelek nagy része ajánlásból és értékelésekből talál rám. Ha elégedett vagy a közös munkával, nagyon sokat segítene egy rövid Google-értékelés – egy-két mondat is elég.</x-mail.text>
<x-mail.button :href="$reviewUrl">Értékelés írása a Google-on &rarr;</x-mail.button>
<x-mail.note>Ha bármi nem úgy működik, ahogy kellene, vagy van ötleted a továbbfejlesztésre, egyszerűen válaszolj erre a levélre – szívesen segítek. Ezt a kérést csak egyszer küldöm el.</x-mail.note>
@endsection
