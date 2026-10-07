@php
    $items = [
        'users' => ['label' => 'Users', 'icon' => 'fa-user-tie'],
        'customers' => ['label' => 'Customers', 'icon' => 'fa-users'],
        'packages_month' => ['label' => 'Packages/mo', 'icon' => 'fa-box'],
        'outlets' => ['label' => 'Outlets', 'icon' => 'fa-store'],
    ];
@endphp

<div class="row g-2">
    @foreach($items as $key => $meta)
        @php
            $item = $usage[$key] ?? ['used' => 0, 'limit' => 0, 'percent' => 0];
            $limit = $item['limit'];
            $percent = $item['percent'];
            $bar = $percent >= 90 ? '#dc2626' : ($percent >= 75 ? '#f59e0b' : '#135fd2');
        @endphp
        <div class="col-md-3 col-6">
            <div class="border rounded p-2 h-100">
                <div class="small text-muted"><i class="fas {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}</div>
                <div class="fw-bold">{{ number_format($item['used']) }} / {{ $limit === null ? 'Unlimited' : number_format($limit) }}</div>
                @if($limit !== null)
                    <div class="metric-progress mt-2" style="height:5px"><span style="width: {{ $percent }}%; background: {{ $bar }}"></span></div>
                @else
                    <div class="text-muted small mt-1">No cap</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
