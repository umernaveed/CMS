@extends('layouts.app')

@section('title', 'Profile')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Profile</h1><p class="text-muted mb-0">Update your contact details and password.</p></div>
<form method="POST" action="{{ route('profile.update') }}" class="panel p-4" enctype="multipart/form-data">
    @csrf
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label">Profile Photo</label>
            <input type="file" name="profile_photo" class="form-control" accept="image/png,image/jpeg,image/webp">
            <div class="form-text">PNG, JPG, or WebP up to 2 MB.</div>
        </div>
        @if($user->image)
            <div class="col-md-12">
                <label class="form-label">Current Photo</label>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <img src="{{ asset($user->image) }}" alt="Profile photo" class="rounded-circle" style="width: 96px; height: 96px; object-fit: cover; border: 1px solid #e2e8f0;">
                    <button type="submit" name="remove_profile_photo" value="1" class="btn btn-outline-danger">
                        <i class="fas fa-trash me-1"></i>Remove Photo
                    </button>
                </div>
            </div>
        @endif
        <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="{{ old('full_name', $user->full_name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="{{ $user->email }}" disabled></div>
        <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
        <div class="col-md-4"><label class="form-label">TRN/SSN</label><input name="trn" class="form-control" value="{{ old('trn', $user->trn) }}" placeholder="Taxpayer Registration Number or Social Security Number"></div>
        <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', $user->city) }}"></div>
        <div class="col-md-4"><label class="form-label">Parish</label><input name="parish" class="form-control" value="{{ old('parish', $user->parish) }}"></div>
        <div class="col-12"><label class="form-label">Address</label><input name="address_line1" class="form-control" value="{{ old('address_line1', $user->address_line1) }}"></div>
        @if($user->tenant && $user->user_type === 'tenant_admin')
            <div class="col-12"><hr><h2 class="h6">Company Branding</h2></div>
            <div class="col-md-6">
                <label class="form-label">Courier Logo</label>
                <input type="file" name="tenant_logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                <div class="form-text">PNG, JPG, or WebP up to 2 MB.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Current Logo</label>
                <div>
                    @if($user->tenant->logo)
                        <img src="{{ asset($user->tenant->logo) }}" alt="Current logo" style="height: 54px; max-width: 180px; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; background: #fff;">
                    @else
                        <span class="text-muted">No logo uploaded yet.</span>
                    @endif
                </div>
            </div>
        @endif
        <div class="col-12"><hr><h2 class="h6">Change Password</h2></div>
        <div class="col-md-4"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="password" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control"></div>
        <div class="col-12 pt-3"><button class="btn btn-primary">Save Profile</button></div>
    </div>
</form>
@endsection
