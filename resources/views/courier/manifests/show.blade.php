@extends('layouts.app')

@section('title', 'Manifest Details')

@section('content')
@php($manifestEditable = $manifest->status === 'draft')
@php($manifestInvoiceableCount = $manifest->items->filter(fn ($item) => optional($item->package)->customer_id && optional($item->package)->invoiceItems->filter(fn ($invoiceItem) => $invoiceItem->invoice && ! $invoiceItem->invoice->isLocked())->isEmpty())->count())
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $manifest->manifest_number }}</h1>
        <p class="text-muted mb-0">{{ $manifest->cargo_company ?: 'Manifest details' }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('courier.manifests.export', $manifest) }}" class="btn btn-outline-secondary"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
        <a href="{{ route('courier.manifests.print', $manifest) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('courier.manifests.download', $manifest) }}" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Download</a>
        @if($manifestInvoiceableCount > 0)
            <a href="{{ route('courier.manifests.bulk-invoices', $manifest) }}" class="btn btn-outline-success"><i class="fas fa-file-invoice-dollar me-1"></i>Generate Manifest Invoices</a>
        @endif
        @if($manifestEditable)
        <a href="{{ route('courier.manifests.edit', $manifest) }}" class="btn btn-primary">Edit</a>
            <form method="POST" action="{{ route('courier.manifests.submit', $manifest) }}">@csrf<button class="btn btn-outline-primary">Submit</button></form>
            @if($manifest->items->isEmpty())
                <form method="POST" action="{{ route('courier.manifests.destroy', $manifest) }}" onsubmit="return confirm('Delete this manifest?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
            @endif
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="panel p-4 h-100">
            <h2 class="h5 mb-3">Manifest Header</h2>
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted">Status</div><span class="badge badge-soft">{{ ucfirst($manifest->status) }}</span></div>
                <div class="col-md-4"><div class="text-muted">Departure</div>{{ optional($manifest->date_of_departure)->format('M d, Y') ?? '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Packages</div>{{ $manifest->items->count() }}</div>
                <div class="col-md-4"><div class="text-muted">Customs Office</div>{{ optional($manifest->customsOffice)->name ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Loading Port</div>{{ optional($manifest->loadingPort)->name ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Shipping Agent</div>{{ $manifest->shipping_agent_code ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Master Air Bill</div>{{ $manifest->master_air_bill ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Voyage</div>{{ $manifest->voyage_number ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Carrier Code</div>{{ $manifest->carrier_code ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Submitted By</div>{{ optional($manifest->submitter)->full_name ?: '-' }}</div>
                <div class="col-md-4"><div class="text-muted">Submitted At</div>{{ optional($manifest->submitted_at)->format('M d, Y h:i A') ?? '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-4 h-100">
            <h2 class="h5 mb-3">Exporter</h2>
            <div class="fw-bold">{{ $manifest->exporter_name ?: '-' }}</div>
            <div class="text-muted mt-2" style="white-space: pre-line">{{ $manifest->exporter_address ?: 'No exporter address.' }}</div>
        </div>
    </div>
</div>

@if($manifestEditable && $availablePackages->count())
    <div class="panel p-4 mb-4">
        <h2 class="h5 mb-3">Add Packages</h2>
        <form method="POST" action="{{ route('courier.manifests.packages.add', $manifest) }}" id="manifest-existing-add-form">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-12">
                    <label class="form-label">Scan HAWB or Tracking Number</label>
                    <input type="text" class="form-control" id="manifest-existing-scan" placeholder="Scan HAWB or carrier tracking number and press Enter">
                    <div class="small text-muted mt-1" id="manifest-existing-scan-status">Scanned matches are added to the manifest immediately.</div>
                </div>
                <div class="col-md-10">
                    <label class="form-label">Available Packages</label>
                    <select name="package_ids[]" class="form-select" multiple required size="4" id="manifest-existing-package-select">
                        @foreach($availablePackages as $package)
                            <option value="{{ $package->id }}" data-identifiers="{{ strtolower(trim($package->tracking_number.' '.$package->supplier_tracking)) }}">{{ $package->tracking_number }} @if($package->supplier_tracking)/ {{ $package->supplier_tracking }} @endif / {{ $package->mailbox ?: '-' }} / {{ ucfirst($package->freight_type) }} / {{ $package->measureDisplay() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100">Add</button>
                </div>
            </div>
        </form>
    </div>
@endif

<div class="panel">
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Manifest Packages</h2>
        <span class="text-muted small">{{ $manifest->items->count() }} packages</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Tracking</th><th>Customer</th><th>Freight</th><th>Weight / Volume</th><th>Status</th><th>Value</th><th></th></tr></thead>
            <tbody>
            @forelse($manifest->items as $item)
                @php($package = $item->package)
                <tr>
                    <td><strong>{{ $package->tracking_number }}</strong><br><small class="text-muted">{{ $package->supplier_tracking ?: ($package->merchant ?: $package->courier) }}</small></td>
                    <td>{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ $package->mailbox ?: '-' }}</small></td>
                    <td>{{ ucfirst($package->freight_type) }}</td>
                    <td>{{ $package->measureDisplay() }}</td>
                    <td><span class="badge" style="background: {{ optional($package->status)->color ?? '#64748b' }}">{{ optional($package->status)->name ?? 'No status' }}</span></td>
                    <td>{{ $package->manifestValueDisplay() }}</td>
                    <td class="text-end">
                        @if($manifestEditable)
                            <form method="POST" action="{{ route('courier.manifests.packages.remove', [$manifest, $package]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No packages attached to this manifest.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if($manifestEditable && $availablePackages->count())
<script>
    (function () {
        const form = document.getElementById('manifest-existing-add-form');
        const scan = document.getElementById('manifest-existing-scan');
        const status = document.getElementById('manifest-existing-scan-status');
        const select = document.getElementById('manifest-existing-package-select');

        if (!form || !scan || !status || !select) {
            return;
        }

        function normalizeScannedIdentifier(value) {
            return (value || '').trim().toLowerCase().replace(/^ol-/, '');
        }

        scan.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            const normalized = normalizeScannedIdentifier(scan.value);
            if (!normalized) {
                return;
            }
            const option = Array.from(select.options).find(function (item) {
                return (item.dataset.identifiers || '')
                    .split(/\s+/)
                    .some(function (identifier) {
                        return normalizeScannedIdentifier(identifier) === normalized;
                    });
            });
            if (!option) {
                status.textContent = 'No available package matched ' + scan.value + '.';
                if (typeof window.playAppActionSound === 'function') {
                    window.playAppActionSound('failed');
                }
                scan.value = '';
                return;
            }
            Array.from(select.options).forEach(function (item) {
                item.selected = false;
            });
            option.selected = true;
            if (typeof window.playAppActionSound === 'function') {
                window.playAppActionSound('check in');
            }
            form.submit();
        });
    })();
</script>
@endif
@endsection



