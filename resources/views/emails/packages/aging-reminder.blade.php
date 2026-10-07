<p>Hello {{ optional(optional($customer)->user)->full_name ?: 'Customer' }},</p>

<p>{{ $tenantName }} is reminding you that the package{{ $packages->count() === 1 ? '' : 's' }} below {{ $packages->count() === 1 ? 'is' : 'are' }} available and still waiting to be collected or delivered.</p>

<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #e5e7eb;">
    <thead>
        <tr>
            <th align="left">Tracking</th>
            <th align="left">Status</th>
            <th align="left">Received</th>
            <th align="left">Age</th>
        </tr>
    </thead>
    <tbody>
        @foreach($packages as $package)
            <tr>
                <td>{{ $package->tracking_number }}</td>
                <td>{{ optional($package->status)->name ?: 'Available' }}</td>
                <td>{{ optional($package->date_received)->format('M d, Y') ?: '-' }}</td>
                <td>{{ $package->date_received ? $package->date_received->diffInDays(now()) : 0 }} days</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p>Please contact us if you need help arranging pickup or delivery.</p>

<p>Thank you.</p>

@php($footer = $tenantId ? \App\Models\TenantSetting::getValue($tenantId, 'email_footer_html', '') : '')
@php($footerText = $tenantId ? \App\Models\TenantSetting::getValue($tenantId, 'email_footer_text', '') : '')
@if($footer)
    <hr>
    {!! $footer !!}
@elseif($footerText)
    <hr>
    <p>{{ $footerText }}</p>
@endif
@php($attachment = $tenantId ? \App\Models\TenantSetting::getValue($tenantId, 'email_attachment_url', '') : '')
@if($attachment)
    <p><a href="{{ $attachment }}">Download attachment</a></p>
@endif
