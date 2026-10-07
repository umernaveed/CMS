<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'mailbox',
        'outlet_id',
        'account_manager_id',
        'pricing_tier_id',
        'account_type',
        'company_name',
        'company_registration',
        'representative_role',
        'business_document_path',
        'business_document_original_name',
        'business_document_mime_type',
        'registration_documents',
        'credit_limit',
        'current_balance',
        'total_points',
        'total_referral_points',
        'notes',
        'email_group',
        'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'total_points' => 'decimal:2',
        'total_referral_points' => 'decimal:2',
        'registration_documents' => 'array',
        'is_active' => 'boolean',
    ];

    public static function normalizeMailbox(?string $mailbox): ?string
    {
        if ($mailbox === null) {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/\s+/', '', trim($mailbox)));

        return $normalized === '' ? null : $normalized;
    }

    public function setMailboxAttribute($value): void
    {
        $this->attributes['mailbox'] = self::normalizeMailbox($value);
    }

    public function scopeWhereNormalizedMailbox($query, ?string $mailbox)
    {
        $normalized = self::normalizeMailbox($mailbox);
        if ($normalized === null) {
            return $query->whereRaw('1 = 0');
        }

        $column = $this->qualifyColumn('mailbox');

        return $query->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(REPLACE($column, ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '')) = ?", [$normalized]);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function shippingAddresses()
    {
        return $this->hasMany(CustomerShippingAddress::class);
    }

    public function pricingTier()
    {
        return $this->belongsTo(PricingTier::class);
    }

    public function authorizedUsers()
    {
        return $this->hasMany(AuthorizedUser::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function referralsMade()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referralSource()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }
}
