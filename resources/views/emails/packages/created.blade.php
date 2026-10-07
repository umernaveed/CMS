<p>Hello {{ optional(optional($package->customer)->user)->full_name ?: 'Customer' }},</p>

<p>{{ $tenantName }} has received a package for your mailbox.</p>

<p>
    <strong>Tracking:</strong> {{ $package->tracking_number }}<br>
    <strong>Mailbox:</strong> {{ $package->mailbox }}<br>
    <strong>Status:</strong> {{ optional($package->status)->name ?: 'Received' }}<br>
    <strong>{{ $package->measureLabel() }}:</strong> {{ $package->measureDisplay() }}
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
