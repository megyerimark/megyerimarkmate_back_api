<!doctype html>
<html lang="hu"><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;line-height:1.7">
<p>A(z) #{{ $quote->id }} számú ajánlatot {{ $quote->customer_name }} elfogadta.</p>
<p>Összeg: {{ number_format($quote->quoted_price_huf, 0, ',', ' ') }} Ft</p>
<p>Elfogadás időpontja: {{ $quote->proposal_accepted_at->format('Y. m. d. H:i') }}</p>
<p>A következő lépések egyeztetése szükséges.</p>
</body></html>
