<p>Hello {{ optional(optional($package->customer)->user)->full_name ?: 'Customer' }},</p>

<p>Your package status has been updated by {{ $tenantName }}.</p>

<p>
    <strong>HAWB:</strong> {{ $package->tracking_number }}<br>
    <strong>Supplier Tracking:</strong> {{ $package->supplier_tracking ?: '-' }}<br>
    <strong>Status:</strong> {{ optional($log->status)->name ?: optional($package->status)->name }}<br>
    <strong>Pickup Location:</strong> {{ $log->location ?: '-' }}<br>
    <strong>Note:</strong> {{ $log->note ?: '-' }}
</p>

<p>Thank you.</p>

@php($footer = \App\Models\TenantSetting::getValue($package->tenant_id, 'email_footer_html', ''))
@php($footerText = \App\Models\TenantSetting::getValue($package->tenant_id, 'email_footer_text', ''))
@if($footer)
    <hr>
    {!! $footer !!}
@elseif($footerText)
    <hr>
    <p>{{ $footerText }}</p>
@endif
@php($attachment = \App\Models\TenantSetting::getValue($package->tenant_id, 'email_attachment_url', ''))
@if($attachment)
    <p><a href="{{ $attachment }}">Download attachment</a></p>
@endif
