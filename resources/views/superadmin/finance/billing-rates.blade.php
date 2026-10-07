@extends('layouts.app')

@section('title', 'Billing Rates')
@section('page_title', 'Billing Rates')
@section('breadcrumb', 'Finances / Billing Rates')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Billing Rates Management</h1>
        <p class="text-muted mb-0">Manage hosting service fees, website development fees, onboarding fees, and custom System Admin charges.</p>
    </div>
</div>

<div class="panel p-4 mb-4">
    <h2 class="h5 mb-3">Add Additional Charge</h2>
    <form method="POST" action="{{ route('superadmin.finance.billing-rates.store') }}" class="row g-3">
        @csrf
        <div class="col-md-3">
            <label class="form-label">Charge Name</label>
            <input name="name" class="form-control" placeholder="API Integration Setup" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Amount USD</label>
            <input name="amount_usd" type="number" step="0.01" min="0" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Amount JMD</label>
            <input name="amount_jmd" type="number" step="0.01" min="0" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Notes</label>
            <input name="notes" class="form-control">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Save</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="p-3 border-bottom">
        <h2 class="h5 mb-0">Configured Charges</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Charge</th>
                    <th>Type</th>
                    <th>USD</th>
                    <th>JMD</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($charges as $charge)
                <tr>
                    <td colspan="7" class="p-0 border-0">
                        <form method="POST" action="{{ route('superadmin.finance.billing-rates.update', $charge) }}" class="row g-2 align-items-center p-3 border-bottom m-0">
                            @csrf
                            @method('PUT')
                            <div class="col-lg-3 col-md-6">
                                <input name="name" class="form-control" value="{{ $charge->name }}" required>
                            </div>
                            <div class="col-lg-1 col-md-6">
                                <span class="badge {{ $charge->is_default ? 'bg-primary' : 'bg-secondary' }}">{{ $charge->is_default ? 'Default' : 'Custom' }}</span>
                            </div>
                            <div class="col-lg-1 col-md-6">
                                <input name="amount_usd" type="number" step="0.01" min="0" class="form-control" value="{{ $charge->amount_usd }}">
                            </div>
                            <div class="col-lg-1 col-md-6">
                                <input name="amount_jmd" type="number" step="0.01" min="0" class="form-control" value="{{ $charge->amount_jmd }}">
                            </div>
                            <div class="col-lg-1 col-md-6">
                                <label class="form-check mb-0">
                                    <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($charge->is_active)>
                                    <span class="form-check-label">Active</span>
                                </label>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <input name="notes" class="form-control" value="{{ $charge->notes }}">
                            </div>
                            <div class="col-lg-2 col-md-12 text-end">
                                <button class="btn btn-sm btn-outline-primary"><i class="fas fa-save me-1"></i>Save</button>
                            </div>
                        </form>
                        @unless($charge->is_default)
                            <form method="POST" action="{{ route('superadmin.finance.billing-rates.destroy', $charge) }}" class="px-3 pb-3 text-end" onsubmit="return confirm('Delete this billing charge?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No billing charges found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $charges->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
