@extends('layouts.app')

@section('title', 'Bulk Check-In Shipment Intake')
@section('page_title', 'Bulk Check-In Shipment Intake')
@section('breadcrumb', 'Logistics / Bulk Check-In')

@section('content')
@php
    $activeShipment = $selectedShipment ?? null;
    $activeIsSea = $activeShipment && $activeShipment->shipment_type === 'sea';
    $activeShipmentClosed = $activeShipment && $activeShipment->status === 'completed';
    $shipmentPackages = $activeShipment ? $activeShipment->packages : collect();
    $unmanifestedPackageCount = $shipmentPackages->whereNull('manifest_id')->count();
    $scanEvents = $scanEvents ?? collect();
    $unknownScanEvents = $unknownScanEvents ?? collect();
    $inventoryPackages = $inventoryPackages ?? collect();
    $filters = $filters ?? [];
    $unassignedPackages = $shipmentPackages->filter(fn ($package) => blank($package->tenant_id) || in_array($package->location_status, ['unknown_room', 'inhouse_unknown_room', 'unassigned', 'unknown'], true));
    $reviewCount = $unassignedPackages->count() + $unknownScanEvents->count();
    $totalWeightKg = $shipmentPackages->sum(function ($package) {
        $weight = (float) $package->weight;
        return $package->weight_unit === 'lb' ? $weight * 0.45359237 : $weight;
    });
    $totalWeightLb = $totalWeightKg * 2.20462262;
    $totalVolumeCuft = $shipmentPackages->sum(function ($package) {
        $measure = (float) $package->weight;
        return $package->weight_unit === 'cbm' ? $measure * 35.3147 : (in_array($package->weight_unit, ['cuft', 'cbm'], true) ? $measure : 0);
    });
    $totalVolumeCbm = $totalVolumeCuft / 35.3147;
    $shipmentExpenses = $activeShipment ? ((float) $activeShipment->awb_cost + (float) $activeShipment->delivery_cost + (float) $activeShipment->additional_cost) : 0;
    $packageRevenue = (float) $shipmentPackages->sum('amount');
    $lastDuplicate = collect($results ?? [])->firstWhere('result', 'Duplicate');
