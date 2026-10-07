<p>Hello {{ optional(optional($deliveryRequest->customer)->user)->full_name ?: 'Customer' }},</p>

<p>Your delivery request status has been updated by {{ $tenantName }}.</p>

<p>
    <strong>Request:</strong> {{ $deliveryRequest->request_number }}<br>
    <strong>Previous Status:</strong> {{ ucfirst(str_replace('_', ' ', $previousStatus)) }}<br>
    <strong>Current Status:</strong> {{ ucfirst(str_replace('_', ' ', $deliveryRequest->status)) }}<br>
    <strong>Assigned Delivery Staff:</strong> {{ optional($deliveryRequest->assignee)->full_name ?: optional($deliveryRequest->assignee)->email ?: '-' }}<br>
    <strong>Delivery Address:</strong> {{ $deliveryRequest->delivery_address ?: '-' }}<br>
    <strong>Preferred Date:</strong> {{ optional($deliveryRequest->preferred_date)->format('M d, Y') ?: '-' }}
</p>

<p>Thank you.</p>

@php($footer = \App\Models\TenantSetting::getValue($deliveryRequest->tenant_id, 'email_footer_html', ''))
@php($footerText = \App\Models\TenantSetting::getValue($deliveryRequest->tenant_id, 'email_footer_text', ''))
@if($footer)
    <hr>
    {!! $footer !!}
@elseif($footerText)
    <hr>
    <p>{{ $footerText }}</p>
@endif
@php($attachment = \App\Models\TenantSetting::getValue($deliveryRequest->tenant_id, 'email_attachment_url', ''))
@if($attachment)
    <p><a href="{{ $attachment }}">Download attachment</a></p>
@endif
