@extends('layouts.app')

@section('title', 'Logistics Manifest')
@section('page_title', 'Manifest '.$manifest->manifest_number)
@section('breadcrumb', 'Logistics / '.$manifest->manifest_number)

@section('content')
@php
    $manifestIsSea = optional($manifest->shipment)->shipment_type === 'sea';
    $formatMeasure = function ($value, $unit) {
        $measure = (float) $value;
        if ($unit === 'cbm') {
            return number_format($measure * 35.3147, 2).' cu.ft / '.number_format($measure, 3).' CBM';
        }
        if ($unit === 'cuft') {
            return number_format($measure, 2).' cu.ft / '.number_format($measure / 35.3147, 3).' CBM';
        }

        $lb = $unit === 'kg' ? $measure * 2.20462262 : $measure;
        $kg = $unit === 'lb' ? $measure * 0.45359237 : $measure;

        return number_format($lb, 2).' lb / '.number_format($kg, 2).' kg';
    };
@endphp
<style>
    .logistics-tool-panel summary {
        align-items: center;
        cursor: pointer;
        display: flex;
        font-size: 1rem;
        font-weight: 800;
        justify-content: space-between;
        list-style: none;
    }
    .logistics-tool-panel summary::-webkit-details-marker { display: none; }
    .logistics-tool-panel summary::after {
        content: "\f078";
        font-family: "Font Awesome 6 Free";
        font-size: .8rem;
        font-weight: 900;
        transition: transform .16s ease;
    }
    .logistics-tool-panel[open] summary::after { transform: rotate(180deg); }
    .logistics-tool-body { margin-top: 1rem; }
    .logistics-subtoolbar {
        display: flex;
        gap: .5rem;
        overflow-x: auto;
        padding: .25rem 0 .75rem;
        scrollbar-width: thin;
    }
    .logistics-subtoolbar .btn { flex: 0 0 auto; }
    [data-logistics-pane][hidden] { display: none !important; }
</style>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">{{ $manifest->manifest_number }}</h1>
        <p class="text-muted mb-0">{{ optional($manifest->tenant)->company_name }} / {{ ucfirst(str_replace('_', ' ', $manifest->status)) }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('superadmin.logistics.manifests') }}" class="btn btn-outline-secondary">Back to Manifests</a>
        @if(! $manifest->invoices->count())
            <form method="POST" action="{{ route('superadmin.logistics.manifests.destroy', $manifest) }}" data-confirm="Delete this manifest and its open packages?">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
            </form>
        @endif
    </div>
</div>

<div class="logistics-subtoolbar mb-3" role="tablist" aria-label="Manifest sections">
    <button type="button" class="btn btn-primary" data-logistics-tab="overview"><i class="fas fa-chart-line me-1"></i>Overview</button>
    <button type="button" class="btn btn-outline-secondary" data-logistics-tab="operations"><i class="fas fa-barcode me-1"></i>Operations</button>
    <button type="button" class="btn btn-outline-secondary" data-logistics-tab="packages"><i class="fas fa-boxes-stacked me-1"></i>Packages <span class="badge bg-light text-dark ms-1">{{ $manifest->packages->count() }}</span></button>
    <button type="button" class="btn btn-outline-secondary" data-logistics-tab="invoices"><i class="fas fa-file-invoice-dollar me-1"></i>Invoices <span class="badge bg-light text-dark ms-1">{{ $manifest->invoices->count() }}</span></button>
</div>

