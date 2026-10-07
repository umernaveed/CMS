<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $manifest->manifest_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 20px 0 8px; }
        p { color: #64748b; margin: 0 0 12px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 12px; }
        .label { color: #64748b; font-size: 11px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 11px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    @php
        $airPackages = $manifest->items->map(fn($item) => $item->package)->filter(fn($package) => $package && ! $package->isSeaFreight());
        $seaPackages = $manifest->items->map(fn($item) => $item->package)->filter(fn($package) => $package && $package->isSeaFreight());
        $totalWeightLb = (float) $airPackages->sum(fn($package) => (float) $package->weight);
        $totalWeightKg = $totalWeightLb * 0.45359237;
        $totalVolumeCuft = (float) $seaPackages->sum(fn($package) => (float) ($package->volume_cuft ?: $package->weight));
        $totalVolumeCbm = (float) $seaPackages->sum(fn($package) => (float) ($package->volume_cbm ?: (($package->volume_cuft ?: $package->weight) / \App\Models\WeightRate::CBM_TO_CUFT)));
        $totalValues = $manifest->items->map(fn($item) => $item->package)->filter()->groupBy(fn($package) => $package->manifestValueCurrency())->map(fn($packages) => (float) $packages->sum(fn($package) => $package->manifestValueAmount()));
        $totalValueDisplay = $totalValues->isEmpty() ? 'JMD 0.00' : $totalValues->map(fn($amount, $currency) => $currency.' '.number_format($amount, 2))->implode(' / ');
    @endphp
    <button class="no-print" onclick="window.print()">Print / Download PDF</button>
    <h1>{{ $manifest->manifest_number }}</h1>
    <p>{{ $manifest->cargo_company ?: 'Manifest' }}</p>
    <div class="grid">
        <div><div class="label">Status</div>{{ ucfirst($manifest->status) }}</div>
        <div><div class="label">Departure</div>{{ optional($manifest->date_of_departure)->format('M d, Y') ?? '-' }}</div>
        <div><div class="label">Customs</div>{{ optional($manifest->customsOffice)->name ?: '-' }}</div>
        <div><div class="label">Loading Port</div>{{ optional($manifest->loadingPort)->name ?: '-' }}</div>
        <div><div class="label">Master Air Bill</div>{{ $manifest->master_air_bill ?: '-' }}</div>
        <div><div class="label">Voyage</div>{{ $manifest->voyage_number ?: '-' }}</div>
        <div><div class="label">Carrier</div>{{ $manifest->carrier_code ?: '-' }}</div>
        <div><div class="label">Packages</div>{{ $manifest->items->count() }}</div>
        @if($airPackages->isNotEmpty())
            <div><div class="label">Total Air Weight</div>{{ number_format($totalWeightLb, 2) }} lb / {{ number_format($totalWeightKg, 2) }} kg</div>
        @endif
        @if($seaPackages->isNotEmpty())
            <div><div class="label">Total Sea Volume</div>{{ number_format($totalVolumeCuft, 2) }} cu.ft / {{ number_format($totalVolumeCbm, 3) }} CBM</div>
        @endif
        <div><div class="label">Total Value</div>{{ $totalValueDisplay }}</div>
    </div>
    <h2>Packages</h2>
    <table>
        <thead><tr><th>HAWB</th><th>Tracking</th><th>Customer</th><th>Mailbox</th><th>Freight</th><th>Weight / Volume</th><th>Status</th><th>Value</th></tr></thead>
        <tbody>
        @forelse($manifest->items as $item)
            @php($package = $item->package)
            <tr>
                <td>{{ $package->tracking_number }}</td>
                <td>{{ $package->supplier_tracking ?: '-' }}</td>
                <td>{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}</td>
                <td>{{ $package->mailbox ?: '-' }}</td>
                <td>{{ ucfirst($package->freight_type) }}</td>
                <td>{{ $package->measureDisplay() }}</td>
                <td>{{ optional($package->status)->name ?? 'No status' }}</td>
                <td>{{ $package->manifestValueDisplay() }}</td>
            </tr>
        @empty
            <tr><td colspan="8">No packages attached.</td></tr>
        @endforelse
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>

