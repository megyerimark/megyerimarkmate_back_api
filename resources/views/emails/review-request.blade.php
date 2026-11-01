@extends('emails.layouts.aurora')
@section('title', mt('Mit gondolsz az új weboldaladról?'))
@section('preheader', mt('Egy rövid Google-értékelés sokat segít – köszönöm, ha megírod a véleményed.'))
@section('eyebrow', mt('KÖSZÖNÖM / VISSZAJELZÉS'))
@section('headline', mt('Remélem, örömöd telik benne.'))
@section('content')
<x-mail.text>{{ mt('Kedves :name! Néhány napja átadtam a projektedet – remélem, minden úgy működik, ahogy szeretted volna.', ['name' => $quote->customer_name]) }}</x-mail.text>
<x-mail.text>{{ mt('Az új ügyfelek nagy része ajánlásból és értékelésekből talál rám. Ha elégedett vagy a közös munkával, nagyon sokat segítene egy rövid Google-értékelés – egy-két mondat is elég.') }}</x-mail.text>
<x-mail.button :href="$reviewUrl">{{ mt('Értékelés írása a Google-on') }} &rarr;</x-mail.button>
<x-mail.note>{{ mt('Ha bármi nem úgy működik, ahogy kellene, vagy van ötleted a továbbfejlesztésre, egyszerűen válaszolj erre a levélre – szívesen segítek. Ezt a kérést csak egyszer küldöm el.') }}</x-mail.note>
@endsection
