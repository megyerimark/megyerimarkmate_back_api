@extends('emails.layouts.aurora')
@section('title','Új ügyfélüzenet')
@section('preheader', $quote->customer_name.' üzenetet írt a #'.$quote->id.' számú projektnél.')
@section('eyebrow','ADMIN / ÜZENET')
@section('headline','Üzenetet kaptál.')
@section('content')
<x-mail.text>{{ $quote->customer_name }} üzenetet írt a #{{ $quote->id }} számú projekt oldalán.</x-mail.text>
<x-mail.details title="AZ ÜZENET">
<tr><td colspan="2" style="padding:6px 20px 18px;color:#e5e9f7;font-size:14px;line-height:1.75;white-space:pre-line;word-break:break-word;">{{ \Illuminate\Support\Str::limit($projectMessage->body, 600) }}</td></tr>
</x-mail.details>
<x-mail.button :href="$adminUrl">Megnyitás az adminban &rarr;</x-mail.button>
<x-mail.note>Ha az ügyfél több üzenetet küld egymás után, 10 percenként legfeljebb egy értesítőt kapsz; az összes üzenet az adminban olvasható.</x-mail.note>
@endsection
