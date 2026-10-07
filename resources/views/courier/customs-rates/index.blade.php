@extends('layouts.app')

@section('title', 'Customs Rates')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Customs Rates</h1>
        <p class="text-muted mb-0">Manage HS-code duty rates and calculator customs settings.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('courier.customs-rates.template') }}" class="btn btn-outline-secondary"><i class="fas fa-file-csv me-1"></i>Template</a>
        <a href="{{ route('courier.customs-rates.export') }}" class="btn btn-outline-primary"><i class="fas fa-download me-1"></i>Extract</a>
        <a href="{{ route('courier.customs-rates.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Add HS Code</a>
    </div>
</div>

<div class="panel p-4 mb-4">
    <h2 class="h5 mb-3">Calculator Customs Defaults</h2>
    <form method="POST" action="{{ route('courier.customs-rates.settings') }}" class="row g-3">
        @csrf
        @method('PUT')
        <div class="col-md-3"><label class="form-label">Exchange Rate</label><input type="number" step="0.01" min="0" name="customs_exchange_rate" class="form-control" value="{{ old('customs_exchange_rate', $settings['customs_exchange_rate']) }}"></div>
        <div class="col-md-3"><label class="form-label">Company GCT %</label><input type="number" step="0.01" min="0" max="100" name="customs_gct_company_rate" class="form-control" value="{{ old('customs_gct_company_rate', $settings['customs_gct_company_rate']) }}"></div>
        <div class="col-md-3"><label class="form-label">Personal GCT %</label><input type="number" step="0.01" min="0" max="100" name="customs_gct_personal_rate" class="form-control" value="{{ old('customs_gct_personal_rate', $settings['customs_gct_personal_rate']) }}"></div>
        <div class="col-md-3"><label class="form-label">De Minimis USD</label><input type="number" step="0.01" min="0" name="customs_de_minimis_usd" class="form-control" value="{{ old('customs_de_minimis_usd', $settings['customs_de_minimis_usd']) }}"></div>
        <div class="col-md-3"><label class="form-label">Residential CAF JMD</label><input type="number" step="0.01" min="0" name="customs_caf_residential_jmd" class="form-control" value="{{ old('customs_caf_residential_jmd', $settings['customs_caf_residential_jmd']) }}"></div>
        <div class="col-md-3"><label class="form-label">Commercial CAF JMD</label><input type="number" step="0.01" min="0" name="customs_caf_commercial_jmd" class="form-control" value="{{ old('customs_caf_commercial_jmd', $settings['customs_caf_commercial_jmd']) }}"></div>
        <div class="col-md-3"><label class="form-label">Default Duty %</label><input type="number" step="0.01" min="0" max="100" name="customs_default_duty_rate" class="form-control" value="{{ old('customs_default_duty_rate', $settings['customs_default_duty_rate']) }}"></div>
        <div class="col-md-3"><label class="form-label">Insurance %</label><input type="number" step="0.01" min="0" max="100" name="customs_insurance_rate" class="form-control" value="{{ old('customs_insurance_rate', $settings['customs_insurance_rate']) }}"></div>
        <div class="col-md-3"><label class="form-label">SCF %</label><input type="number" step="0.01" min="0" max="100" name="customs_scf_rate" class="form-control" value="{{ old('customs_scf_rate', $settings['customs_scf_rate']) }}"></div>
        <div class="col-md-3"><label class="form-label">ENVL %</label><input type="number" step="0.01" min="0" max="100" name="customs_envl_rate" class="form-control" value="{{ old('customs_envl_rate', $settings['customs_envl_rate']) }}"></div>
        <div class="col-md-3"><label class="form-label">Stamp Duty JMD</label><input type="number" step="0.01" min="0" name="customs_stamp_duty_jmd" class="form-control" value="{{ old('customs_stamp_duty_jmd', $settings['customs_stamp_duty_jmd']) }}"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">Save Defaults</button></div>
    </form>
</div>

<div class="panel p-4 mb-4">
    <form method="POST" action="{{ route('courier.customs-rates.import') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
        @csrf
        <div class="col-lg-5">
            <label class="form-label">Bulk Upload HS-Code CSV</label>
            <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
        </div>
        <div class="col-lg-2"><button class="btn btn-outline-primary w-100">Upload</button></div>
        <div class="col-lg-5 text-muted small">Columns: code, description, duty_rate, is_active. Existing codes are updated during upload.</div>
    </form>
</div>

<div class="panel">
    <div class="p-3 border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Search HS code or description"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Search</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>HS Code</th><th>Description</th><th>Duty Rate</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($rates as $rate)
                <tr>
                    <td class="fw-bold">{{ $rate->code }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($rate->description, 120) }}</td>
                    <td>{{ number_format($rate->duty_rate, 2) }}%</td>
                    <td><span class="badge {{ $rate->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $rate->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('courier.customs-rates.edit', $rate) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('courier.customs-rates.destroy', $rate) }}" class="d-inline" onsubmit="return confirm('Delete this HS code?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No customs HS-code rates yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $rates->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