<div class="panel p-4 mb-4" data-logistics-pane="overview">
    <h2 class="h5 mb-3">Manifest Details</h2>
    <form method="POST" action="{{ route('superadmin.logistics.manifests.update', $manifest) }}" class="row g-3">
        @csrf
        @method('PUT')
        <div class="col-md-3">
            <label class="form-label">Courier</label>
            <select name="tenant_id" class="form-select" required>
                @foreach($couriers as $courier)
                    <option value="{{ $courier->id }}" @selected($courier->id == $manifest->tenant_id)>{{ $courier->company_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Manifest #</label><input name="manifest_number" class="form-control" value="{{ $manifest->manifest_number }}"></div>
        <div class="col-md-3"><label class="form-label">Warehouse Reference</label><input name="warehouse_reference" class="form-control" value="{{ $manifest->warehouse_reference }}"></div>
        <div class="col-md-3"><label class="form-label">Cargo Company</label><input name="cargo_company" class="form-control" value="{{ $manifest->cargo_company }}"></div>
        <div class="col-md-3"><label class="form-label">Received At</label><input type="datetime-local" name="received_at" class="form-control" value="{{ optional($manifest->received_at)->format('Y-m-d\TH:i') }}"></div>
        <div class="col-md-3 d-flex align-items-end">
            <label class="form-check mb-2">
                <input type="hidden" name="require_checkout_scan" value="0">
                <input class="form-check-input" type="checkbox" name="require_checkout_scan" value="1" @checked($manifest->require_checkout_scan)>
                <span class="form-check-label">Require checkout scan</span>
            </label>
        </div>
        <div class="col-md-6"><label class="form-label">Notes</label><input name="notes" class="form-control" value="{{ $manifest->notes }}"></div>
        <div class="col-12 text-end"><button class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Save Manifest</button></div>
    </form>
</div>

<div class="row g-4 mb-4" data-logistics-pane="operations" hidden>
    <div class="col-xl-4">
        <details class="panel p-4 h-100 logistics-tool-panel" open>
            <summary>Add Packages</summary>
            <div class="logistics-tool-body">
            @if($manifest->status === 'checked_out')
                <div class="alert alert-secondary mb-0"><i class="fas fa-lock me-1"></i>This manifest is checked out. Package changes are disabled.</div>
            @else
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="{{ route('superadmin.logistics.packages.template', $manifest) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>CSV Template</a>
                    <a href="{{ route('superadmin.logistics.packages.export', $manifest) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-export me-1"></i>Export Packages</a>
                </div>
                <form method="POST" action="{{ route('superadmin.logistics.packages.manifest-bulk-upload', $manifest) }}" enctype="multipart/form-data" class="mb-4">
                    @csrf
                    <label class="form-label">Bulk upload CSV</label>
                    <input type="file" name="file" class="form-control" accept=".csv,text/csv,text/plain" required>
                    <div class="form-text mb-3">CSV columns: tracking_number, weight, weight_unit, description. Use the template for 2000+ rows.</div>
                    <button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload Package CSV</button>
                </form>
                @if(session('manifest_bulk_upload_skips'))
                    <div class="alert alert-warning small">
                        <div class="fw-bold mb-1">Skipped rows</div>
                        @foreach(session('manifest_bulk_upload_skips') as $skip)
                            <div>{{ $skip }}</div>
                        @endforeach
                    </div>
                @endif
                <hr>
                <form method="POST" action="{{ route('superadmin.logistics.packages.store', $manifest) }}">
                    @csrf
                    <label class="form-label">Quick manual rows</label>
                    <textarea name="packages" rows="8" class="form-control" placeholder="ABC123, 10, lb, Box of parts&#10;XYZ789, 4.5, kg, Carton"></textarea>
                    <div class="form-text mb-3">Rates are pulled automatically from the configured lb/kg ranges and charged as one flat amount per package.</div>
                    <button class="btn btn-primary">Add Package Rows</button>
                </form>
            @endif
            </div>
        </details>
    </div>
    <div class="col-xl-4">
        <details class="panel p-4 h-100 logistics-tool-panel">
            <summary>Wharf Check-in Scan</summary>
            <div class="logistics-tool-body">
            <form method="POST" action="{{ route('superadmin.logistics.check-in', $manifest) }}">
                @csrf
                <label class="form-label">Tracking / HAWB Barcodes</label>
                <textarea name="tracking_numbers" rows="8" class="form-control" placeholder="Scan or enter one per line"></textarea>
                <div class="form-text mb-3">Used when shipment is cleared at the wharf on behalf of co-loaders.</div>
                <button class="btn btn-primary" data-action-sound="check in"><i class="fas fa-barcode me-1"></i>Check In</button>
            </form>
            </div>
        </details>
    </div>
    <div class="col-xl-4">
        <details class="panel p-4 h-100 logistics-tool-panel">
            <summary>Paid Cargo Check-out</summary>
            <div class="logistics-tool-body">
            <form method="POST" action="{{ route('superadmin.logistics.check-out', $manifest) }}">
                @csrf
                <label class="form-label">Tracking / HAWB Barcodes</label>
                <textarea name="tracking_numbers" rows="8" class="form-control" placeholder="Scan paid packages before handover"></textarea>
                <div class="form-text mb-3">Only packages on paid logistics invoices can be checked out.</div>
                <button class="btn btn-success" data-action-sound="check out"><i class="fas fa-truck-ramp-box me-1"></i>Check Out Paid Cargo</button>
            </form>
            </div>
        </details>
    </div>
</div>

<form id="logisticsInvoiceForm" method="POST" action="{{ route('superadmin.logistics.invoices.store', $manifest) }}" class="d-none">@csrf</form>

<div class="panel mb-4" data-logistics-pane="packages" hidden>
    @if($manifest->status !== 'checked_out')
        <div class="p-3 border-bottom bg-light">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <form method="POST" action="{{ route('superadmin.logistics.packages.store', $manifest) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-9">
                            <label class="form-label fw-semibold">Add / Scan Package To Open Manifest</label>
                            <textarea name="packages" rows="2" class="form-control" placeholder="Scan HAWB/tracking here. Optional row format: TRACKING, 10, lb, Description" required></textarea>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100"><i class="fas fa-barcode me-1"></i>Add / Scan</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <form method="POST" action="{{ route('superadmin.logistics.packages.manifest-bulk-upload', $manifest) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Bulk Upload Packages</label>
                            <input type="file" name="file" class="form-control" accept=".csv,text/csv,text/plain" required>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-primary w-100"><i class="fas fa-upload me-1"></i>Upload</button>
                        </div>
                    </form>
                    <div class="mt-2"><a href="{{ route('superadmin.logistics.packages.template', $manifest) }}" class="small">Download CSV template</a></div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-secondary m-3 mb-0"><i class="fas fa-lock me-1"></i>This manifest is checked out. Package changes are disabled.</div>
    @endif
    <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h5 mb-0">Open Manifest Packages</h2>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-check-all="#logisticsInvoiceForm">Check All</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-uncheck-all="#logisticsInvoiceForm">Uncheck All</button>
            <select name="billing_scope" form="logisticsInvoiceForm" class="form-select form-select-sm" style="width: 170px;">
                <option value="manifest">Bill all manifest packages</option>
                <option value="packages">Bill selected packages</option>
            </select>
            <button form="logisticsInvoiceForm" class="btn btn-sm btn-primary">Create Invoice</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th style="width:40px;"></th><th>Tracking</th><th>Description</th><th>Measure</th><th>Warehouse Fee</th><th>Service Fee</th><th>Freight Fee</th><th>USD/JMD</th><th>Total</th><th>Check-in</th><th>Check-out</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($openPackages as $package)
                @php
                    $locked = filled($package->checked_out_at);
                    $packageForm = 'logisticsPackageUpdate'.$package->id;
                @endphp
                <tr>
                    <td><input type="checkbox" form="logisticsInvoiceForm" name="package_ids[]" value="{{ $package->id }}" class="form-check-input" @disabled($locked)></td>
                    <td>
                        @if($locked)
                            <strong>{{ $package->tracking_number }}</strong>
                        @else
                            <form id="{{ $packageForm }}" method="POST" action="{{ route('superadmin.logistics.packages.update', [$manifest, $package]) }}">@csrf @method('PUT')</form>
                            <input name="tracking_number" form="{{ $packageForm }}" class="form-control form-control-sm" value="{{ $package->tracking_number }}" required>
                        @endif
                    </td>
                    <td>
                        @if($locked)
                            {{ $package->description ?: '-' }}
                        @else
                            <input name="description" form="{{ $packageForm }}" class="form-control form-control-sm" value="{{ $package->description }}">
                        @endif
                    </td>
                    <td>
                        @if($locked)
                            {{ method_exists($package, 'measureDisplay') ? $package->measureDisplay() : $formatMeasure($package->weight, $package->weight_unit) }}
                        @else
                            <div class="d-flex gap-1">
                                <input type="number" step="0.01" min="0" name="weight" form="{{ $packageForm }}" class="form-control form-control-sm" value="{{ $package->weight }}" style="min-width: 90px;" required>
                                <select name="weight_unit" form="{{ $packageForm }}" class="form-select form-select-sm" style="width: 76px;">
                                    @if($manifestIsSea)
                                        <option value="cuft" @selected($package->weight_unit === 'cuft')>cu.ft</option>
                                        <option value="cbm" @selected($package->weight_unit === 'cbm')>CBM</option>
                                    @else
                                        <option value="lb" @selected($package->weight_unit === 'lb')>lb</option>
                                        <option value="kg" @selected($package->weight_unit === 'kg')>kg</option>
                                    @endif
                                </select>
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($locked)
                            US${{ number_format((float) ($package->warehouse_fee ?? $package->rate), 2) }}
                        @else
                            <input name="warehouse_fee" form="{{ $packageForm }}" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $package->warehouse_fee ?? $package->rate }}" title="Flat warehouse range fee for this package">
                        @endif
                    </td>
                    <td>
                        @if($locked)
                            <div>US${{ number_format((float) ($package->service_fee ?? 0), 2) }}</div>
                            @if($package->service_fee_type)
                                <small class="text-muted">{{ ucfirst($package->service_fee_type) }} {{ number_format((float) $package->service_fee_value, 2) }}{{ $package->service_fee_type === 'percentage' ? '%' : '' }}</small>
                            @endif
                        @else
                            <input name="service_fee" form="{{ $packageForm }}" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $package->service_fee ?? 0 }}" title="Service fee USD for this package">
                        @endif
                    </td>
                    <td>
                        @if($locked)
                            <div>US${{ number_format((float) ($package->freight_charge ?? 0), 2) }}</div>
                            <small class="text-muted">{{ number_format((float) ($package->freight_weight ?: $package->weight), 4) }} {{ $package->freight_unit ?: $package->weight_unit }} x US${{ number_format((float) ($package->freight_rate ?? 0), 2) }}</small>
                        @else
                            <div class="d-flex gap-1 flex-wrap">
                                <input name="freight_weight" form="{{ $packageForm }}" type="number" step="0.0001" min="0" class="form-control form-control-sm" value="{{ $package->freight_weight ?: $package->weight }}" style="width:92px;" title="Freight billable weight">
                                <select name="freight_unit" form="{{ $packageForm }}" class="form-select form-select-sm" style="width:82px;"><option value="kg" @selected(($package->freight_unit ?: $package->weight_unit) === 'kg')>kg</option><option value="lb" @selected(($package->freight_unit ?: $package->weight_unit) === 'lb')>lb</option><option value="cuft" @selected(($package->freight_unit ?: $package->weight_unit) === 'cuft')>cu.ft</option><option value="cbm" @selected(($package->freight_unit ?: $package->weight_unit) === 'cbm')>CBM</option></select>
                                <input name="freight_rate" form="{{ $packageForm }}" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $package->freight_rate ?? 0 }}" style="width:86px;" title="Freight rate USD per unit">
                                <input name="freight_charge" form="{{ $packageForm }}" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $package->freight_charge ?? 0 }}" style="width:96px;" title="Freight fee USD for this package">
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($locked)
                            {{ number_format((float) $package->exchange_rate_jmd, 4) }}
                        @else
                            <input name="exchange_rate_jmd" form="{{ $packageForm }}" type="number" step="0.0001" min="0" class="form-control form-control-sm" value="{{ $package->exchange_rate_jmd ?? 0 }}" style="width:105px;">
                        @endif
                    </td>
                    <td>
                        @if((float) ($package->amount_usd ?? 0) > 0)
                            <div>US${{ number_format((float) $package->amount_usd, 2) }}</div>
                            <small class="text-muted">USD/JMD {{ number_format((float) $package->exchange_rate_jmd, 4) }}</small><br>
                        @endif
                        <strong>JMD {{ number_format($package->amount, 2) }}</strong>
                    </td>
                    <td>{{ optional($package->checked_in_at)->format('M d, Y h:i A') ?: '-' }}</td>
                    <td>
                        @if($locked)
                            <span class="badge bg-success">Checked Out</span><br>
                            <small>{{ optional($package->checked_out_at)->format('M d, Y h:i A') }}</small>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-end">
                        @if($locked)
                            <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Locked</span>
                        @else
                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                <button form="{{ $packageForm }}" class="btn btn-sm btn-outline-primary" data-action-sound="save"><i class="fas fa-save me-1"></i>Save</button>
                                <form method="POST" action="{{ route('superadmin.logistics.packages.reassign', [$manifest, $package]) }}" class="d-flex gap-1">
                                    @csrf @method('PATCH')
                                    <select name="tenant_id" class="form-select form-select-sm" style="width: 150px;">
                                        @foreach($couriers as $courier)
                                            <option value="{{ $courier->id }}" @selected($courier->id == $package->tenant_id)>{{ $courier->company_name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-secondary">Reassign</button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.logistics.packages.unknown-room', [$manifest, $package]) }}" data-confirm="Move this package to the InHouse Unknown Room?">@csrf<button class="btn btn-sm btn-outline-warning">Unknown Room</button></form>
                                <form method="POST" action="{{ route('superadmin.logistics.packages.destroy', [$manifest, $package]) }}" data-confirm="Delete this package from the manifest?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-center text-muted py-4">No open packages left to bill. Check the invoiced packages section below.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 text-muted small">Select packages before choosing "Bill selected packages". Invoiced packages are removed from this open list to prevent duplicate billing.</div>
</div>

<div class="panel mb-4" data-logistics-pane="packages" hidden>
    <div class="p-3 border-bottom"><h2 class="h5 mb-0">Invoiced Packages</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Tracking</th><th>Invoice</th><th>Measure</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($invoicedPackages as $package)
                @php $latestItem = $package->invoiceItems->sortByDesc('created_at')->first(); @endphp
                <tr>
                    <td>{{ $package->tracking_number }}</td>
                    <td>{{ optional(optional($latestItem)->invoice)->invoice_number ?: '-' }}</td>
                    <td>{{ method_exists($package, 'measureDisplay') ? $package->measureDisplay() : $formatMeasure($package->weight, $package->weight_unit) }}</td>
                    <td>
                        <div>US${{ number_format((float) $package->amount_usd, 2) }}</div>
                        <strong>JMD {{ number_format((float) $package->amount, 2) }}</strong>
                    </td>
                    <td>{{ optional(optional($latestItem)->invoice)->status ? ucfirst(optional(optional($latestItem)->invoice)->status) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No invoiced packages yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel" data-logistics-pane="invoices" hidden>
    <div class="p-3 border-bottom"><h2 class="h5 mb-0">Logistics Invoices</h2></div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Invoice</th><th>Scope</th><th>Items</th><th>Status</th><th>Files</th><th>Total</th><th>Balance</th><th></th></tr></thead>
            <tbody>@forelse($manifest->invoices as $invoice)<tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ ucfirst($invoice->billing_scope) }}</td>
                <td>{{ $invoice->items->count() }}</td>
                <td><span class="badge {{ $invoice->status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ ucfirst($invoice->status) }}</span></td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        @if($invoice->supplier_invoice_path)
                            <a href="{{ route('superadmin.logistics.invoices.supplier-invoice.view', $invoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Supplier File</a>
                        @endif
                        @if($invoice->receipt_path)
                            <a href="{{ route('superadmin.logistics.invoices.receipt.view', $invoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Payment Proof</a>
                        @endif
                        @if(! $invoice->supplier_invoice_path && ! $invoice->receipt_path)
                            <span class="text-muted small">-</span>
                        @endif
                    </div>
                </td>
                <td>JMD {{ number_format($invoice->total, 2) }}</td>
                <td>JMD {{ number_format($invoice->balance_jmd, 2) }}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#invoiceItems{{ $invoice->id }}">Packages</button>
                    <a href="{{ route('superadmin.logistics.invoices.download', $invoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Full Invoice</a>
                    <a href="{{ route('superadmin.logistics.invoices.thermal', $invoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank">View Thermal</a>
                    <a href="{{ route('superadmin.logistics.invoices.thermal.download', $invoice) }}" class="btn btn-sm btn-outline-secondary">Download Thermal</a>
                    @if($invoice->status !== 'paid')<form method="POST" action="{{ route('superadmin.logistics.invoices.paid', $invoice) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Mark Paid</button></form>@endif
                </td>
            </tr>
            <tr class="collapse" id="invoiceItems{{ $invoice->id }}">
                <td colspan="8">
                    @if($invoice->status !== 'void')
                        <form method="POST" action="{{ route('superadmin.logistics.invoices.update', $invoice) }}" class="row g-2 align-items-end mb-3">
                            @csrf @method('PUT')
                            <div class="col-md-2"><label class="form-label small">Discount JMD</label><input name="discount_jmd" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $invoice->discount_jmd ?? 0 }}"></div>
                            <div class="col-md-7"><label class="form-label small">Notes</label><input name="notes" class="form-control form-control-sm" value="{{ $invoice->notes }}"></div>
                            <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Update Invoice</button></div>
                        </form>
                        <form method="POST" action="{{ route('superadmin.logistics.invoices.items.store', $invoice) }}" class="row g-2 align-items-end mb-3">
                            @csrf
                            <div class="col-md-9"><label class="form-label small">Add Package by Tracking / HAWB</label><input name="tracking_numbers" class="form-control form-control-sm" placeholder="Scan or enter tracking numbers separated by commas"></div>
                            <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary w-100">Add To Invoice</button></div>
                        </form>
                        <div class="d-flex gap-2 mb-3 flex-wrap">
                            <form method="POST" action="{{ route('superadmin.logistics.invoices.payments.store', $invoice) }}" class="d-flex gap-2 flex-wrap">
                                @csrf
                                <input type="number" step="0.01" min="0.01" name="amount_jmd" class="form-control form-control-sm" placeholder="Payment JMD" style="width:140px;" value="{{ $invoice->balance_jmd }}">
                                <select name="payment_method" class="form-select form-select-sm" style="width:150px;"><option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option><option value="card">Credit Card</option><option value="debit_card">Debit Card</option></select>
                                <input name="reference_number" class="form-control form-control-sm" placeholder="Reference" style="width:150px;">
                                <button class="btn btn-sm btn-success">Add Payment</button>
                            </form>
                            <form method="POST" action="{{ route('superadmin.logistics.invoices.apply-credit', $invoice) }}">@csrf<button class="btn btn-sm btn-outline-success">Apply Courier Credit</button></form>
                            <form method="POST" action="{{ route('superadmin.logistics.invoices.send', $invoice) }}">@csrf<button class="btn btn-sm btn-outline-primary">Send Email</button></form>
                            <form method="POST" action="{{ route('superadmin.logistics.invoices.receipt', $invoice) }}" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                                @csrf
                                <input type="file" name="receipt" class="form-control form-control-sm" style="max-width:320px;" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                <button class="btn btn-sm btn-outline-secondary">Upload Receipt</button>
                            </form>
                            <form method="POST" action="{{ route('superadmin.logistics.invoices.void', $invoice) }}" data-confirm="Void this logistics invoice? It will be permanently locked.">@csrf<button class="btn btn-sm btn-outline-danger">Void</button></form>
                        </div>
                    @endif
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Tracking</th><th>HAWB</th><th>Measure</th><th>Warehouse</th><th>Service</th><th>Freight</th><th class="text-end">Total</th><th></th></tr></thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->displayTrackingNumber() }}</td>
                                    <td>{{ $item->hawbNumber() ?: '-' }}</td>
                                    <td>{{ $formatMeasure($item->weight, $item->weight_unit) }}</td>
                                    <td>US${{ number_format((float) $item->warehouse_fee, 2) }}</td>
                                    <td>US${{ number_format((float) $item->service_fee, 2) }}</td>
                                    <td>US${{ number_format((float) $item->freight_charge, 2) }}</td>
                                    <td class="text-end">JMD {{ number_format((float) $item->amount, 2) }}</td>
                                    <td class="text-end">@if($invoice->status !== 'void')<form method="POST" action="{{ route('superadmin.logistics.invoices.items.destroy', [$invoice, $item]) }}" data-confirm="Remove this package from the invoice?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($invoice->payments->isNotEmpty())
                        <div class="mt-3"><strong>Payments</strong></div>
                        <table class="table table-sm">
                            <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>@foreach($invoice->payments as $payment)<tr><td>{{ optional($payment->created_at)->format('M d, Y') }}</td><td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td><td>{{ $payment->reference_number ?: '-' }}</td><td class="text-end">JMD {{ number_format((float) $payment->amount_jmd, 2) }}</td></tr>@endforeach</tbody>
                        </table>
                    @endif
                </td>
            </tr>@empty<tr><td colspan="7" class="text-muted py-3">No logistics invoices yet.</td></tr>@endforelse</tbody>
        </table>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const logisticsTabs = Array.from(document.querySelectorAll('[data-logistics-tab]'));
    const logisticsPanes = Array.from(document.querySelectorAll('[data-logistics-pane]'));
    const activateLogisticsTab = function (name) {
        logisticsTabs.forEach(function (button) {
            const active = button.dataset.logisticsTab === name;
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-outline-secondary', !active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        logisticsPanes.forEach(function (pane) {
            pane.hidden = pane.dataset.logisticsPane !== name;
        });
        if (window.history && history.replaceState) {
            history.replaceState(null, '', '#'+name);
        }
    };
    logisticsTabs.forEach(function (button) {
        button.addEventListener('click', function () {
            activateLogisticsTab(button.dataset.logisticsTab);
        });
    });
    const requestedTab = window.location.hash.replace('#', '');
    activateLogisticsTab(logisticsTabs.some(button => button.dataset.logisticsTab === requestedTab) ? requestedTab : 'overview');

    document.querySelectorAll('[data-check-all], [data-uncheck-all]').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = document.querySelector(button.dataset.checkAll || button.dataset.uncheckAll);
            if (!form) return;
            const selector = form.id
                ? 'input[type="checkbox"][name="package_ids[]"][form="' + form.id + '"]:not(:disabled)'
                : 'input[type="checkbox"][name="package_ids[]"]:not(:disabled)';
            document.querySelectorAll(selector).forEach(function (checkbox) {
                checkbox.checked = Boolean(button.dataset.checkAll);
            });
        });
    });
});
</script>
@endsection
