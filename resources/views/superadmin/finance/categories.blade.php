@extends('layouts.app')
@section('title', 'Ledger Categories')
@section('page_title', 'Ledger Categories')
@section('breadcrumb', 'Finances / Ledger Categories')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Ledger Categories</h1>
        <p class="text-muted mb-0">Manage the categories available on Expense & Income entries.</p>
    </div>
    <a href="{{ route('superadmin.finance.entries') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Expense & Income</a>
</div>

<form method="POST" action="{{ route('superadmin.finance.categories.store') }}" class="panel p-4 mb-4">
    @csrf
    <h2 class="h5 mb-3">Add Category</h2>
    <div class="row g-3 align-items-end">
        <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select" required><option value="income">Income</option><option value="expense">Expense</option></select></div>
        <div class="col-md-3"><label class="form-label">Category Name</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
        <div class="col-md-5"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ old('description') }}"></div>
        <div class="col-md-1"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="newCategoryActive" checked><label class="form-check-label" for="newCategoryActive">Active</label></div></div>
        <div class="col-md-1"><button class="btn btn-primary w-100"><i class="fas fa-save"></i></button></div>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Type</th><th>Name</th><th>Description</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td><span class="badge {{ $category->type === 'income' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($category->type) }}</span></td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->description ?: '-' }}</td>
                    <td><span class="badge {{ $category->is_active ? 'bg-primary' : 'bg-secondary' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#categoryEdit{{ $category->id }}"><i class="fas fa-pen me-1"></i>Edit</button>
                    </td>
                </tr>
                <tr class="collapse" id="categoryEdit{{ $category->id }}">
                    <td colspan="5" class="bg-light">
                        <form method="POST" action="{{ route('superadmin.finance.categories.update', $category) }}" class="row g-2 align-items-end p-2">
                            @csrf
                            @method('PUT')
                            <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select" required><option value="income" @selected($category->type === 'income')>Income</option><option value="expense" @selected($category->type === 'expense')>Expense</option></select></div>
                            <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $category->name }}" required></div>
                            <div class="col-md-4"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ $category->description }}"></div>
                            <div class="col-md-1"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="categoryActive{{ $category->id }}" @checked($category->is_active)><label class="form-check-label" for="categoryActive{{ $category->id }}">Active</label></div></div>
                            <div class="col-md-1"><button class="btn btn-primary w-100"><i class="fas fa-save"></i></button></div>
                        </form>
                        <form method="POST" action="{{ route('superadmin.finance.categories.destroy', $category) }}" class="px-2 pb-2 text-end" data-confirm="Delete this category?">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No categories found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $categories->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
