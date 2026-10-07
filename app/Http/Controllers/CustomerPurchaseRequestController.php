<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestReceipt;
use App\Models\TenantSetting;
use App\Services\CourierNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerPurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $customer = auth()->user()->customer;
        abort_if(! $customer, 404);

        $purchaseRequests = PurchaseRequest::where('customer_id', $customer->id)
            ->with('receipts')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->q.'%';
                $query->where(function ($nested) use ($term) {
                    $nested->where('purchase_request_number', 'like', $term)
                        ->orWhere('supplier_tracking', 'like', $term)
                        ->orWhere('product_url', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('delivery_type'), fn ($query) => $query->where('delivery_type', $request->delivery_type))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to));

        $sorts = [
            'created_at' => 'created_at',
            'purchase_request_number' => 'purchase_request_number',
            'delivery_type' => 'delivery_type',
            'status' => 'status',
            'price_usd' => 'price_usd',
        ];
        $sort = $sorts[$request->input('sort')] ?? 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $purchaseRequests = $purchaseRequests
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($this->perPage($request, 15))
            ->withQueryString();

        return view('customer.purchase-requests.index', compact('purchaseRequests'));
    }

    public function create()
    {
        return view('customer.purchase-requests.create', [
            'purchaseRequest' => null,
        ]);
    }

    public function store(Request $request, CourierNotificationService $courierNotifications)
    {
        $customer = auth()->user()->customer;
        abort_if(! $customer, 404);

        $data = $this->validatedData($request);
        unset($data['purchase_receipts']);

        $purchaseRequest = PurchaseRequest::create($data + [
            'tenant_id' => auth()->user()->tenant_id,
            'customer_id' => $customer->id,
            'outlet_id' => $customer->outlet_id,
            'purchase_request_number' => $this->nextPurchaseRequestNumber(auth()->user()->tenant_id),
            'status' => 'submitted',
        ]);

        $this->storeReceipts($request, $purchaseRequest);

        $courierNotifications->notify(
            $purchaseRequest->tenant_id,
            $purchaseRequest->outlet_id,
            'purchase_request',
            'New Purchase Request',
            (optional($customer->user)->full_name ?: $customer->mailbox).' submitted '.$purchaseRequest->purchase_request_number.'.',
            route('courier.purchase-requests.index'),
            ['purchase_request_id' => $purchaseRequest->id]
        );

        return redirect()->route('customer.purchase-requests.index')->with('success', 'Purchase request submitted successfully.');
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $this->authorizePurchaseRequest($purchaseRequest);

        return view('customer.purchase-requests.edit', [
            'purchaseRequest' => $purchaseRequest->load('receipts'),
        ]);
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizePurchaseRequest($purchaseRequest);
        abort_if($purchaseRequest->status !== 'submitted', 422, 'Purchase requests cannot be modified after courier processing has started.');

        $data = $this->validatedData($request);
        unset($data['purchase_receipts']);
        $purchaseRequest->update($data);
        $this->storeReceipts($request, $purchaseRequest);

        return redirect()->route('customer.purchase-requests.index')->with('success', 'Purchase request updated successfully.');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $this->authorizePurchaseRequest($purchaseRequest);
        abort_if($purchaseRequest->status !== 'submitted', 422, 'Purchase requests cannot be deleted after courier processing has started.');

        foreach ($purchaseRequest->receipts as $receipt) {
            Storage::disk('public')->delete($receipt->path);
        }
        $purchaseRequest->delete();

        return redirect()->route('customer.purchase-requests.index')->with('success', 'Purchase request deleted successfully.');
    }

    public function receipt(PurchaseRequestReceipt $receipt)
    {
        $purchaseRequest = $receipt->purchaseRequest;
        $this->authorizePurchaseRequest($purchaseRequest);
        abort_if(! Storage::disk('public')->exists($receipt->path), 404);

        return Storage::disk('public')->download($receipt->path, $receipt->original_name);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'product_url' => 'nullable|url|max:500',
            'price_usd' => 'nullable|numeric|min:0',
            'delivery_type' => 'required|in:air,sea',
            'description' => 'required|string',
            'notes' => 'nullable|string',
            'purchase_receipts' => 'nullable|array|max:8',
            'purchase_receipts.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:4096',
        ]);
    }

    private function storeReceipts(Request $request, PurchaseRequest $purchaseRequest): void
    {
        foreach ($request->file('purchase_receipts', []) as $file) {
            PurchaseRequestReceipt::create([
                'purchase_request_id' => $purchaseRequest->id,
                'path' => $file->store('purchase-request-receipts', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
            ]);
        }
    }

    private function authorizePurchaseRequest(PurchaseRequest $purchaseRequest): void
    {
        abort_if((int) $purchaseRequest->customer_id !== (int) optional(auth()->user()->customer)->id, 404);
    }

    private function nextPurchaseRequestNumber(int $tenantId): string
    {
        $prefix = 'PR-'.$this->courierCode($tenantId);
        $sequence = PurchaseRequest::where('tenant_id', $tenantId)
            ->where('purchase_request_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 7, '0', STR_PAD_LEFT);
            $sequence++;
        } while (PurchaseRequest::where('tenant_id', $tenantId)->where('purchase_request_number', $number)->exists());

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
