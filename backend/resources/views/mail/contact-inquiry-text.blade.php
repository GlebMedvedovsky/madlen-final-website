{{ $inquiry['language'] === 'en' ? 'New website inquiry' : 'Neue Website-Anfrage' }}

Name: {{ $inquiry['name'] }}
{{ $inquiry['language'] === 'en' ? 'Email' : 'E-Mail' }}: {{ $inquiry['email'] }}
{{ $inquiry['language'] === 'en' ? 'Phone' : 'Telefon' }}: {{ $inquiry['phone'] ?: '—' }}
{{ $inquiry['language'] === 'en' ? 'Inquiry type' : 'Art der Anfrage' }}: {{ $inquiry['request_type'] }}
{{ $inquiry['language'] === 'en' ? 'Preferred date(s)' : 'Wunschtermin(e)' }}: {{ $inquiry['preferred_date'] }}
{{ $inquiry['language'] === 'en' ? 'Location' : 'Ort' }}: {{ $inquiry['location'] }}

{{ $inquiry['language'] === 'en' ? 'Message' : 'Nachricht' }}:
{{ $inquiry['message'] }}
