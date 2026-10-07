<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Courier</label>
        <select name="tenant_id" class="form-select">
            <option value="">Platform / no courier</option>
            @foreach($tenants as $tenant)
                <option value="{{ $tenant->id }}" @selected(old('tenant_id', optional($ticket ?? null)->tenant_id) == $tenant->id)>{{ $tenant->company_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Requester</label>
        <select name="user_id" class="form-select">
            <option value="">No requester</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected(old('user_id', optional($ticket ?? null)->user_id) == $user->id)>{{ $user->full_name ?: $user->email }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Assigned To</label>
        <select name="assigned_to" class="form-select">
            <option value="">Unassigned</option>
            @foreach($admins as $admin)
                <option value="{{ $admin->id }}" @selected(old('assigned_to', optional($ticket ?? null)->assigned_to) == $admin->id)>{{ $admin->full_name ?: $admin->email }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-select">
            @foreach(['low', 'medium', 'high', 'urgent'] as $priority)
                <option value="{{ $priority }}" @selected(old('priority', optional($ticket ?? null)->priority ?? 'medium') === $priority)>{{ ucfirst($priority) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            @foreach(['open', 'in_progress', 'resolved', 'closed'] as $status)
                <option value="{{ $status }}" @selected(old('status', optional($ticket ?? null)->status ?? 'open') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Subject</label>
        <input type="text" name="subject" class="form-control" value="{{ old('subject', optional($ticket ?? null)->subject) }}" required>
    </div>
    <div class="col-12">
        <label class="form-label">Message</label>
        <textarea name="message" class="form-control" rows="6" required>{{ old('message', optional($ticket ?? null)->message) }}</textarea>
    </div>
    <div class="col-12 d-flex gap-2 pt-3">
        <button class="btn btn-primary">Save Ticket</button>
        <a href="{{ route('superadmin.support.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>
