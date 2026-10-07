@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $selectedPackageIds = collect(old('package_ids', $selectedPackageIds ?? []))->map(fn($id) => (int) $id)->all();
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="panel p-4 mb-4">
            <div class="row align-items-end">
                <div class="col-lg-10">
                    <h2 class="h5 mb-3 register-section-title"><i class="fas fa-file-lines"></i>Manifest Details</h2>
                </div>
                <div class="col-lg-2 text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#manifestDetailsCollapse" aria-expanded="false">
                        <i class="fas fa-chevron-up"></i> Collapse
                    </button>
                </div>
            </div>
            <div class="collapse show" id="manifestDetailsCollapse">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Manifest Number</label>
                        <input name="manifest_number" class="form-control @error('manifest_number') is-invalid @enderror" value="{{ old('manifest_number', $manifest->manifest_number) }}" required>
                        @error('manifest_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cargo Company</label>
                        <input name="cargo_company" class="form-control" value="{{ old('cargo_company', $manifest->cargo_company) }}" list="manifestCargoCompanies">
                        <datalist id="manifestCargoCompanies">
                            @foreach(($cargoCompanies ?? collect()) as $company)
                                <option value="{{ $company }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Departure Date</label>
                        <input type="date" name="date_of_departure" class="form-control" value="{{ old('date_of_departure', optional($manifest->date_of_departure)->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Customs Office</label>
                        <select name="customs_office_id" class="form-select">
                            <option value="">Select customs office</option>
                            @foreach($customsOffices as $office)
                                <option value="{{ $office->id }}" @selected(old('customs_office_id', $manifest->customs_office_id) == $office->id)>{{ $office->name }} @if($office->code)({{ $office->code }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Loading Port</label>
                        <select name="loading_port_id" class="form-select">
                            <option value="">Select loading port</option>
                            @foreach($loadingPorts as $port)
                                <option value="{{ $port->id }}" @selected(old('loading_port_id', $manifest->loading_port_id) == $port->id)>{{ $port->name }} @if($port->code)({{ $port->code }})@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Shipping Agent Code</label>
                        <input name="shipping_agent_code" class="form-control" value="{{ old('shipping_agent_code', $manifest->shipping_agent_code) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Master Air Bill</label>
                        <input name="master_air_bill" class="form-control" value="{{ old('master_air_bill', $manifest->master_air_bill) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Voyage Number</label>
                        <input name="voyage_number" class="form-control" value="{{ old('voyage_number', $manifest->voyage_number) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Carrier Code</label>
                        <input name="carrier_code" class="form-control" value="{{ old('carrier_code', $manifest->carrier_code) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Manifest File</label>
                        <input type="file" name="manifest_file" class="form-control" accept=".pdf,.csv,.txt,.xlsx,.xls,.jpg,.jpeg,.png">
                        @if($manifest->file_path)
                            <small><a href="{{ route('courier.manifests.download', $manifest) }}">Download current file</a></small>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Exporter Name</label>
                        <input name="exporter_name" class="form-control" value="{{ old('exporter_name', $manifest->exporter_name) }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Exporter Address</label>
                        <textarea name="exporter_address" rows="2" class="form-control">{{ old('exporter_address', $manifest->exporter_address) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="panel p-4">
            <h2 class="h5 mb-3 register-section-title"><i class="fas fa-boxes-stacked"></i>Packages</h2>
            <p class="text-muted small">Choose packages that should travel on this manifest. Packages already assigned to another manifest are hidden.</p>
            <div class="row g-2 mb-3">
                <div class="col-12">
                    <label class="form-label">Scan HAWB or Tracking Number</label>
                    <input id="manifest-package-scan" type="text" class="form-control" placeholder="Scan HAWB or carrier tracking number and press Enter">
                    <div id="manifest-package-scan-status" class="small text-muted mt-1">Use a barcode scanner or type a HAWB / tracking number and press Enter.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Search</label>
                    <input id="manifest-package-search" type="search" class="form-control" placeholder="HAWB, tracking, customer, mailbox, merchant">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Date From</label>
                    <input id="manifest-package-date-from" type="date" class="form-control">
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Date To</label>
                    <input id="manifest-package-date-to" type="date" class="form-control">
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Branch</label>
                    <select id="manifest-package-branch" class="form-select">
                        <option value="">All branches</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" id="manifest-apply-filters" class="btn btn-sm btn-primary">Apply Filters</button>
                <button type="button" id="manifest-select-all" class="btn btn-sm btn-outline-primary">Select All</button>
                <button type="button" id="manifest-clear-selection" class="btn btn-sm btn-outline-secondary">Unselect All</button>
                <button type="button" id="manifest-reset-filters" class="btn btn-sm btn-outline-dark">Reset Filters</button>
            </div>
            <style>
                .manifest-package-picker { overflow: auto; max-height: 480px; }
                .manifest-package-grid { min-width: 980px; }
                .manifest-package-grid-row {
                    display: grid;
                    grid-template-columns: 44px minmax(138px, .95fr) minmax(250px, 1.55fr) minmax(180px, 1.1fr) 90px 86px minmax(128px, .85fr);
                    align-items: center;
                    gap: 10px;
                    padding: 10px 12px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .manifest-package-grid-row > span,
                .manifest-package-grid-row > strong {
                    min-width: 0;
                }
                .manifest-package-grid-row [data-package-tracking],
                .manifest-package-grid-row [data-package-customer],
                .manifest-package-grid-row [data-package-branch] {
                    overflow-wrap: anywhere;
                    word-break: break-word;
                    line-height: 1.25;
                }
                .manifest-package-grid-row [data-package-branch] {
                    font-size: 13px;
                }
                .manifest-package-grid-head {
                    position: sticky;
                    top: 0;
                    z-index: 2;
                    background: #f8fafc;
                    color: #475569;
                    font-size: 12px;
                    font-weight: 700;
                    text-transform: uppercase;
                }
                .manifest-package-row { cursor: pointer; background: #fff; }
                .manifest-package-row:hover { background: #f8fbff; }
                .manifest-package-row.is-selected { background: #eff6ff; }
            </style>
            <div class="manifest-package-picker border rounded">
                <div class="manifest-package-grid">
                    <div class="manifest-package-grid-row manifest-package-grid-head">
                        <span></span><span>HAWB</span><span>Carrier Tracking</span><span>Customer</span><span>Freight</span><span>Weight / Volume</span><span>Branch</span>
                    </div>
                    @forelse($availablePackages as $package)
                        <label class="manifest-package-row manifest-package-grid-row" data-identifiers="{{ strtolower(trim($package->tracking_number.' '.$package->supplier_tracking)) }}" data-search="{{ strtolower($package->tracking_number.' '.$package->supplier_tracking.' '.optional(optional($package->customer)->user)->full_name.' '.($package->mailbox ?: '').' '.($package->merchant ?: '')) }}" data-date="{{ optional($package->date_received)->format('Y-m-d') }}" data-branch="{{ $package->outlet_id }}">
                            <input class="form-check-input package-checkbox" type="checkbox" name="package_ids[]" value="{{ $package->id }}" @checked(in_array($package->id, $selectedPackageIds))>
                            <strong>{{ $package->tracking_number }}</strong>
                            <span data-package-tracking>{{ $package->supplier_tracking ?: '-' }}</span>
                            <span data-package-customer>{{ optional(optional($package->customer)->user)->full_name ?? 'Unassigned' }}<br><small class="text-muted">{{ $package->mailbox ?: '-' }}</small></span>
                            <span>{{ ucfirst($package->freight_type) }}</span>
                            <span>{{ $package->measureDisplay() }}</span>
                            <span data-package-branch>{{ optional($package->outlet)->name ?: 'Main' }}</span>
                        </label>
                    @empty
                        <div class="text-muted small text-center py-4">No warehouse packages are awaiting an in-house manifest.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        function initManifestPackageFilters() {
            const searchInput = document.getElementById('manifest-package-search');
            const dateFromInput = document.getElementById('manifest-package-date-from');
            const dateToInput = document.getElementById('manifest-package-date-to');
            const branchSelect = document.getElementById('manifest-package-branch');
            const scanInput = document.getElementById('manifest-package-scan');
            const scanStatus = document.getElementById('manifest-package-scan-status');
            const applyFiltersButton = document.getElementById('manifest-apply-filters');
            const selectAllButton = document.getElementById('manifest-select-all');
            const clearSelectionButton = document.getElementById('manifest-clear-selection');
            const resetFiltersButton = document.getElementById('manifest-reset-filters');
            const packageRows = Array.from(document.querySelectorAll('.manifest-package-row'));

            if (!searchInput || !dateFromInput || !dateToInput || !branchSelect || !scanInput || !scanStatus || !applyFiltersButton || !selectAllButton || !clearSelectionButton || !resetFiltersButton) {
                return;
            }

            function applyFilters() {
                const searchValue = (searchInput.value || '').trim().toLowerCase();
                const dateFrom = dateFromInput.value;
                const dateTo = dateToInput.value;
                const branchValue = branchSelect.value;

                packageRows.forEach(function (row) {
                    const searchText = (row.dataset.search || '').toLowerCase();
                    const rowDate = row.dataset.date || '';
                    const rowBranch = row.dataset.branch || '';
                    const checkbox = row.querySelector('.package-checkbox');

                    const matchesSearch = searchValue === '' || searchText.includes(searchValue);
                    const matchesBranch = branchValue === '' || rowBranch === branchValue;
                    const matchesFrom = dateFrom === '' || (rowDate !== '' && rowDate >= dateFrom);
                    const matchesTo = dateTo === '' || (rowDate !== '' && rowDate <= dateTo);

                    row.style.display = (matchesSearch && matchesBranch && matchesFrom && matchesTo) ? '' : 'none';
                });
            }

            function updateSelectedVisibility() {
                packageRows.forEach(function (row) {
                    const checkbox = row.querySelector('.package-checkbox');
                    row.classList.toggle('is-selected', Boolean(checkbox?.checked));
                });
            }

            function normalizeScannedIdentifier(value) {
                return (value || '').trim().toLowerCase().replace(/^ol-/, '');
            }

            function findRowByTracking(tracking) {
                const normalized = normalizeScannedIdentifier(tracking);
                if (!normalized) {
                    return null;
                }

                return packageRows.find(function (row) {
                    return (row.dataset.identifiers || '')
                        .split(/\s+/)
                        .some(function (identifier) {
                            return normalizeScannedIdentifier(identifier) === normalized;
                        });
                }) || null;
            }

            function processScanInput() {
                const rawValue = scanInput.value || '';
                const scannedValues = rawValue
                    .split(/[\r\n,;]+/)
                    .map(function (value) { return value.trim(); })
                    .filter(function (value) { return value !== ''; });

                if (scannedValues.length === 0) {
                    scanStatus.textContent = 'Scan or enter a tracking number and press Enter.';
                    return;
                }

                const missing = [];
                const duplicate = [];
                let addedCount = 0;

                scannedValues.forEach(function (value) {
                    const row = findRowByTracking(value);
                    if (!row) {
                        missing.push(value);
                        return;
                    }

                    const checkbox = row.querySelector('.package-checkbox');
                    if (checkbox) {
                        if (checkbox.checked) {
                            duplicate.push(value);
                            return;
                        }
                        checkbox.checked = true;
                    }
                    row.classList.add('is-selected');
                    addedCount++;
                });

                if (addedCount > 0) {
                    scanStatus.textContent = 'Added ' + addedCount + ' scanned package(s).';
                    if (typeof window.playAppActionSound === 'function') {
                        window.playAppActionSound('check in');
                    }
                }

                if (missing.length > 0) {
                    scanStatus.textContent += (scanStatus.textContent ? ' ' : '') + 'Not found: ' + missing.join(', ');
                    if (typeof window.playAppActionSound === 'function') {
                        window.playAppActionSound('failed');
                    }
                }

                if (duplicate.length > 0) {
                    scanStatus.textContent += (scanStatus.textContent ? ' ' : '') + 'Duplicate: ' + duplicate.join(', ');
                    if (typeof window.playAppActionSound === 'function') {
                        window.playAppActionSound('duplicate');
                    }
                }

                scanInput.value = '';
            }

            function selectVisible(checked) {
                packageRows.forEach(function (row) {
                    if (row.style.display !== 'none') {
                        const checkbox = row.querySelector('.package-checkbox');
                        if (checkbox) {
                            checkbox.checked = checked;
                        }
                    }
                });
                updateSelectedVisibility();
            }

            searchInput.addEventListener('input', applyFilters);
            dateFromInput.addEventListener('change', applyFilters);
            dateToInput.addEventListener('change', applyFilters);
            branchSelect.addEventListener('change', applyFilters);
            scanInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    processScanInput();
                }
            });
            applyFiltersButton.addEventListener('click', applyFilters);

            selectAllButton.addEventListener('click', function () {
                selectVisible(true);
            });

            clearSelectionButton.addEventListener('click', function () {
                const checkboxes = Array.from(document.querySelectorAll('.package-checkbox'));
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });
                applyFilters();
            });

            resetFiltersButton.addEventListener('click', function () {
                searchInput.value = '';
                dateFromInput.value = '';
                dateToInput.value = '';
                branchSelect.value = '';
                applyFilters();
            });

                document.querySelectorAll('.package-checkbox').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    updateSelectedVisibility();
                    applyFilters();
                });
            });

            applyFilters();
            updateSelectedVisibility();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initManifestPackageFilters);
        } else {
            initManifestPackageFilters();
        }
    })();
</script>

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('courier.manifests.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $button ?? 'Save Manifest' }}</button>
</div>

