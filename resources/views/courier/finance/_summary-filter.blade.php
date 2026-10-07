<form method="GET" class="panel p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from', $dateFrom) }}"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to', $dateTo) }}"></div>
        <div class="col-md-6 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a></div>
    </div>
</form>

