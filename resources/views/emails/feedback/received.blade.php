<x-mail::message>
# New Feedback Received

**User:** {{ $data['user_name'] }} (ID: {{ $data['user_id'] }})
**Email:** {{ $data['user_email'] }}
**Category:** {{ ucfirst($data['category']) }}

**Message:**
{{ $data['message'] }}

<x-mail::button :url="config('app.url')">
Open App
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