@endphp
<style>
    .intake-page, .intake-page * { min-width: 0; }
    .intake-head { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; margin-bottom:1rem; }
    .intake-card { background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:8px; padding:1rem; }
    .intake-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; }
    .intake-grid .wide { grid-column:1 / -1; }
    .intake-grid .half { grid-column:span 2; }
    .intake-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; }
    .intake-kpi { border:1px solid rgba(15,23,42,.08); border-radius:8px; background:#fff; padding:1rem; }
    .intake-kpi span { color:#64748b; font-size:.76rem; font-weight:800; text-transform:uppercase; display:block; }
    .intake-kpi strong { font-size:1.25rem; display:block; margin-top:.25rem; color:#0f172a; }
    .shipment-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:.75rem; }
    .shipment-tile { display:block; color:inherit; text-decoration:none; border:1px solid rgba(15,23,42,.08); border-radius:8px; padding:.9rem; background:#fff; }
    .shipment-tile.active { border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.12); }
    .scanner-box { display:flex; gap:.65rem; align-items:end; }
    .scan-input { font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:1.05rem; }
    .result-pill { border-radius:999px; padding:.22rem .55rem; font-size:.75rem; font-weight:800; white-space:nowrap; }
    .result-pill.successful, .result-pill.already-received { background:#dcfce7; color:#166534; }
    .result-pill.duplicate { background:#fee2e2; color:#991b1b; }
    .result-pill.not-found, .result-pill.ambiguous { background:#fef3c7; color:#92400e; }
    .print-report { display:none; }
    .print-report { color:#111827; font-family:Arial, Helvetica, sans-serif; }
    .print-report .report-sheet { width:100%; }
    .print-report .report-header { display:flex; justify-content:space-between; gap:24px; align-items:flex-start; padding-bottom:18px; border-bottom:3px solid #111827; }
    .print-report .report-brand { display:flex; gap:12px; align-items:center; }
    .print-report .report-mark { width:46px; height:46px; border-radius:8px; background:#111827; color:#fff; display:grid; place-items:center; font-weight:800; font-size:15px; }
    .print-report .report-title { margin:0; color:#0f172a; font-size:24px; line-height:1.15; font-weight:800; }
    .print-report .report-subtitle { margin-top:4px; color:#475569; font-size:11px; text-transform:uppercase; letter-spacing:.08em; font-weight:700; }
    .print-report .report-meta { text-align:right; color:#334155; font-size:11px; line-height:1.55; }
    .print-report .report-section { margin-top:16px; break-inside:avoid; page-break-inside:avoid; }
    .print-report .section-title { margin:0 0 8px; color:#0f172a; font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; }
    .print-report .info-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
    .print-report .info-box, .print-report .kpi-box { border:1px solid #cbd5e1; border-radius:8px; padding:9px 10px; background:#f8fafc; min-height:54px; }
    .print-report .info-box.wide { grid-column:span 2; }
    .print-report .label { display:block; color:#64748b; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; margin-bottom:4px; }
    .print-report .value { color:#0f172a; font-size:12px; font-weight:700; line-height:1.3; overflow-wrap:anywhere; }
    .print-report .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
    .print-report .kpi-box { background:#fff; border-top:4px solid #2563eb; }
    .print-report .kpi-box.warning { border-top-color:#f59e0b; }
    .print-report .kpi-box.success { border-top-color:#16a34a; }
    .print-report .kpi-box.dark { border-top-color:#111827; }
    .print-report .kpi-box .value { font-size:16px; }
    .print-report table.report-table { width:100%; border-collapse:collapse; font-size:10px; }
    .print-report .report-table th { background:#e2e8f0; color:#0f172a; border:1px solid #cbd5e1; padding:7px 6px; text-align:left; font-size:9px; text-transform:uppercase; letter-spacing:.04em; }
    .print-report .report-table td { border:1px solid #dbe3ef; padding:6px; vertical-align:top; color:#1f2937; }
    .print-report .report-table tbody tr:nth-child(even) td { background:#f8fafc; }
    .print-report .text-right { text-align:right; }
    .print-report .muted { color:#64748b; font-size:9px; }
    .print-report .badge-print { display:inline-block; border-radius:999px; padding:2px 7px; background:#e2e8f0; color:#0f172a; font-size:9px; font-weight:800; white-space:nowrap; }
    .print-report .badge-print.warn { background:#fef3c7; color:#92400e; }
    .print-report .badge-print.good { background:#dcfce7; color:#166534; }
    .print-report .summary-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .print-report .signature-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; margin-top:34px; }
    .print-report .signature-line { border-top:1px solid #111827; padding-top:7px; font-size:10px; color:#334155; }
    .print-report .report-footer { margin-top:18px; padding-top:8px; border-top:1px solid #cbd5e1; color:#64748b; font-size:9px; display:flex; justify-content:space-between; gap:12px; }
    .intake-page ~ .modal, #newShipmentModal, #editShipmentModal, #bulkUploadModal, #duplicateScanModal { --bs-modal-margin: .5rem; z-index:20080 !important; }
    .intake-page ~ .modal.show, #newShipmentModal.show, #editShipmentModal.show, #bulkUploadModal.show, #duplicateScanModal.show { display:block; pointer-events:none; z-index:20080 !important; }
    .intake-page ~ .modal .modal-dialog, #newShipmentModal .modal-dialog, #editShipmentModal .modal-dialog, #bulkUploadModal .modal-dialog, #duplicateScanModal .modal-dialog { position:relative; z-index:20090 !important; pointer-events:auto; }
    .intake-page ~ .modal .modal-content, #duplicateScanModal .modal-content { position:relative; z-index:20100 !important; pointer-events:auto; }
    .modal-backdrop, .modal-backdrop.show { z-index:20040 !important; pointer-events:none; }
    #newShipmentModal .modal-dialog, #editShipmentModal .modal-dialog, #bulkUploadModal .modal-dialog { max-width:min(920px, calc(100vw - 24px)); margin-top:.5rem; margin-bottom:.5rem; }
    #newShipmentModal .modal-content, #editShipmentModal .modal-content, #bulkUploadModal .modal-content { max-height:calc(100vh - 16px); border:0; border-radius:10px; overflow:hidden; box-shadow:0 24px 80px rgba(15,23,42,.28); }
    #newShipmentModal .modal-header, #newShipmentModal .modal-footer, #editShipmentModal .modal-header, #editShipmentModal .modal-footer, #bulkUploadModal .modal-header, #bulkUploadModal .modal-footer { flex-shrink:0; background:#fff; z-index:2; }
    #newShipmentModal .modal-header, #editShipmentModal .modal-header, #bulkUploadModal .modal-header { padding:.85rem 1rem; border-bottom:1px solid #e5e7eb; }
    #newShipmentModal .modal-body, #editShipmentModal .modal-body, #bulkUploadModal .modal-body { overflow:auto; padding:.85rem 1rem; background:#f8fafc; max-height:calc(100vh - 172px); }
    #newShipmentModal .modal-footer, #editShipmentModal .modal-footer, #bulkUploadModal .modal-footer { padding:.75rem 1rem; border-top:1px solid #e5e7eb; box-shadow:0 -8px 24px rgba(15,23,42,.06); }
    .intake-tabs { gap:.35rem; border-bottom:0; margin-bottom:.85rem; }
    .intake-tabs .nav-link { border:1px solid #dbe3ef; border-radius:8px; color:#334155; font-weight:800; padding:.48rem .75rem; background:#fff; }
    .intake-tabs .nav-link.active { background:#1d4ed8; border-color:#1d4ed8; color:#fff; }
    .shipment-form-section { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:.85rem; }
    .shipment-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.7rem .85rem; }
    .shipment-form-grid .wide { grid-column:1 / -1; }
    #newShipmentModal .form-label, #editShipmentModal .form-label, #bulkUploadModal .form-label { margin-bottom:.25rem; font-size:.8rem; color:#334155; font-weight:700; }
    #newShipmentModal .form-control, #newShipmentModal .form-select, #editShipmentModal .form-control, #editShipmentModal .form-select, #bulkUploadModal .form-control, #bulkUploadModal .form-select { min-height:38px; padding:.38rem .55rem; border-color:#cbd5e1; border-radius:7px; font-size:.95rem; }
    #newShipmentModal textarea.form-control, #editShipmentModal textarea.form-control, #bulkUploadModal textarea.form-control { min-height:76px; }
    #newShipmentModal .form-check, #editShipmentModal .form-check { min-height:38px; display:flex; align-items:center; gap:.45rem; padding-left:1.55rem; }
    @media (max-width: 575.98px) { #newShipmentModal .modal-dialog, #editShipmentModal .modal-dialog, #bulkUploadModal .modal-dialog { max-width:calc(100vw - 10px); margin:.3rem auto; } .shipment-form-grid { grid-template-columns:1fr; } #newShipmentModal .modal-body, #editShipmentModal .modal-body, #bulkUploadModal .modal-body { padding:.7rem; } .intake-tabs .nav-link { padding:.42rem .55rem; font-size:.85rem; } }
    @media (max-width: 991.98px) { .intake-grid, .intake-kpis { grid-template-columns:1fr 1fr; } .intake-grid .wide, .intake-grid .half { grid-column:1 / -1; } .scanner-box { display:block; } }
    @media (max-width: 575.98px) { .intake-head { flex-direction:column; } .intake-kpis { grid-template-columns:1fr; } }
    @media print {
        @page { size:A4; margin:10mm; }
        html, body { width:auto; height:auto; background:#fff !important; overflow:visible !important; }
        body * { visibility:hidden; }
        .sidebar, .topbar, .sidebar-mobile-toggle, .intake-page, .modal, .modal-backdrop { display:none !important; }
        .page-pad { padding:0 !important; overflow:visible !important; }
        .content-wrap, main { margin:0 !important; width:100% !important; height:auto !important; overflow:visible !important; }
        .print-report, .print-report * { visibility:visible; }
        .print-report { display:block !important; position:absolute; inset:0 auto auto 0; width:100%; padding:0; background:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .print-report .report-section, .print-report .kpi-box, .print-report .info-box { break-inside:avoid; page-break-inside:avoid; }
        .print-report .package-section { break-inside:auto; page-break-inside:auto; }
        .print-report table { page-break-inside:auto; }
        .print-report tr { page-break-inside:avoid; page-break-after:auto; }
        .print-report thead { display:table-header-group; }
    }
</style>

<div class="intake-page">
    <div class="intake-head">
        <div>
            <h1 class="h3 mb-1">Bulk Check-In</h1>
            <p class="text-muted mb-0">Shipment intake, scanning, review, manifests, courier billing, and in-house inventory.</p>
        </div>
        <a href="{{ route('superadmin.logistics.index') }}" class="btn btn-outline-secondary">Back to Logistics</a>
    </div>

    <div class="intake-card mb-3">
        <form method="GET" action="{{ route('superadmin.logistics.bulk-check-in') }}" class="row g-2 align-items-end mb-3">
            <div class="col-lg-3"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Master AWB/BOL, shipment, cargo"></div>
            <div class="col-lg-2"><label class="form-label">Type</label><select name="shipment_type" class="form-select"><option value="">All</option><option value="air" @selected(($filters['shipment_type'] ?? '') === 'air')>AIR</option><option value="sea" @selected(($filters['shipment_type'] ?? '') === 'sea')>SEA</option></select></div>
            <div class="col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>In Progress</option><option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option></select></div>
            <div class="col-lg-2"><label class="form-label">Arrival From</label><input type="date" name="arrival_from" value="{{ $filters['arrival_from'] ?? '' }}" class="form-control"></div>
            <div class="col-lg-2"><label class="form-label">Arrival To</label><input type="date" name="arrival_to" value="{{ $filters['arrival_to'] ?? '' }}" class="form-control"></div>
            <div class="col-lg-1 d-grid"><button class="btn btn-outline-primary"><i class="fas fa-filter"></i></button></div>
        </form>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <div><h2 class="h5 mb-1">Shipment Intakes</h2><div class="text-muted small">Select a shipment to scan and manage packages.</div></div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newShipmentModal"><i class="fas fa-plus me-1"></i>New Intake</button>
        </div>
        <div class="shipment-list">
            @forelse($shipments as $shipment)
                <a class="shipment-tile {{ $activeShipment && $activeShipment->id === $shipment->id ? 'active' : '' }}" href="{{ route('superadmin.logistics.bulk-check-in', array_filter(request()->only(['search','shipment_type','status','arrival_from','arrival_to']) + ['shipment_id' => $shipment->id])) }}">
                    <div class="d-flex justify-content-between gap-2"><strong>{{ $shipment->shipment_number }}</strong><span class="badge bg-light text-dark">{{ strtoupper($shipment->shipment_type) }}</span></div>
                    <div class="text-muted small mt-1">{{ $shipment->master_number }} / {{ $shipment->cargo_company ?: '-' }}</div>
                    <div class="small mt-2">Arrival: {{ optional($shipment->arrival_date)->format('M d, Y') ?: '-' }}</div>
                    <div class="small">{{ number_format($shipment->packages_count) }} scanned / {{ number_format($shipment->manifests_count) }} manifests</div>
                    <span class="badge mt-2 bg-{{ $shipment->status === 'completed' ? 'success' : 'warning text-dark' }}">{{ ucfirst(str_replace('_', ' ', $shipment->status ?? 'in_progress')) }}</span>
                </a>
            @empty
                <div class="text-muted">No shipment intakes found.</div>
            @endforelse
        </div>
    </div>

    @if($activeShipment)
        <div class="intake-kpis mb-3">
            <div class="intake-kpi"><span>Total Scanned</span><strong>{{ number_format($shipmentPackages->count()) }}</strong></div>
            <div class="intake-kpi"><span>Unassigned / Unknown</span><strong class="text-warning">{{ number_format($reviewCount) }}</strong></div>
            <div class="intake-kpi"><span>{{ $activeIsSea ? 'Total Volume' : 'Total Weight' }}</span><strong>{{ $activeIsSea ? number_format($totalVolumeCuft, 2).' cu.ft / '.number_format($totalVolumeCbm, 3).' CBM' : number_format($totalWeightLb, 2).' lbs / '.number_format($totalWeightKg, 2).' kg' }}</strong></div>
            <div class="intake-kpi"><span>Revenue / Cost</span><strong>JMD {{ number_format($packageRevenue, 2) }} / {{ number_format($shipmentExpenses, 2) }}</strong></div>
        </div>

        <div class="intake-card mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                    <h2 class="h4 mb-1">{{ $activeShipment->master_number }}</h2>
                    <div class="text-muted">{{ $activeShipment->shipment_number }} / {{ strtoupper($activeShipment->shipment_type) }} / {{ $activeShipment->region ?? 'USA' }} / {{ $activeShipment->cargo_company ?: '-' }} / Pieces: {{ number_format($activeShipment->pieces ?? 0) }}</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editShipmentModal"><i class="fas fa-edit me-1"></i>Edit Shipment</button>
                    @if(($activeShipment->packages_count ?? $shipmentPackages->count()) === 0 && ($activeShipment->manifests_count ?? $activeShipment->manifests->count()) === 0)
                        <form method="POST" action="{{ route('superadmin.logistics.shipments.destroy', $activeShipment) }}" onsubmit="return confirm('Delete this blank shipment intake? This cannot be undone.');">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete Blank</button></form>
                    @endif
                    @if(! $activeShipmentClosed)
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bulkUploadModal"><i class="fas fa-upload me-1"></i>Bulk Upload Packages</button>
                    @endif
                    @if($unmanifestedPackageCount > 0)
                        <form method="POST" action="{{ route('superadmin.logistics.shipments.generate-bills', $activeShipment) }}" onsubmit="return confirm('Generate or update courier manifests for unmanifested packages on this shipment?');">@csrf<button class="btn btn-primary"><i class="fas fa-clipboard-list me-1"></i>Generate / Update Manifest</button></form>
                    @elseif(($activeShipment->manifests_count ?? $activeShipment->manifests->count()) > 0)
                        <form method="POST" action="{{ route('superadmin.logistics.shipments.generate-invoices', $activeShipment) }}" onsubmit="return confirm('Generate bulk shipment invoices from the generated manifest(s)?');">@csrf<button class="btn btn-primary"><i class="fas fa-file-invoice-dollar me-1"></i>Generate Bulk Invoice</button></form>
                    @elseif(! $activeShipmentClosed)
                        <form method="POST" action="{{ route('superadmin.logistics.shipments.generate-bills', $activeShipment) }}" onsubmit="return confirm('Generate courier manifests for this shipment?');">@csrf<button class="btn btn-primary"><i class="fas fa-clipboard-list me-1"></i>Generate Bulk Manifest</button></form>
                    @endif
                    <button class="btn btn-outline-dark" onclick="window.print()"><i class="fas fa-print me-1"></i>Print Report</button>
                </div>
            </div>

            @if(! empty($activeShipment->documents))
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <strong>Uploaded Files</strong>
                        <span class="badge bg-secondary">{{ count($activeShipment->documents) }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach($activeShipment->documents as $document)
                            @php
                                $documentPath = $document['path'] ?? null;
                                $documentName = $document['name'] ?? ($documentPath ? basename($documentPath) : 'Uploaded file');
                                $documentUrl = $documentPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($documentPath) : null;
                            @endphp
                            @if($documentUrl)
                                <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-paperclip me-1"></i>{{ $documentName }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if($activeShipmentClosed)
                <div class="alert alert-warning"><i class="fas fa-lock me-1"></i>This shipment intake is closed. Reopen it before scanning or uploading more packages.</div>
            @else
                <form method="POST" action="{{ route('superadmin.logistics.shipments.scan', $activeShipment) }}" class="scanner-box mb-3">
                    @csrf
                    <div class="flex-grow-1"><label class="form-label">Live Intake Scanner</label><input name="tracking_number" class="form-control scan-input" placeholder="Scan barcode here and press Enter" autocomplete="off" autofocus required></div>
                    <div style="width:150px"><label class="form-label">{{ $activeIsSea ? 'Volume' : 'Weight' }}</label><input type="number" step="0.01" min="0" name="weight" class="form-control" placeholder="Optional"></div>
                    <div style="width:110px"><label class="form-label">Unit</label><select name="weight_unit" class="form-select">@if($activeIsSea)<option value="cuft" selected>cu.ft</option><option value="cbm">CBM</option>@else<option value="lb" selected>lb</option><option value="kg">kg</option>@endif</select></div>
                    <button class="btn btn-success"><i class="fas fa-barcode me-1"></i>Scan</button>
                </form>
            @endif

            @if(! empty($results))
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Scan</th><th>Result</th><th>Courier</th><th>Package</th><th>Customer</th><th>Measure</th><th>Previous</th><th>New</th><th>Message</th></tr></thead>
                        <tbody>
                            @foreach($results as $row)
                                @php
                                    $class = \Illuminate\Support\Str::slug($row['result'] ?? '');
                                @endphp
                                <tr>
                                    <td><code>{{ $row['scan'] ?? '' }}</code></td>
                                    <td><span class="result-pill {{ $class }}">{{ $row['result'] ?? '' }}</span></td>
                                    <td>{{ $row['courier'] ?? '-' }}</td>
                                    <td>{{ $row['package'] ?? '-' }}</td>
                                    <td>{{ $row['customer'] ?? '-' }}</td>
                                    <td>{{ $row['weight'] ?? '-' }}</td>
                                    <td>{{ $row['previous_status'] ?? '-' }}</td>
                                    <td>{{ $row['new_status'] ?? '-' }}</td>
                                    <td>{{ $row['message'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#packagesTab" type="button">Scanned Packages</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviewTab" type="button">Unassigned / Unknown</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#scanHistoryTab" type="button">Scan History</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#manifestsTab" type="button">Manifests</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#inventoryTab" type="button">Inventory</button></li>
            </ul>
            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="packagesTab">@include('superadmin.logistics.partials.bulk-package-table', ['packages' => $shipmentPackages, 'empty' => 'No packages scanned into this shipment yet.', 'tableId' => 'scannedPackagesTable', 'activeShipment' => $activeShipment, 'allowIntakeRemoval' => true])</div>
                <div class="tab-pane fade" id="reviewTab">
                    @include('superadmin.logistics.partials.bulk-package-table', ['packages' => $unassignedPackages, 'empty' => 'No unassigned packages in logistics packages.', 'tableId' => 'unassignedPackagesTable'])
                    <div class="table-responsive mt-3"><table class="table table-sm align-middle"><thead><tr><th>Scan</th><th>Result</th><th>Courier</th><th>Message</th><th>Scanned</th></tr></thead><tbody>@forelse($unknownScanEvents as $event)<tr><td><code>{{ $event->scan_value }}</code></td><td><span class="result-pill {{ \Illuminate\Support\Str::slug($event->result) }}">{{ $event->result }}</span></td><td>{{ $event->courier_name ?: '-' }}</td><td>{{ $event->message }}</td><td>{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('M d, Y h:i A') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No unknown, duplicate, or ambiguous scans recorded.</td></tr>@endforelse</tbody></table></div>
                </div>
                <div class="tab-pane fade" id="scanHistoryTab"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Scan</th><th>Result</th><th>Courier</th><th>Previous</th><th>New</th><th>When</th></tr></thead><tbody>@forelse($scanEvents as $event)<tr><td><code>{{ $event->scan_value }}</code></td><td><span class="result-pill {{ \Illuminate\Support\Str::slug($event->result) }}">{{ $event->result }}</span></td><td>{{ $event->courier_name ?: '-' }}</td><td>{{ $event->previous_status ?: '-' }}</td><td>{{ $event->new_status ?: '-' }}</td><td>{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('M d, Y h:i A') }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-3">No scan history yet.</td></tr>@endforelse</tbody></table></div></div>
                <div class="tab-pane fade" id="manifestsTab"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Manifest</th><th>Courier</th><th>Status</th><th class="text-end">Packages</th><th class="text-end">Invoices</th><th></th></tr></thead><tbody>@forelse($activeShipment->manifests as $manifest)<tr><td><strong>{{ $manifest->manifest_number }}</strong><br><span class="text-muted small">{{ $manifest->warehouse_reference }}</span></td><td>{{ optional($manifest->tenant)->company_name ?: '-' }}</td><td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $manifest->status)) }}</span></td><td class="text-end">{{ number_format($manifest->packages()->count()) }}</td><td class="text-end">{{ number_format($manifest->invoices->count()) }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('superadmin.logistics.show', $manifest) }}">View / Send / Edit</a></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No manifests generated for this shipment yet.</td></tr>@endforelse</tbody></table></div></div>
                <div class="tab-pane fade" id="inventoryTab">@include('superadmin.logistics.partials.bulk-package-table', ['packages' => $inventoryPackages, 'empty' => 'No in-house packages across shipments.', 'tableId' => 'inventoryPackagesTable'])</div>
            </div>
        </div>
    @endif
</div>

@if($activeShipment)
<div class="print-report">
    <div class="report-sheet">
        <header class="report-header">
            <div class="report-brand">
                <div class="report-mark">{{ strtoupper(substr($platformName ?? config('app.name', 'FT'), 0, 2)) }}</div>
                <div>
                    <h1 class="report-title">Shipment Intake Report</h1>
                    <div class="report-subtitle">Bulk Check-In / Warehouse Receiving</div>
                </div>
            </div>
            <div class="report-meta">
                <strong>{{ $platformName ?? config('app.name', 'Courier SaaS') }}</strong><br>
                Generated: {{ now()->format('M d, Y h:i A') }}<br>
                Report ID: {{ $activeShipment->shipment_number }}
            </div>
        </header>

        <section class="report-section">
            <h2 class="section-title">Shipment Details</h2>
            <div class="info-grid">
                <div class="info-box"><span class="label">Master AWB / BOL</span><div class="value">{{ $activeShipment->master_number ?: '-' }}</div></div>
                <div class="info-box"><span class="label">Shipment No.</span><div class="value">{{ $activeShipment->shipment_number }}</div></div>
                <div class="info-box"><span class="label">Shipment Type</span><div class="value">{{ strtoupper($activeShipment->shipment_type) }}</div></div>
                <div class="info-box"><span class="label">Region</span><div class="value">{{ $activeShipment->region ?? 'USA' }}</div></div>
                <div class="info-box"><span class="label">Status</span><div class="value">{{ ucfirst(str_replace('_', ' ', $activeShipment->status ?? 'in_progress')) }}</div></div>
                <div class="info-box"><span class="label">Cargo Company</span><div class="value">{{ $activeShipment->cargo_company ?: '-' }}</div></div>
                <div class="info-box"><span class="label">Pieces</span><div class="value">{{ number_format($activeShipment->pieces ?? 0) }}</div></div>
                <div class="info-box"><span class="label">Departure</span><div class="value">{{ optional($activeShipment->departure_date)->format('M d, Y') ?: '-' }}</div></div>
                <div class="info-box"><span class="label">Arrival</span><div class="value">{{ optional($activeShipment->arrival_date)->format('M d, Y') ?: '-' }}</div></div>
                <div class="info-box"><span class="label">Customs Office</span><div class="value">{{ $activeShipment->customs_office ?: '-' }}</div></div>
                <div class="info-box"><span class="label">Loading Port</span><div class="value">{{ $activeShipment->loading_port ?: '-' }}</div></div>
                <div class="info-box"><span class="label">Unloading Place</span><div class="value">{{ $activeShipment->place_of_unloading ?: '-' }}</div></div>
                <div class="info-box"><span class="label">Agent / ASYCUDA</span><div class="value">{{ $activeShipment->shipping_agent_code ?: '-' }} / {{ $activeShipment->asycuda_reference_number ?: '-' }}</div></div>
                @if($activeShipment->shipment_type === 'sea' || filled($activeShipment->voyage_container_numbers))
                    <div class="info-box wide"><span class="label">Voyage / Container Numbers</span><div class="value">{{ $activeShipment->voyage_container_numbers ?: '-' }}</div></div>
                @endif
                @if(filled($activeShipment->notes))
                    <div class="info-box wide"><span class="label">Notes</span><div class="value">{{ $activeShipment->notes }}</div></div>
                @endif
            </div>
        </section>

        <section class="report-section">
            <h2 class="section-title">Intake Summary</h2>
            <div class="kpi-grid">
                <div class="kpi-box"><span class="label">Packages Scanned</span><div class="value">{{ number_format($shipmentPackages->count()) }}</div></div>
                <div class="kpi-box warning"><span class="label">Unassigned / Unknown</span><div class="value">{{ number_format($reviewCount) }}</div></div>
                <div class="kpi-box"><span class="label">{{ $activeIsSea ? 'Total Volume' : 'Total Weight' }}</span><div class="value">{{ $activeIsSea ? number_format($totalVolumeCuft, 2).' cu.ft' : number_format($totalWeightLb, 2).' lb' }}</div><div class="muted">{{ $activeIsSea ? number_format($totalVolumeCbm, 3).' CBM' : number_format($totalWeightKg, 2).' kg' }}</div></div>
                <div class="kpi-box success"><span class="label">Net Shipment Result</span><div class="value">JMD {{ number_format($packageRevenue - $shipmentExpenses, 2) }}</div></div>
            </div>
        </section>

        <section class="report-section">
            <h2 class="section-title">Financial Summary</h2>
            <table class="report-table">
                <thead><tr><th>Package Revenue</th><th>AWB/BOL Cost</th><th>Delivery Cost</th><th>Additional Cost</th><th>Net</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="text-right">JMD {{ number_format($packageRevenue, 2) }}</td>
                        <td class="text-right">JMD {{ number_format((float) $activeShipment->awb_cost, 2) }}</td>
                        <td class="text-right">JMD {{ number_format((float) $activeShipment->delivery_cost, 2) }}</td>
                        <td class="text-right">JMD {{ number_format((float) $activeShipment->additional_cost, 2) }}</td>
                        <td class="text-right"><strong>JMD {{ number_format($packageRevenue - $shipmentExpenses, 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="report-section summary-row">
            <div>
                <h2 class="section-title">Courier Breakdown</h2>
                <table class="report-table">
                    <thead><tr><th>Courier</th><th class="text-right">Packages</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @forelse($shipmentPackages->groupBy('tenant_id') as $packages)
                            <tr>
                                <td>{{ optional($packages->first()->tenant)->company_name ?: 'Unassigned / Unknown' }}</td>
                                <td class="text-right">{{ number_format($packages->count()) }}</td>
                                <td class="text-right">JMD {{ number_format((float) $packages->sum('amount'), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">No packages scanned into this shipment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                <h2 class="section-title">Manifest Breakdown</h2>
                <table class="report-table">
                    <thead><tr><th>Manifest</th><th>Courier</th><th class="text-right">Packages</th></tr></thead>
                    <tbody>
                        @forelse($activeShipment->manifests as $manifest)
                            <tr>
                                <td>{{ $manifest->manifest_number }}</td>
                                <td>{{ optional($manifest->tenant)->company_name ?: '-' }}</td>
                                <td class="text-right">{{ number_format($manifest->packages()->count()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">No manifests generated.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($reviewCount > 0)
            <section class="report-section">
                <h2 class="section-title">Items Requiring Review</h2>
                <table class="report-table">
                    <thead><tr><th>Tracking / Scan</th><th>Issue</th><th>Courier</th><th>Notes</th></tr></thead>
                    <tbody>
                        @foreach($unassignedPackages->take(20) as $package)
                            <tr>
                                <td><strong>{{ $package->tracking_number }}</strong></td>
                                <td><span class="badge-print warn">Unassigned</span></td>
                                <td>{{ optional($package->tenant)->company_name ?: '-' }}</td>
                                <td>{{ $package->location_notes ?: 'Needs client/mailbox assignment.' }}</td>
                            </tr>
                        @endforeach
                        @foreach($unknownScanEvents->take(20) as $event)
                            <tr>
                                <td><strong>{{ $event->scan_value }}</strong></td>
                                <td><span class="badge-print warn">{{ $event->result }}</span></td>
                                <td>{{ $event->courier_name ?: '-' }}</td>
                                <td>{{ $event->message ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($reviewCount > 40)
                    <div class="muted" style="margin-top:6px;">Showing first 40 review items. Use the portal review tab for the full list.</div>
                @endif
            </section>
        @endif

        <section class="report-section package-section">
            <h2 class="section-title">Scanned Package Register</h2>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Courier</th>
                        <th>Customer</th>
                        <th>Description</th>
                        <th class="text-right">Measure</th>
                        <th class="text-right">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipmentPackages as $package)
                        @php
                            $packageMeasure = (float) $package->weight;
                            if ($package->weight_unit === 'cbm') {
                                $measureDisplay = number_format($packageMeasure * 35.3147, 2).' cu.ft / '.number_format($packageMeasure, 3).' CBM';
                            } elseif ($package->weight_unit === 'cuft') {
                                $measureDisplay = number_format($packageMeasure, 2).' cu.ft / '.number_format($packageMeasure / 35.3147, 3).' CBM';
                            } else {
                                $measureDisplay = number_format($packageMeasure, 2).' '.$package->weight_unit;
                            }
                        @endphp
                        <tr>
                            <td><strong>{{ $package->tracking_number }}</strong><br><span class="muted">{{ optional($package->courierPackage)->tracking_number ?: optional($package->courierPackage)->supplier_tracking }}</span></td>
                            <td>{{ optional($package->tenant)->company_name ?: '-' }}</td>
                            <td>{{ optional($package->courierPackage?->customer?->user)->name ?: optional($package->courierPackage)->mailbox ?: '-' }}</td>
                            <td>{{ $package->description ?: '-' }}</td>
                            <td class="text-right">{{ $measureDisplay }}</td>
                            <td class="text-right">JMD {{ number_format((float) $package->amount, 2) }}</td>
                            <td><span class="badge-print {{ $package->tenant_id ? 'good' : 'warn' }}">{{ $package->tenant_id ? 'Assigned' : 'Unassigned' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">No packages scanned into this shipment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="signature-grid">
            <div class="signature-line">Prepared by</div>
            <div class="signature-line">Verified by</div>
            <div class="signature-line">Date / time</div>
        </section>

        <footer class="report-footer">
            <span>{{ $activeShipment->shipment_number }} / {{ $activeShipment->master_number ?: 'No master number' }}</span>
            <span>Printed from {{ $platformName ?? config('app.name', 'Courier SaaS') }}</span>
        </footer>
    </div>
</div>
@endif

<div class="modal fade" id="newShipmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('superadmin.logistics.shipments.store') }}" enctype="multipart/form-data" data-intake-form novalidate>
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">New Shipment Intake</h5>
                        <div class="text-muted small">AIR/SEA master shipment setup</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav intake-tabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#intakeShipmentTab" type="button"><i class="fas fa-plane-departure me-1"></i>Shipment</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#intakeCustomsTab" type="button"><i class="fas fa-calendar-alt me-1"></i>Customs</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#intakeCostsTab" type="button"><i class="fas fa-file-invoice-dollar me-1"></i>Costs</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#intakeDocsTab" type="button"><i class="fas fa-paperclip me-1"></i>Docs</button></li>
                    </ul>

                    <div class="alert alert-danger d-none mb-3" data-intake-errors></div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="intakeShipmentTab">
                            <section class="shipment-form-section">
                                <div class="shipment-form-grid">
                                    <div>
                                        <label class="form-label">Shipment Type</label>
                                        <select name="shipment_type" class="form-select" data-shipment-type required><option value="air">AIR</option><option value="sea">SEA</option></select>
                                    </div>
                                    <div>
                                        <label class="form-label">Region</label>
                                        <select name="region" class="form-select" required><option value="USA" @selected(old('region', 'USA') === 'USA')>USA</option><option value="China" @selected(old('region') === 'China')>China</option></select>
                                    </div>
                                    <div>
                                        <label class="form-label">Cargo Company</label>
                                        <input name="cargo_company" value="{{ old('cargo_company') }}" class="form-control" list="cargoCompanies" required>
                                        <datalist id="cargoCompanies"><option value="CAL Cargo"><option value="Amerijet"><option value="Caribbean Airlines"><option value="DHL Aviation"><option value="FedEx Express"><option value="UPS Airlines"><option value="MSC"><option value="Maersk"><option value="Seaboard Marine"><option value="Tropical Shipping"></datalist>
                                    </div>
                                    <div>
                                        <label class="form-label">Master AWB / BOL</label>
                                        <input name="master_number" value="{{ old('master_number') }}" class="form-control" placeholder="Auto if blank">
                                    </div>
                                    <div>
                                        <label class="form-label">Pieces</label>
                                        <input type="number" min="0" name="pieces" value="{{ old('pieces', 0) }}" class="form-control">
                                    </div>
                                    <div class="wide">
                                        <div class="form-check">
                                            <input type="hidden" name="auto_generate_master" value="0">
                                            <input class="form-check-input" type="checkbox" name="auto_generate_master" value="1" id="autoMaster" checked>
                                            <label class="form-check-label fw-semibold" for="autoMaster">Auto-generate master number</label>
                                        </div>
                                    </div>
                                    <div class="wide" data-sea-field>
                                        <label class="form-label">Voyage / Container #</label>
                                        <input name="voyage_container_numbers" value="{{ old('voyage_container_numbers') }}" class="form-control">
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="tab-pane fade" id="intakeCustomsTab">
                            <section class="shipment-form-section">
                                <div class="shipment-form-grid">
                                    <div>
                                        <label class="form-label">Departure Date</label>
                                        <input type="date" name="departure_date" value="{{ old('departure_date') }}" class="form-control" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Arrival Date</label>
                                        <input type="date" name="arrival_date" value="{{ old('arrival_date') }}" class="form-control">
                                    </div>
                                    <div>
                                        <label class="form-label">Shipping Agent Code</label>
                                        <input name="shipping_agent_code" value="{{ old('shipping_agent_code') }}" class="form-control">
                                    </div>
                                    <div>
                                        <label class="form-label">ASYCUDA Reference #</label>
                                        <input name="asycuda_reference_number" value="{{ old('asycuda_reference_number') }}" class="form-control">
                                    </div>
                                    <div>
                                        <label class="form-label">Customs Office</label>
                                        <input name="customs_office" value="{{ old('customs_office') }}" class="form-control" list="customsOffices">
                                        <datalist id="customsOffices">@foreach($customsOffices as $office)<option value="{{ $office->name }}">@endforeach</datalist>
                                    </div>
                                    <div>
                                        <label class="form-label">Loading Port</label>
                                        <input name="loading_port" value="{{ old('loading_port') }}" class="form-control" list="loadingPorts">
                                        <datalist id="loadingPorts">@foreach($loadingPorts as $port)<option value="{{ $port->name }}">@endforeach</datalist>
                                    </div>
                                    <div class="wide">
                                        <label class="form-label">Place of Unloading</label>
                                        <input name="place_of_unloading" value="{{ old('place_of_unloading') }}" class="form-control" list="loadingPorts">
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="tab-pane fade" id="intakeCostsTab">
                            <section class="shipment-form-section">
                                <div class="shipment-form-grid">
                                    <div>
                                        <label class="form-label">AWB/BOL Cost</label>
                                        <input type="number" step="0.01" min="0" name="awb_cost" value="{{ old('awb_cost', 0) }}" class="form-control">
                                    </div>
                                    <div>
                                        <label class="form-label">Delivery / Transport</label>
                                        <input type="number" step="0.01" min="0" name="delivery_cost" value="{{ old('delivery_cost', 0) }}" class="form-control">
                                    </div>
                                    <div>
                                        <label class="form-label">Additional Cost</label>
                                        <input type="number" step="0.01" min="0" name="additional_cost" value="{{ old('additional_cost', 0) }}" class="form-control">
                                    </div>
                                    <div>
                                        <label class="form-label">Exporter Name</label>
                                        <input name="exporter_name" value="{{ old('exporter_name') }}" class="form-control">
                                    </div>
                                    <div class="wide">
                                        <label class="form-label">Exporter Address</label>
                                        <textarea name="exporter_address" class="form-control" rows="2">{{ old('exporter_address') }}</textarea>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="tab-pane fade" id="intakeDocsTab">
                            <section class="shipment-form-section">
                                <div class="shipment-form-grid">
                                    <div>
                                        <label class="form-label">AWB / BOL Document</label>
                                        <input type="file" name="documents[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt">
                                    </div>
                                    <div>
                                        <label class="form-label">Arrival Notice</label>
                                        <input type="file" name="documents[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt">
                                    </div>
                                    <div class="wide">
                                        <label class="form-label">Additional Shipment Files</label>
                                        <input type="file" name="documents[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt">
                                    </div>
                                    <div class="wide">
                                        <label class="form-label">Notes</label>
                                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary"><i class="fas fa-check me-1"></i>Create Shipment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if($activeShipment)
<div class="modal fade" id="editShipmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('superadmin.logistics.shipments.update', $activeShipment) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Edit Shipment Intake</h5>
                        <div class="text-muted small">{{ $activeShipment->shipment_number }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="shipment-form-section">
                        <div class="shipment-form-grid">
                            <div><label class="form-label">Shipment Type</label><select name="shipment_type" class="form-select" data-shipment-type required><option value="air" @selected(old('shipment_type', $activeShipment->shipment_type) === 'air')>AIR</option><option value="sea" @selected(old('shipment_type', $activeShipment->shipment_type) === 'sea')>SEA</option></select></div>
                            <div><label class="form-label">Region</label><select name="region" class="form-select" required><option value="USA" @selected(old('region', $activeShipment->region ?? 'USA') === 'USA')>USA</option><option value="China" @selected(old('region', $activeShipment->region ?? 'USA') === 'China')>China</option></select></div>
                            <div><label class="form-label">Status</label><select name="status" class="form-select" required><option value="in_progress" @selected(old('status', $activeShipment->status) === 'in_progress')>In Progress</option><option value="completed" @selected(old('status', $activeShipment->status) === 'completed')>Completed</option></select></div>
                            <div><label class="form-label">Master AWB / BOL</label><input name="master_number" class="form-control" value="{{ old('master_number', $activeShipment->master_number) }}"></div>
                            <div class="form-check mt-4"><input type="checkbox" name="auto_generate_master" value="1" class="form-check-input" id="editAutoMaster"><label class="form-check-label" for="editAutoMaster">Auto-generate new master number</label></div>
                            <div><label class="form-label">Cargo Company</label><input name="cargo_company" class="form-control" value="{{ old('cargo_company', $activeShipment->cargo_company) }}" required></div>
                            <div><label class="form-label">Pieces</label><input type="number" min="0" name="pieces" class="form-control" value="{{ old('pieces', $activeShipment->pieces ?? 0) }}"></div>
                            <div><label class="form-label">Departure Date</label><input type="date" name="departure_date" class="form-control" value="{{ old('departure_date', optional($activeShipment->departure_date)->format('Y-m-d')) }}" required></div>
                            <div><label class="form-label">Arrival Date</label><input type="date" name="arrival_date" class="form-control" value="{{ old('arrival_date', optional($activeShipment->arrival_date)->format('Y-m-d')) }}"></div>
                            <div><label class="form-label">Customs Office</label><input name="customs_office" class="form-control" value="{{ old('customs_office', $activeShipment->customs_office) }}"></div>
                            <div><label class="form-label">Loading Port</label><input name="loading_port" class="form-control" value="{{ old('loading_port', $activeShipment->loading_port) }}"></div>
                            <div><label class="form-label">Place of Unloading</label><input name="place_of_unloading" class="form-control" value="{{ old('place_of_unloading', $activeShipment->place_of_unloading) }}"></div>
                            <div><label class="form-label">Shipping Agent Code</label><input name="shipping_agent_code" class="form-control" value="{{ old('shipping_agent_code', $activeShipment->shipping_agent_code) }}"></div>
                            <div><label class="form-label">ASCUDA Reference #</label><input name="asycuda_reference_number" class="form-control" value="{{ old('asycuda_reference_number', $activeShipment->asycuda_reference_number) }}"></div>
                            <div data-sea-field><label class="form-label">Voyage / Container Numbers</label><input name="voyage_container_numbers" class="form-control" value="{{ old('voyage_container_numbers', $activeShipment->voyage_container_numbers) }}"></div>
                            <div><label class="form-label">AWB/BOL Cost</label><input type="number" step="0.01" min="0" name="awb_cost" class="form-control" value="{{ old('awb_cost', $activeShipment->awb_cost ?? 0) }}"></div>
                            <div><label class="form-label">Delivery / Transport</label><input type="number" step="0.01" min="0" name="delivery_cost" class="form-control" value="{{ old('delivery_cost', $activeShipment->delivery_cost ?? 0) }}"></div>
                            <div><label class="form-label">Additional Cost</label><input type="number" step="0.01" min="0" name="additional_cost" class="form-control" value="{{ old('additional_cost', $activeShipment->additional_cost ?? 0) }}"></div>
                            <div><label class="form-label">Exporter Name</label><input name="exporter_name" class="form-control" value="{{ old('exporter_name', $activeShipment->exporter_name) }}"></div>
                            <div class="wide"><label class="form-label">Exporter Address</label><textarea name="exporter_address" class="form-control" rows="2">{{ old('exporter_address', $activeShipment->exporter_address) }}</textarea></div>
                            <div class="wide"><label class="form-label">Add Documents</label><input type="file" name="documents[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt">@if(! empty($activeShipment->documents))<div class="form-text">Existing documents remain attached. New uploads are appended.</div>@endif</div>
                            @if(! empty($activeShipment->documents))
                                <div class="wide">
                                    <label class="form-label">Existing Documents</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($activeShipment->documents as $document)
                                            @php
                                                $documentPath = $document['path'] ?? null;
                                                $documentName = $document['name'] ?? ($documentPath ? basename($documentPath) : 'Uploaded file');
                                                $documentUrl = $documentPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($documentPath) : null;
                                            @endphp
                                            @if($documentUrl)
                                                <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-paperclip me-1"></i>{{ $documentName }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="wide"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $activeShipment->notes) }}</textarea></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Shipment</button></div>
            </form>
        </div>
    </div>
</div>
@endif
@if($activeShipment)
<div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Bulk Upload Packages</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form method="POST" action="{{ route('superadmin.logistics.shipments.bulk-upload', $activeShipment) }}" enctype="multipart/form-data">@csrf<div class="modal-body"><label class="form-label">CSV File</label><input type="file" name="package_file" class="form-control mb-3" accept=".csv,.txt"><label class="form-label">Or Paste Rows</label><textarea name="package_rows" rows="8" class="form-control" placeholder="{{ $activeIsSea ? 'tracking_number,volume,unit&#10;TRK123,12.5,cuft' : 'tracking_number,weight,unit&#10;TRK123,2.5,lb' }}"></textarea><div class="form-text">CSV/paste rows are processed against real courier packages. If unit is blank, {{ $activeIsSea ? 'cu.ft' : 'lb' }} is used.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Upload & Process</button></div></form></div></div></div>
@endif

@if($lastDuplicate)
<div class="modal fade" id="duplicateScanModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Duplicate Scan Detected</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-1"><strong>{{ $lastDuplicate['scan'] ?? '' }}</strong></p><p class="mb-0">{{ $lastDuplicate['message'] ?? 'This scan already exists in shipment intake.' }}</p></div><div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Acknowledge</button></div></div></div></div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-shipment-type]').forEach(function (shipmentType) {
        const container = shipmentType.closest('.modal-content') || document;
        const seaFields = Array.from(container.querySelectorAll('[data-sea-field]'));
        function syncShipmentFields() {
            const isSea = shipmentType.value === 'sea';
            seaFields.forEach(field => field.style.display = isSea ? '' : 'none');
        }
        shipmentType.addEventListener('change', syncShipmentFields);
        syncShipmentFields();
    });
    document.querySelectorAll('[data-table-search]').forEach(input => { input.addEventListener('input', () => { const table = document.getElementById(input.dataset.tableSearch); const needle = input.value.toLowerCase(); if (!table) return; table.querySelectorAll('tbody tr').forEach(row => row.style.display = row.innerText.toLowerCase().includes(needle) ? '' : 'none'); }); });
    document.querySelectorAll('[data-table-export]').forEach(button => { button.addEventListener('click', () => { const table = document.getElementById(button.dataset.tableExport); if (!table) return; const rows = Array.from(table.querySelectorAll('tr')).map(row => Array.from(row.children).map(cell => '"' + cell.innerText.replace(/"/g, '""').trim() + '"').join(',')); const blob = new Blob([rows.join('\n')], { type: 'text/csv' }); const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = button.dataset.tableExport + '.csv'; link.click(); URL.revokeObjectURL(link.href); }); });
    const intakeForm = document.querySelector('[data-intake-form]');
    const intakeErrors = document.querySelector('[data-intake-errors]');
    function fieldLabel(field) {
        const wrap = field.closest('div');
        const label = wrap ? wrap.querySelector('label') : null;
        return label ? label.innerText.trim() : (field.getAttribute('name') || 'This field');
    }
    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        let feedback = field.parentElement ? field.parentElement.querySelector('.invalid-feedback') : null;
        if (!feedback && field.parentElement) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentElement.appendChild(feedback);
        }
        if (feedback) feedback.innerText = message;
    }
    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        const feedback = field.parentElement ? field.parentElement.querySelector('.invalid-feedback') : null;
        if (feedback) feedback.remove();
    }
    function activateFieldTab(field) {
        const pane = field.closest('.tab-pane');
        if (!pane || !pane.id) return;
        const trigger = document.querySelector(`[data-bs-target="#${pane.id}"]`);
        if (!trigger) return;
        if (window.bootstrap && bootstrap.Tab) bootstrap.Tab.getOrCreateInstance(trigger).show();
        else trigger.click();
    }
    if (intakeForm) {
        intakeForm.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', () => clearFieldError(field));
            field.addEventListener('change', () => clearFieldError(field));
        });
        intakeForm.addEventListener('submit', function (event) {
            const requiredFields = Array.from(intakeForm.querySelectorAll('[required]'));
            const invalid = requiredFields.filter(field => !String(field.value || '').trim());
            intakeForm.querySelectorAll('.is-invalid').forEach(clearFieldError);
            if (intakeErrors) {
                intakeErrors.classList.add('d-none');
                intakeErrors.innerHTML = '';
            }
            if (invalid.length === 0) return;
            event.preventDefault();
            invalid.forEach(field => showFieldError(field, fieldLabel(field) + ' is required.'));
            activateFieldTab(invalid[0]);
            setTimeout(() => invalid[0].focus({ preventScroll: false }), 150);
            if (intakeErrors) {
                intakeErrors.classList.remove('d-none');
                intakeErrors.innerHTML = '<strong>Please complete required fields:</strong><ul class="mb-0 mt-1">' + invalid.map(field => '<li>' + fieldLabel(field) + '</li>').join('') + '</ul>';
            }
        });
    }
    document.addEventListener('hidden.bs.modal', function () {
        if (document.querySelector('.modal.show')) return;
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    });
    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function () {
            const modal = this.closest('.modal');
            if (!modal || !window.bootstrap) return;
            const instance = bootstrap.Modal.getInstance(modal);
            if (instance) instance.hide();
        });
    });
    const duplicateModal = document.getElementById('duplicateScanModal');
    if (duplicateModal && window.bootstrap) {
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        const modal = new bootstrap.Modal(duplicateModal, { backdrop: true, focus: true });
        duplicateModal.addEventListener('shown.bs.modal', function () {
            duplicateModal.style.zIndex = '20080';
            const dialog = duplicateModal.querySelector('.modal-dialog');
            if (dialog) dialog.style.zIndex = '20090';
        }, { once: true });
        modal.show();
    }
});
</script>
@endsection






