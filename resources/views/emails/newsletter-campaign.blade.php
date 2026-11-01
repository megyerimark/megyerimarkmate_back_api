@extends('emails.layouts.aurora')
@section('title', $mailSubject)
@section('preheader', \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $body), 110))
@section('eyebrow','HÍRLEVÉL / MEGYERI MÁRK MÁTÉ')
@section('headline', $mailSubject)
@section('content')
@foreach ($blocks as $block)
@if ($block['type'] === 'list')
<x-mail.details>
@foreach ($block['items'] as $item)
<x-mail.row label="✦" label-width="28px" :first="$loop->first"><span style="font-weight:normal;">{{ $item }}</span></x-mail.row>
@endforeach
</x-mail.details>
@else
<p style="margin:0 0 16px;color:#c7d0ec;font-size:15px;line-height:1.75;white-space:pre-line;">{{ $block['text'] }}</p>
@endif
@endforeach
@if ($buttonText && $buttonUrl)
<x-mail.button :href="$buttonUrl">{{ $buttonText }} &rarr;</x-mail.button>
@endif
<x-mail.note>Azért kapod ezt a levelet, mert feliratkoztál a hírlevélre. <a href="{{ $unsubscribeUrl }}" style="color:#8fb0ff;">Leiratkozás egy kattintással</a>.</x-mail.note>
@endsection
