@php
    $tableId = $tableId ?? 'bulkPackageTable'.\Illuminate\Support\Str::random(6);
    $allowIntakeRemoval = $allowIntakeRemoval ?? false;
    $activeShipment = $activeShipment ?? null;
@endphp

<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
    <input class="form-control form-control-sm" style="max-width:320px" data-table-search="{{ $tableId }}" placeholder="Search tracking, HAWB, courier, customer">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-table-export="{{ $tableId }}"><i class="fas fa-file-export me-1"></i>Export CSV</button>
</div>

<div class="table-responsive">
    <table class="table align-middle mb-0" id="{{ $tableId }}">
        <thead>
            <tr>
                <th>Tracking No.</th>
                <th>HAWB</th>
                <th>Courier</th>
                <th>Customer</th>
                <th class="text-end">Measure</th>
                <th class="text-end">Total Cost</th>
                <th>Status / Location</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($packages as $package)
                @php
                    $weight = (float) $package->weight;
                    $isVolume = in_array($package->weight_unit, ['cuft', 'cbm'], true);
                    $kg = $package->weight_unit === 'lb' ? $weight * 0.45359237 : $weight;
                    $lb = $package->weight_unit === 'kg' ? $weight * 2.20462262 : $weight;
                    $cuft = $package->weight_unit === 'cbm' ? $weight * 35.3147 : $weight;
                    $cbm = $package->weight_unit === 'cuft' ? $weight / 35.3147 : $weight;
                    $daysInHouse = $package->checked_in_at ? now()->diffInDays($package->checked_in_at) : null;
                    $statusBadge = $package->checked_out_at ? 'success' : (($daysInHouse !== null && $daysInHouse > 7) ? 'danger' : 'info');
                    $statusText = $package->checked_out_at ? 'Checked out' : ucfirst(str_replace('_', ' ', $package->location_status ?: 'in_house'));
                    $supplierTracking = data_get($package, 'courierPackage.supplier_tracking') ?: $package->tracking_number;
                    $hawb = data_get($package, 'courierPackage.tracking_number');
                    if ($hawb && strtoupper(trim((string) $hawb)) === strtoupper(trim((string) $supplierTracking))) {
                        $hawb = null;
                    }
                @endphp
                <tr>
                    <td>
                        <code>{{ $supplierTracking }}</code><br>
                        <span class="text-muted small">{{ optional($package->shipment)->master_number }}</span>
                    </td>
                    <td>{{ $hawb ?: '-' }}</td>
                    <td>{{ optional($package->tenant)->company_name ?: '-' }}</td>
                    <td>{{ data_get($package, 'courierPackage.customer.user.name') ?: data_get($package, 'courierPackage.mailbox') ?: '-' }}</td>
                    <td class="text-end">
                        @if($package->manifest)
                            <form method="POST" action="{{ route('superadmin.logistics.packages.update', [$package->manifest, $package]) }}" class="d-flex justify-content-end gap-1">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="tracking_number" value="{{ $supplierTracking }}">
                                <input type="hidden" name="description" value="{{ $package->description }}">
                                <input type="number" step="0.01" min="0" name="weight" value="{{ $package->weight }}" class="form-control form-control-sm" style="width:90px">
                                <select name="weight_unit" class="form-select form-select-sm" style="width:82px">
                                    <option value="lb" @selected($package->weight_unit === 'lb')>lb</option>
                                    <option value="kg" @selected($package->weight_unit === 'kg')>kg</option>
                                    <option value="cuft" @selected($package->weight_unit === 'cuft')>cu.ft</option>
                                    <option value="cbm" @selected($package->weight_unit === 'cbm')>CBM</option>
                                </select>
                                <button class="btn btn-sm btn-outline-secondary" title="Save measure"><i class="fas fa-save"></i></button>
                            </form>
                        @else
                            {{ $isVolume ? number_format($cuft, 2).' cu.ft / '.number_format($cbm, 3).' CBM' : number_format($lb, 2).' lbs / '.number_format($kg, 2).' kg' }}
                        @endif
                    </td>
                    <td class="text-end">JMD {{ number_format((float) $package->amount, 2) }}</td>
                    <td>
                        <span class="badge bg-{{ $statusBadge }}">{{ $statusText }}</span>
                        @if($daysInHouse !== null)
                            <div class="small {{ $daysInHouse > 7 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $daysInHouse }} day(s) in house</div>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($package->manifest)
                            <div class="btn-group">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('superadmin.logistics.show', $package->manifest) }}">Manage</a>
                                <form method="POST" action="{{ route('superadmin.logistics.packages.unknown-room', [$package->manifest, $package]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning" title="Move to Unknown Room"><i class="fas fa-random"></i></button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.logistics.packages.destroy', [$package->manifest, $package]) }}" onsubmit="return confirm('Delete this package from the shipment intake?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        @else
                            @if($allowIntakeRemoval && $activeShipment && (int) $package->shipment_id === (int) $activeShipment->id)
                                <form method="POST" action="{{ route('superadmin.logistics.shipments.packages.remove', [$activeShipment, $package]) }}" onsubmit="return confirm('Remove this package from this shipment intake and return it to the main logistics package room?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-undo me-1"></i>Return to Package Room</button>
                                </form>
                            @else
                                <span class="text-muted small">No manifest</span>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">{{ $empty ?? 'No packages found.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>


