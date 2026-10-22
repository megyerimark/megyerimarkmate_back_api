<!doctype html>
<html lang="hu">
<head><meta charset="utf-8"><title>Új ajánlatkérés</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6">
<h1>Új ajánlatkérés #{{ $quote->id }}</h1>
<p><strong>Érdeklődő:</strong> {{ $quote->customer_name }} ({{ $quote->email }})</p>
<p><strong>Vállalkozás:</strong> {{ $quote->company_name ?: 'Nincs megadva' }}</p>
<p><strong>Weboldaltípus:</strong> {{ $quote->website_type }}</p>
@if($quote->industry)<p><strong>Iparág:</strong> {{ $quote->industry }}</p>@endif
@if($quote->layout_style)<p><strong>Elrendezés:</strong> {{ $quote->layout_style }}</p>@endif
@if($quote->font_style)<p><strong>Betűvilág:</strong> {{ $quote->font_style }}</p>@endif
<p><strong>Költségkeret:</strong> {{ $quote->budget_range ?: 'Nincs megadva' }}</p>
<p><strong>Projektleírás:</strong></p>
<p style="white-space:pre-wrap">{{ $quote->project_description }}</p>
<p>A további adatokat a védett adminfelületen tekintsd meg.</p>
</body>
</html>
