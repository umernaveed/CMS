<?php

namespace App\Http\Controllers;

use App\Models\PreAlert;
use App\Models\PreAlertReceipt;
use App\Models\SystemSetting;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerPreAlertController extends Controller
{
    public function index(Request $request)
    {
        $customer = auth()->user()->customer;
        abort_if(! $customer, 404);

        $preAlerts = PreAlert::where('customer_id', $customer->id)
            ->with(['receipts', 'package.status'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('pre_alert_number', 'like', $term)
                        ->orWhere('supplier_tracking', 'like', $term)
                        ->orWhere('merchant', 'like', $term)
                        ->orWhere('courier', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to));

        $sorts = [
            'created_at' => 'created_at',
            'pre_alert_number' => 'pre_alert_number',
            'supplier_tracking' => 'supplier_tracking',
            'merchant' => 'merchant',
            'status' => 'status',
            'price_usd' => 'price_usd',
        ];
        $sort = $sorts[$request->input('sort')] ?? 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $preAlerts = $preAlerts
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($this->perPage($request, 15))
            ->withQueryString();

        return view('customer.pre-alerts.index', compact('preAlerts'));
    }

    public function create()
    {
        return view('customer.pre-alerts.create', [
            'preAlert' => null,
            'carriers' => $this->carriers(),
        ]);
    }

    public function store(Request $request)
    {
        $customer = auth()->user()->customer;
        abort_if(! $customer, 404);

        $data = $this->validatedData($request);
        unset($data['purchase_receipts']);
        $preAlert = PreAlert::create($data + [
            'tenant_id' => auth()->user()->tenant_id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'pre_alert_number' => $this->nextPreAlertNumber(auth()->user()->tenant_id),
            'status' => 'submitted',
        ]);

        $this->storeReceipts($request, $preAlert);

        return redirect()->route('customer.pre-alerts.index')->with('success', 'Pre-Alert submitted successfully.');
    }

    public function edit(PreAlert $preAlert)
    {
        $this->authorizePreAlert($preAlert);

        return view('customer.pre-alerts.edit', [
            'preAlert' => $preAlert->load('receipts'),
            'carriers' => $this->carriers(),
        ]);
    }

    public function update(Request $request, PreAlert $preAlert)
    {
        $this->authorizePreAlert($preAlert);
        abort_if($this->preAlertIsLocked($preAlert), 422, 'Assigned Pre-Alerts cannot be modified.');

        $data = $this->validatedData($request);
        unset($data['purchase_receipts']);
        $preAlert->update($data);
        $this->storeReceipts($request, $preAlert);

        return redirect()->route('customer.pre-alerts.index')->with('success', 'Pre-Alert updated successfully.');
    }

    public function destroy(PreAlert $preAlert)
    {
        $this->authorizePreAlert($preAlert);
        abort_if($this->preAlertIsLocked($preAlert), 422, 'Assigned Pre-Alerts cannot be deleted.');

        foreach ($preAlert->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
        }
        $preAlert->delete();

        return redirect()->route('customer.pre-alerts.index')->with('success', 'Pre-Alert deleted successfully.');
    }

    public function receipt(PreAlertReceipt $receipt)
    {
        $preAlert = $receipt->preAlert;
        $this->authorizePreAlert($preAlert);
        abort_if(! Storage::disk('public')->exists($receipt->path), 404);

        $headers = [
            'Content-Type' => $receipt->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($receipt->original_name).'"',
        ];

        return response()->file(Storage::disk('public')->path($receipt->path), $headers);
    }

    private function preAlertIsLocked(PreAlert $preAlert): bool
    {
        return in_array($preAlert->status, ['assigned', 'converted', 'archived'], true) || filled($preAlert->package_id);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'supplier_tracking' => 'required|string|max:150',
            'courier' => 'nullable|string|max:100',
            'merchant' => 'nullable|string|max:255',
            'description' => 'required|string',
            'price_usd' => 'nullable|numeric|min:0',
            'delivery_type' => 'required|in:pickup,delivery',
            'purchase_receipts' => 'nullable|array|max:8',
            'purchase_receipts.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:4096',
            'notes' => 'nullable|string',
        ]);
    }

    private function storeReceipts(Request $request, PreAlert $preAlert): void
    {
        foreach ($request->file('purchase_receipts', []) as $file) {
            PreAlertReceipt::create([
                'pre_alert_id' => $preAlert->id,
                'path' => $file->store('pre-alert-receipts', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }
    }

    private function authorizePreAlert(PreAlert $preAlert): void
    {
        abort_if((int) $preAlert->customer_id !== (int) optional(auth()->user()->customer)->id, 404);
    }

    private function carriers(): array
    {
        $value = (string) SystemSetting::where('key', 'courier_carriers')->value('value');

        return collect(preg_split('/\r\n|\r|\n|,/', $value))
            ->map(fn ($carrier) => trim($carrier))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function nextPreAlertNumber(int $tenantId): string
    {
        $prefix = 'PRE-'.$this->courierCode($tenantId);
        $sequence = PreAlert::where('tenant_id', $tenantId)
            ->where('pre_alert_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 7, '0', STR_PAD_LEFT);
            $sequence++;
        } while (PreAlert::where('tenant_id', $tenantId)->where('pre_alert_number', $number)->exists());

        return $number;
    }

    private function courierCode(int $tenantId): string
    {
        $configured = TenantSetting::getValue($tenantId, 'hawb_prefix')
            ?: TenantSetting::getValue($tenantId, 'courier_code')
            ?: TenantSetting::getValue($tenantId, 'mailbox_prefix');
        $code = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configured)));

        return $code !== '' ? $code : 'COU';
    }
    protected function perPage(Request $request, int $default, ?int $total = null): int
    {
        if ($request->input('per_page') === 'all') {
            return 10000;
        }

        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, [10, 25, 50, 100], true) ? $perPage : $default;
    }
}
