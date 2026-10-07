@extends('layouts.app')

@section('title', 'Manifest Bulk Invoices')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Manifest Bulk Invoices</h1>
        <p class="text-muted mb-0">Review uninvoiced customer packages on {{ $manifest->manifest_number }} before creating invoices.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('courier.manifests.show', $manifest) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Manifest</a>
    </div>
</div>

@if(($bulkPreviewGroups ?? collect())->isEmpty())
    <div class="panel p-4 text-center">
        <h2 class="h5 mb-2">No invoices to create</h2>
        <p class="text-muted mb-3">This manifest has no uninvoiced packages assigned to customers.</p>
        <a href="{{ route('courier.manifests.show', $manifest) }}" class="btn btn-primary">Open Manifest</a>
    </div>
@else
    <form method="POST" action="{{ route('courier.manifests.bulk-invoices.store', $manifest) }}" class="panel">
        @csrf
        <input type="hidden" name="selected_only" value="1">
        <div class="p-4 border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="{{ old('due_date') }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Notes</label>
                    <input name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional note for generated invoices">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:44px;"><input class="form-check-input" type="checkbox" data-bulk-select-all checked></th>
                        <th>Customer</th>
                        <th>Mailbox</th>
                        <th>Packages</th>
                        <th class="text-end">Estimated Package Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bulkPreviewGroups as $group)
                        @php($customer = $group['customer'])
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" checked data-bulk-customer></td>
                            <td>{{ optional($customer->user)->full_name ?: 'Customer' }}</td>
                            <td>{{ $customer->mailbox }}</td>
                            <td>{{ number_format($group['package_count']) }}</td>
                            <td class="text-end">JMD {{ number_format($group['estimated_total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Selected invoice groups</th>
                        <th>{{ number_format($bulkPreviewGroups->sum('package_count')) }}</th>
                        <th class="text-end">JMD {{ number_format($bulkPreviewGroups->sum('estimated_total'), 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="p-4 border-top d-flex flex-wrap justify-content-end gap-2">
            <a href="{{ route('courier.manifests.show', $manifest) }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-outline-primary" name="send_email" value="0"><i class="fas fa-file-circle-plus me-1"></i>Create Only</button>
            <button class="btn btn-success" name="send_email" value="1"><i class="fas fa-paper-plane me-1"></i>Create & Send</button>
        </div>
    </form>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('[data-bulk-select-all]')?.addEventListener('change', function () {
        document.querySelectorAll('[data-bulk-customer]').forEach(input => input.checked = this.checked);
    });
});
</script>
@endsection