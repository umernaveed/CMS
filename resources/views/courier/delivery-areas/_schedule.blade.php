@if(optional($area)->exists)
    @php($dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
    <div class="panel p-4 mt-4" id="schedule-days">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1">Schedule Delivery Days</h2>
                <div class="text-muted small">Set the days and time windows customers can choose for this delivery location.</div>
            </div>
        </div>
        <form id="delivery-slot-form" method="POST" action="{{ route('courier.delivery-areas.slots.store', $area) }}" class="row g-3 align-items-end mb-4">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Day</label>
                <select name="day_of_week" class="form-select" required>
                    @foreach($dayNames as $dayIndex => $dayName)
                        <option value="{{ $dayIndex }}">{{ $dayName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Start Time</label><input type="time" name="start_time" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">End Time</label><input type="time" name="end_time" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Max Requests</label><input type="number" min="1" name="max_requests" class="form-control" value="10" required></div>
            <div class="col-md-1">
                <label class="form-check pb-2">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                    <span class="form-check-label">Active</span>
                </label>
            </div>
            <div class="col-md-2"><button type="submit" form="delivery-slot-form" class="btn btn-primary w-100">Add Day</button></div>
        </form>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Day</th><th>Time</th><th>Max</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($area->timeSlots as $slot)
                    <tr>
                        <td>{{ $dayNames[(int) $slot->day_of_week] ?? 'Day '.$slot->day_of_week }}</td>
                        <td>{{ substr($slot->start_time, 0, 5) }} - {{ substr($slot->end_time, 0, 5) }}</td>
                        <td>{{ number_format($slot->max_requests) }}</td>
                        <td><span class="badge {{ $slot->is_active ? 'badge-soft' : 'bg-secondary' }}">{{ $slot->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('courier.delivery-areas.slots.destroy', [$area, $slot]) }}" class="d-inline" data-confirm="Delete this schedule day?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No schedule days configured yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

