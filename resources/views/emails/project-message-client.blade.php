@extends('emails.layouts.aurora')
@section('title','Új üzenet a projektedhez')
@section('preheader','Üzenetet írtam a #'.$quote->id.' számú projekted oldalán.')
@section('eyebrow','PROJEKT / ÜZENET')
@section('headline','Új üzenetet kaptál.')
@section('content')
<x-mail.text>Kedves {{ $quote->customer_name }}! Üzenetet írtam a #{{ $quote->id }} számú projekted oldalán.</x-mail.text>
<x-mail.details title="AZ ÜZENET">
<tr><td colspan="2" style="padding:6px 20px 18px;color:#e5e9f7;font-size:14px;line-height:1.75;white-space:pre-line;word-break:break-word;">{{ \Illuminate\Support\Str::limit($projectMessage->body, 900) }}</td></tr>
</x-mail.details>
<x-mail.button :href="$portalUrl">Válasz a projektoldalon &rarr;</x-mail.button>
<x-mail.note>A projektoldalon minden üzenet, dokumentum és a projekt állapota egy helyen látható. A link személyes, ne továbbítsd. Ha több üzenetet küldök egymás után, 10 percenként legfeljebb egy értesítőt kapsz.</x-mail.note>
@endsection
