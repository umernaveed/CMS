<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">{{ $title }}</h1><p class="text-muted mb-0">{{ $subtitle }}</p></div>
    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('courier.finance.export', array_merge(['section' => $section], request()->query())) }}" class="btn btn-outline-primary"><i class="fas fa-file-export me-1"></i>Export</a>
        <a href="{{ route('courier.finance.print', array_merge(['section' => $section], request()->query())) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <a href="{{ route('courier.finance.download', array_merge(['section' => $section], request()->query())) }}" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Download</a>
    </div>
</div>
