@extends('layouts.app')

@section('title', 'Manifests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Manifests</h1>
        <p class="text-muted mb-0">Build cargo manifests, attach packages, submit shipments, and export manifest details.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('courier.manifests.export-list', request()->query()) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Export</a>
        <a href="{{ route('courier.manifests.print-list', request()->query()) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('courier.manifests.template') }}" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Template</a>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#bulkManifestUpload"><i class="fas fa-upload me-1"></i>Bulk Upload</button>
        <a href="{{ route('courier.manifests.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Manifest</a>
    </div>
</div>

<form method="GET" action="{{ route('courier.manifests.index') }}" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6"><label class="form-label">Search</label><input name="q" class="form-control" value="{{ request('q') }}" placeholder="Manifest, cargo, bill, voyage"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['draft','submitted','approved','rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Customs</label><select name="customs_office_id" class="form-select"><option value="">All</option>@foreach($customsOffices as $office)<option value="{{ $office->id }}" @selected(request('customs_office_id') == $office->id)>{{ $office->name }}</option>@endforeach</select></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Loading Port</label><select name="loading_port_id" class="form-select"><option value="">All</option>@foreach($loadingPorts as $port)<option value="{{ $port->id }}" @selected(request('loading_port_id') == $port->id)>{{ $port->name }}</option>@endforeach</select></div>
        <div class="col-lg-1 col-md-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
        <div class="col-lg-1 col-md-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
        <div class="col-lg-1 col-md-6 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-filter"></i></button><a href="{{ route('courier.manifests.index') }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

<div class="collapse mb-3" id="bulkManifestUpload">
    <form method="POST" action="{{ route('courier.manifests.import') }}" enctype="multipart/form-data" class="panel p-3">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-8"><label class="form-label">CSV File</label><input type="file" name="file" class="form-control" accept=".csv,.txt" required></div>
            <div class="col-md-4 d-flex gap-2"><button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button><a href="{{ route('courier.manifests.template') }}" class="btn btn-outline-secondary">Download Template</a></div>
        </div>
    </form>
</div>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Manifest</th><th>Cargo</th><th>Departure</th><th>Packages</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($manifests as $manifest)
                <tr>
                    <td><strong>{{ $manifest->manifest_number }}</strong><br><small class="text-muted">{{ optional($manifest->loadingPort)->name ?: 'No loading port' }}</small></td>
                    <td>{{ $manifest->cargo_company ?: '-' }}<br><small class="text-muted">{{ optional($manifest->customsOffice)->name ?: 'No customs office' }}</small></td>
                    <td>{{ optional($manifest->date_of_departure)->format('M d, Y') ?? '-' }}</td>
                    <td>{{ $manifest->items_count }}</td>
                    <td><span class="badge badge-soft">{{ ucfirst($manifest->status) }}</span></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('courier.manifests.show', $manifest) }}"><i class="fas fa-up-right-from-square me-2"></i>Open</a></li>
                                @if($manifest->status === 'draft')
                                    <li><a class="dropdown-item" href="{{ route('courier.manifests.edit', $manifest) }}"><i class="fas fa-pen me-2"></i>Modify</a></li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="{{ route('courier.manifests.export', $manifest) }}"><i class="fas fa-file-export me-2"></i>Export</a></li>
                                <li><a class="dropdown-item" href="{{ route('courier.manifests.print', $manifest) }}" target="_blank"><i class="fas fa-print me-2"></i>Print</a></li>
                                <li><a class="dropdown-item" href="{{ route('courier.manifests.download', $manifest) }}"><i class="fas fa-download me-2"></i>Download</a></li>
                                <li><hr class="dropdown-divider"></li>
                                @if($manifest->items_count > 0)
                                    <li>
                                        <div class="dropdown-item-text px-3 py-2">
                                            <form method="POST" action="{{ route('courier.manifests.packages.update-status', $manifest) }}">
                                                @csrf
                                                <label class="form-label small mb-2">Update package status</label>
                                                <div class="input-group input-group-sm mb-2">
                                                    <select name="status_id" class="form-select" required>
                                                        <option value="">Select status</option>
                                                        @foreach($statuses as $status)
                                                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-primary">Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                @endif
                                @if($manifest->status === 'draft' && $manifest->items_count === 0)
                                    <li>
                                        <form method="POST" action="{{ route('courier.manifests.destroy', $manifest) }}" data-confirm="Delete this manifest?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit"><i class="fas fa-trash me-2"></i>Delete</button>
                                        </form>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No manifests created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $manifests->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
