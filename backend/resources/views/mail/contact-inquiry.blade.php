<!doctype html>
<html lang="{{ $inquiry['language'] }}">
<head>
    <meta charset="utf-8">
    <title>{{ $inquiry['language'] === 'en' ? 'New website inquiry' : 'Neue Website-Anfrage' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #171717; line-height: 1.5;">
    <h1 style="font-size: 22px;">{{ $inquiry['language'] === 'en' ? 'New website inquiry' : 'Neue Website-Anfrage' }}</h1>
    <p><strong>{{ $inquiry['language'] === 'en' ? 'Name' : 'Name' }}:</strong> {{ $inquiry['name'] }}</p>
    <p><strong>{{ $inquiry['language'] === 'en' ? 'Email' : 'E-Mail' }}:</strong> {{ $inquiry['email'] }}</p>
    <p><strong>{{ $inquiry['language'] === 'en' ? 'Phone' : 'Telefon' }}:</strong> {{ $inquiry['phone'] ?: '—' }}</p>
    <p><strong>{{ $inquiry['language'] === 'en' ? 'Inquiry type' : 'Art der Anfrage' }}:</strong> {{ $inquiry['request_type'] }}</p>
    <p><strong>{{ $inquiry['language'] === 'en' ? 'Preferred date(s)' : 'Wunschtermin(e)' }}:</strong> {{ $inquiry['preferred_date'] }}</p>
    <p><strong>{{ $inquiry['language'] === 'en' ? 'Location' : 'Ort' }}:</strong> {{ $inquiry['location'] }}</p>
    <p><strong>{{ $inquiry['language'] === 'en' ? 'Message' : 'Nachricht' }}:</strong></p>
    <p style="white-space: pre-wrap;">{{ $inquiry['message'] }}</p>
</body>
</html>
