@extends('emails.layouts.aurora')
@section('nohero', '1')
@section('title','Admin jelszó-visszaállítás')
@section('preheader','Jelszó-visszaállítási hivatkozás az adminfelülethez; 30 percig érvényes.')
@section('eyebrow','BIZTONSÁGI ÉRTESÍTÉS / ADMIN')
@section('headline','Állíts be új jelszót.')
@section('content')
<x-mail.text>{{ $adminName }}, jelszó-visszaállítási kérelmet kaptunk az adminfiókodhoz.</x-mail.text>
<x-mail.text>Az alábbi gombbal új jelszót állíthatsz be. A hivatkozás 30 percig használható, és egy alkalommal érvényes.</x-mail.text>
<x-mail.button :href="$resetUrl">Jelszó visszaállítása &rarr;</x-mail.button>
<x-mail.note>Ha nem te kérted a jelszóváltoztatást, nincs további teendőd. Ne továbbítsd ezt az üzenetet.</x-mail.note>
@endsection
