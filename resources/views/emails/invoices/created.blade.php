<p>Hello {{ optional(optional($invoice->customer)->user)->full_name ?: 'Customer' }},</p>

<p>{{ $tenantName }} has created an invoice for your account.</p>

<p>
    <strong>Invoice:</strong> {{ $invoice->invoice_number }}<br>
    <strong>Mailbox:</strong> {{ $invoice->mailbox }}<br>
    <strong>Total:</strong> JMD {{ number_format((float) $invoice->total_jmd, 2) }}<br>
    <strong>Payment Status:</strong> {{ ucfirst($invoice->payment_status) }}<br>
    <strong>Due Date:</strong> {{ optional($invoice->due_date)->format('M d, Y') ?: '-' }}
</p>

@if($invoice->items->count())
    <p><strong>Packages:</strong></p>
    <ul>
        @foreach($invoice->items as $item)
            <li>{{ $item->tracking_number }} - JMD {{ number_format((float) $item->total_jmd, 2) }}</li>
        @endforeach
    </ul>
@endif

<p>Thank you.</p>

@php($footer = \App\Models\TenantSetting::getValue($invoice->tenant_id, 'email_footer_html', ''))
@php($footerText = \App\Models\TenantSetting::getValue($invoice->tenant_id, 'email_footer_text', ''))
@if($footer)
    <hr>
    {!! $footer !!}
@elseif($footerText)
    <hr>
    <p>{{ $footerText }}</p>
@endif
@php($attachment = \App\Models\TenantSetting::getValue($invoice->tenant_id, 'email_attachment_url', ''))
@if($attachment)
    <p><a href="{{ $attachment }}">Download attachment</a></p>
@endif
