<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantSetting;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'outlet_id',
        'tracking_number',
        'supplier_tracking',
        'mailbox',
        'courier',
        'merchant',
        'description',
        'package_type_id',
        'weight',
        'price_usd',
        'price_jmd',
        'freight_type',
        'region',
        'volume_cuft',
        'volume_cbm',
        'label_type',
        'delivery_type',
        'status_id',
        'invoice_file',
        'manifest_id',
        'wms_package_id',
        'wms_last_synced_at',
        'is_in_manifest',
        'storage_days',
        'storage_fee',
        'processing_fee_jmd',
        'unknown_fee_jmd',
        'unknown_fee_reason',
        'date_received',
        'date_delivered',
        'notes',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'price_jmd' => 'decimal:2',
        'volume_cuft' => 'decimal:4',
        'volume_cbm' => 'decimal:4',
        'storage_fee' => 'decimal:2',
        'processing_fee_jmd' => 'decimal:2',
        'unknown_fee_jmd' => 'decimal:2',
        'date_received' => 'date',
        'date_delivered' => 'datetime',
        'is_in_manifest' => 'boolean',
        'wms_last_synced_at' => 'datetime',
    ];

    public function manifestValueAmount(): float
    {
        return (float) $this->price_usd > 0 ? (float) $this->price_usd : (float) $this->price_jmd;
    }

    public function manifestValueCurrency(): string
    {
        return (float) $this->price_usd > 0 ? 'USD' : 'JMD';
    }

    public function manifestValueDisplay(): string
    {
        return $this->manifestValueCurrency().' '.number_format($this->manifestValueAmount(), 2);
    }

    public function normalizedRegion(): string
    {
        $region = trim((string) ($this->region ?: 'USA'));
        $key = strtolower($region);

        return in_array($key, ['cn', 'china'], true) ? 'China' : (in_array($key, ['us', 'usa'], true) ? 'USA' : $region);
    }

    public function labelType(): string
    {
        if (trim((string) $this->label_type) !== '') {
            return (string) $this->label_type;
        }

        $regionCode = strtolower($this->normalizedRegion()) === 'china' ? 'CN' : (strtolower($this->normalizedRegion()) === 'usa' ? 'US' : strtoupper($this->normalizedRegion()));
        $method = strtolower((string) $this->freight_type) === 'sea' ? 'Sea' : 'Air';

        return trim($regionCode.' '.$method);
    }

    public function seaVolumeDisplay(): string
    {
        $cuft = (float) ($this->volume_cuft ?: $this->weight);
        $cbm = (float) ($this->volume_cbm ?: ($cuft > 0 ? $cuft / WeightRate::CBM_TO_CUFT : 0));

        return number_format($cuft, 2).' cu.ft / '.number_format($cbm, 3).' CBM';
    }

    public function isSeaFreight(): bool
    {
        return strtolower((string) $this->freight_type) === 'sea';
    }

    public function measureDisplay(): string
    {
        if ($this->isSeaFreight()) {
            return $this->seaVolumeDisplay();
        }

        return number_format((float) $this->weight, 2).' lb';
    }

    public function measureLabel(): string
    {
        return $this->isSeaFreight() ? 'Volume' : 'Weight';
    }
    public function setMailboxAttribute($value): void
    {
        $this->attributes['mailbox'] = Customer::normalizeMailbox($value);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function packageType()
    {
        return $this->belongsTo(PackageType::class);
    }

    public function status()
    {
        return $this->belongsTo(PackageStatus::class, 'status_id');
    }

    public function manifest()
    {
        return $this->belongsTo(Manifest::class);
    }

    public function purchaseReceipts()
    {
        return $this->hasMany(PackagePurchaseReceipt::class);
    }

    public function logs()
    {
        return $this->hasMany(PackageLog::class);
    }

    public function latestLog()
    {
        return $this->hasOne(PackageLog::class)->latestOfMany();
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function customerVisibleCostJmd(): float
    {
        if (! $this->manifest_id) {
            return 0.0;
        }

        $this->loadMissing('invoiceItems.invoice');

        $item = $this->invoiceItems
            ->filter(fn (InvoiceItem $item) => $item->invoice
                && $item->invoice->voided_at === null
                && $item->invoice->invoice_status !== 'cancelled'
                && $item->invoice->payment_status !== 'cancelled')
            ->sortByDesc(fn (InvoiceItem $item) => optional($item->invoice->created_at)->timestamp ?? 0)
            ->first();

        return $item ? (float) $item->total_jmd : 0.0;
    }

    public function customerVisibleCostDisplay(): string
    {
        return 'JMD '.number_format($this->customerVisibleCostJmd(), 2);
    }


    public function activeInvoiceItem()
    {
        return $this->invoiceItems
            ->filter(fn (InvoiceItem $item) => $item->invoice && ! $item->invoice->isLocked() && $item->invoice->payment_status !== 'cancelled')
            ->sortByDesc(fn (InvoiceItem $item) => optional($item->invoice->created_at)->timestamp ?? 0)
            ->first();
    }
    public function scopeCurrentlyAging(Builder $query, int $minimumAge = 7): Builder
    {
        $minimumAge = max($minimumAge, 0);
        $eligibleInvoiceDate = now()->startOfDay()->subDays($minimumAge + 1)->toDateString();

        return $query
            ->whereNull('date_delivered')
            ->whereHas('invoiceItems.invoice', function (Builder $invoice) use ($eligibleInvoiceDate) {
                $invoice->where('invoice_status', 'issued')
                    ->where('payment_status', '!=', 'cancelled')
                    ->whereNull('voided_at')
                    ->whereDate('created_at', '<=', $eligibleInvoiceDate);
            })
            ->whereHas('status', function (Builder $status) {
                $status
                    ->where('is_active', true)
                    ->where(function (Builder $query) {
                        $query
                            ->whereIn('slug', [
                                'ready-for-pickup',
                                'available-for-pickup',
                                'ready-for-delivery',
                                'available-for-delivery',
                                'out-for-delivery',
                            ])
                            ->orWhere(function (Builder $nested) {
                                $nested->where(function (Builder $availability) {
                                    $availability->where('name', 'like', '%Ready%')
                                        ->orWhere('name', 'like', '%Available%')
                                        ->orWhere('name', 'like', '%Out%');
                                })->where(function (Builder $type) {
                                    $type->where('name', 'like', '%Pickup%')
                                        ->orWhere('name', 'like', '%Delivery%');
                                });
                            });
                    });
            });
    }

    public function activeIssuedInvoice()
    {
        return $this->invoiceItems
            ->map(fn (InvoiceItem $item) => $item->invoice)
            ->filter(fn ($invoice) => $invoice
                && $invoice->invoice_status === 'issued'
                && $invoice->payment_status !== 'cancelled'
                && $invoice->voided_at === null)
            ->sortBy('created_at')
            ->first();
    }

    public function invoiceAgingDays(): int
    {
        $invoice = $this->activeIssuedInvoice();

        return $invoice && $invoice->created_at
            ? max(0, (int) $invoice->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay()))
            : 0;
    }

    public function invoiceAgingStorageFee(?int $minimumAge = null): float
    {
        $days = $this->invoiceAgingDays();
        $freeDays = $minimumAge ?? (int) TenantSetting::getValue((int) $this->tenant_id, 'storage_free_days', 0);
        if ($days <= $freeDays) {
            return 0.0;
        }

        $flat = (float) TenantSetting::getValue((int) $this->tenant_id, 'storage_flat_fee_jmd', 0);
        $daily = (float) TenantSetting::getValue((int) $this->tenant_id, 'storage_daily_increment_jmd', 0);

        return round($flat + max($days - $freeDays, 0) * $daily, 2);
    }
}



