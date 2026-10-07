<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Couriers</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        .no-print { margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 11px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        p { margin: 0 0 16px; color: #6b7280; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()">Print</button>
    <h1>Couriers</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead>
            <tr>
                <th>Company</th>
                <th>Courier Code</th>
                <th>Portal URL</th>
                <th>Customer Signup</th>
                <th>Subdomain</th>
                <th>Custom Domain</th>
                <th>Plan</th>
                <th>Status</th>
                <th>Billing</th>
                <th>Contact</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tenants as $tenant)
                @php($portalUrl = ($tenant->subdomain || $tenant->custom_domain) ? $tenant->portalUrl() : null)
                <tr>
                    <td>{{ $tenant->company_name }}</td>
                    <td>{{ $tenantCodes[$tenant->id] ?? $tenant->subdomain ?? $tenant->slug }}</td>
                    <td>{{ $portalUrl ?: '-' }}</td>
                    <td>{{ $portalUrl ? $portalUrl.'/register-customer' : '-' }}</td>
                    <td>{{ $tenant->subdomain ?: '-' }}</td>
                    <td>{{ $tenant->custom_domain ?: '-' }}</td>
                    <td>{{ optional(optional($tenant->subscription)->plan)->name ?: '-' }}</td>
                    <td>{{ ucfirst($tenant->status) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' + ', $tenant->billing_model ?? 'subscription')) }}</td>
                    <td>{{ $tenant->email ?: '-' }}<br>{{ $tenant->phone ?: '-' }}</td>
                    <td>{{ $tenant->city ?: '-' }}, {{ $tenant->parish ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="11">No couriers found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>