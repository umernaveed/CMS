@extends('layouts.app')

@section('title', 'Add Authorized User')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Add Authorized User</h1>
    <p class="text-muted mb-0">Add someone who can collect packages on your behalf.</p>
</div>

<div class="panel p-4" style="max-width: 600px;">
    <form method="POST" action="{{ route('customer.authorized-users.store') }}">
        @csrf

        <div class="mb-3">
            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('full_name') is-invalid @enderror"
                   id="full_name" name="full_name" value="{{ old('full_name') }}" required>
            @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                   id="phone" name="phone" value="{{ old('phone') }}">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="identification_type" class="form-label">ID Type</label>
                <select class="form-select @error('identification_type') is-invalid @enderror"
                        id="identification_type" name="identification_type">
                    <option value="">Select ID type</option>
                    <option value="passport" @selected(old('identification_type') == 'passport')>Passport</option>
                    <option value="drivers_license" @selected(old('identification_type') == 'drivers_license')>Driver's License</option>
                    <option value="id_card" @selected(old('identification_type') == 'id_card')>ID Card</option>
                    <option value="other" @selected(old('identification_type') == 'other')>Other</option>
                </select>
                @error('identification_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="identification_number" class="form-label">ID Number</label>
                <input type="text" class="form-control @error('identification_number') is-invalid @enderror"
                       id="identification_number" name="identification_number" value="{{ old('identification_number') }}">
                @error('identification_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control @error('notes') is-invalid @enderror"
                      id="notes" name="notes" rows="3" placeholder="Any additional notes about this person">{{ old('notes') }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Add Authorized User
            </button>
            <a href="{{ route('customer.authorized-users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection