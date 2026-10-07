{{ $plainContent }}
@if(! empty($attachments))

Downloads:
@foreach($attachments as $attachment)
{{ $attachment['name'] ?? 'Download file' }}: {{ $attachment['url'] ?? '' }}
@endforeach
@endif
