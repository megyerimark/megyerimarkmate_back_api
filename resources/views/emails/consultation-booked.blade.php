<!doctype html>
<html lang="hu"><head><meta charset="utf-8"><title>Konzultációs időpont</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6">
<h1>{{ $forClient ? 'Köszönöm a konzultációfoglalást!' : 'Új konzultációfoglalás' }}</h1>
<p>Azonosító: #{{ $booking->id }}</p>
<p>Ügyfél: {{ $booking->customer_name }}</p>
<p>Időpont (magyarországi idő): {{ $booking->slot->starts_at->timezone('Europe/Budapest')->format('Y. m. d. H:i') }}</p>
<p>Időtartam: {{ $booking->slot->duration_minutes }} perc</p>
@if(! $forClient)<p>E-mail: {{ $booking->email }} | Telefon: {{ $booking->phone ?: 'nincs megadva' }}</p><p>Megjegyzés: {{ $booking->notes ?: 'nincs' }}</p>@endif
<p>{{ $forClient ? 'A találkozó részleteiről külön egyeztetünk.' : 'Vedd fel a kapcsolatot az ügyféllel a találkozó részleteiért.' }}</p>
</body></html>
