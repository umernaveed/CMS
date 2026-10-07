<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PackageStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageStatusController extends Controller
{
    public function index()
    {
        $statuses = PackageStatus::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('packages')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage(request(), 15));

        return view('courier.statuses.index', compact('statuses'));
    }

    public function create()
    {
        return view('courier.statuses.create', ['status' => null]);
    }

    public function show(PackageStatus $status)
    {
        $this->authorizeTenantStatus($status);
        $status->loadCount('packages');

        return view('courier.statuses.show', compact('status'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $tenantId = auth()->user()->tenant_id;

        if (! empty($data['is_default'])) {
            PackageStatus::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $status = PackageStatus::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'slug' => $this->uniqueSlug($tenantId, $data['name']),
            'is_default' => ! empty($data['is_default']),
            'is_active' => ! empty($data['is_active']),
        ]));

        ActivityLog::record('created', 'package_statuses', 'Created package status '.$status->name, ['status_id' => $status->id]);

        return redirect()->route('courier.statuses.index')->with('success', 'Package status created successfully.');
    }

    public function edit(PackageStatus $status)
    {
        $this->authorizeTenantStatus($status);

        return view('courier.statuses.edit', compact('status'));
    }

    public function update(Request $request, PackageStatus $status)
    {
        $this->authorizeTenantStatus($status);
        $data = $this->validated($request);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $tenantId = auth()->user()->tenant_id;

        if (! empty($data['is_default'])) {
            PackageStatus::where('tenant_id', $tenantId)->whereKeyNot($status->id)->update(['is_default' => false]);
        }

        $status->update(array_merge($data, [
            'slug' => $this->uniqueSlug($tenantId, $data['name'], $status->id),
            'is_default' => ! empty($data['is_default']),
            'is_active' => ! empty($data['is_active']),
        ]));

        ActivityLog::record('updated', 'package_statuses', 'Updated package status '.$status->name, ['status_id' => $status->id]);

        return redirect()->route('courier.statuses.index')->with('success', 'Package status updated successfully.');
    }

    public function destroy(PackageStatus $status)
    {
        $this->authorizeTenantStatus($status);

        if ($status->packages()->exists()) {
            return back()->withErrors(['status' => 'This status is used by packages and cannot be deleted. Deactivate it instead.']);
        }

        $name = $status->name;
        $status->delete();

        ActivityLog::record('deleted', 'package_statuses', 'Deleted package status '.$name);

        return redirect()->route('courier.statuses.index')->with('success', 'Package status deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:50',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function uniqueSlug(int $tenantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'status';
        $slug = $base;
        $counter = 2;

        while (PackageStatus::where('tenant_id', $tenantId)->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function authorizeTenantStatus(PackageStatus $status): void
    {
        abort_if($status->tenant_id !== auth()->user()->tenant_id, 404);
    }
}
