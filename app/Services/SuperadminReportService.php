<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\DeliveryRequest;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PreAlert;
use App\Models\PurchaseRequest;
use App\Models\SuperadminFinanceEntry;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SuperadminReportService
{
    public function modules(): array
    {
        return [
            'finances' => ['title' => 'Finances', 'description' => 'Expenses, income, subscription revenue, and profit or loss.'],
            'couriers' => ['title' => 'Couriers', 'description' => 'Courier workspaces and subscription status.'],
            'customers' => ['title' => 'Customers', 'description' => 'Customer accounts across courier workspaces.'],
            'packages' => ['title' => 'Packages', 'description' => 'Packages created and handled inside courier portals.'],
            'invoices' => ['title' => 'Invoices', 'description' => 'Courier invoices, totals, and payment status.'],
            'payments' => ['title' => 'Payments', 'description' => 'Customer payment records.'],
            'deliveries' => ['title' => 'Delivery Requests', 'description' => 'Delivery requests and delivery revenue.'],
            'manifests' => ['title' => 'Manifests', 'description' => 'Courier manifest activity.'],
            'purchase_requests' => ['title' => 'Purchase Requests', 'description' => 'Customer purchase requests.'],
            'pre_alerts' => ['title' => 'Pre-Alerts', 'description' => 'Customer pre-alert submissions.'],
            'support' => ['title' => 'Support Tickets', 'description' => 'Support requests across the platform.'],
            'audit' => ['title' => 'Audit Trail', 'description' => 'System activity from courier, customer, and superadmin portals.'],
        ];
    }

    public function selectedModule(Request $request): string
    {
        $module = $request->input('module', 'finances');

        return array_key_exists($module, $this->modules()) ? $module : 'finances';
    }

    public function build(Request $request, ?string $module = null, bool $paginate = true): array
    {
        $module = $module ?: $this->selectedModule($request);

        if ($module === 'finances') {
            return $this->financeReport($request, $paginate);
        }

        $definition = $this->definitions()[$module];
        $query = $definition['query']();
        $this->applyFilters($query, $definition, $request);
        $query->latest($definition['date_column'] ?? 'created_at');

        $summary = $this->summary($query, $definition);
        $records = $paginate ? $query->paginate(25)->withQueryString() : $query->get();
        $rowMapper = fn ($record) => $this->row($record, $definition);

        if ($paginate) {
            $records->setCollection($records->getCollection()->map($rowMapper));
            $rows = $records;
        } else {
            $rows = $records->map($rowMapper);
        }

        return [
            'key' => $module,
            'title' => $this->modules()[$module]['title'],
            'description' => $this->modules()[$module]['description'],
            'columns' => array_keys($definition['columns']),
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function platformStats(Request $request): array
    {
        $finance = $this->financeSummary($request);

        return [
            'couriers' => Tenant::count(),
            'activeCouriers' => Tenant::where('status', 'active')->count(),
            'customers' => Customer::count(),
            'packages' => Package::whereNotNull('date_received')->count(),
            'invoices' => Invoice::count(),
            'openTickets' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
            'income' => $finance['income'],
            'expenses' => $finance['expenses'],
            'profitLoss' => $finance['profitLoss'],
        ];
    }

    private function definitions(): array
    {
        return [
            'couriers' => [
                'query' => fn () => Tenant::with('subscription.plan')->withCount(['customers', 'packages', 'users']),
                'date_column' => 'created_at',
                'tenant_filter' => fn ($query, $tenantId) => $query->whereKey($tenantId),
                'status_column' => 'status',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('company_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('city', 'like', $term)->orWhere('parish', 'like', $term)),
                'columns' => [
                    'Created' => fn ($tenant) => $this->date($tenant->created_at),
                    'Courier' => fn ($tenant) => $tenant->company_name,
                    'Status' => fn ($tenant) => ucfirst($tenant->status),
                    'Plan' => fn ($tenant) => optional(optional($tenant->subscription)->plan)->name ?: 'No plan',
                    'Customers' => fn ($tenant) => number_format($tenant->customers_count),
                    'Packages' => fn ($tenant) => number_format($tenant->packages_count),
                    'Contact' => fn ($tenant) => trim($tenant->email.' '.$tenant->phone),
                ],
            ],
            'customers' => [
                'query' => fn () => Customer::with(['tenant', 'user', 'outlet'])->withCount(['packages', 'invoices']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_filter' => fn ($query, $status) => $query->where('is_active', strtolower($status) !== 'inactive'),
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('mailbox', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term))),
                'columns' => [
                    'Created' => fn ($customer) => $this->date($customer->created_at),
                    'Courier' => fn ($customer) => optional($customer->tenant)->company_name ?: '-',
                    'Customer' => fn ($customer) => optional($customer->user)->full_name ?: 'Unnamed',
                    'Email' => fn ($customer) => optional($customer->user)->email ?: '-',
                    'Mailbox' => fn ($customer) => $customer->mailbox ?: '-',
                    'Packages' => fn ($customer) => number_format($customer->packages_count),
                    'Invoices' => fn ($customer) => number_format($customer->invoices_count),
                    'Status' => fn ($customer) => $customer->is_active ? 'Active' : 'Inactive',
                ],
            ],
            'packages' => [
                'query' => fn () => Package::with(['tenant', 'customer.user', 'status']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_filter' => fn ($query, $status) => $query->whereHas('status', fn ($statusQuery) => $statusQuery->where('name', 'like', '%'.$status.'%')->orWhere('slug', 'like', '%'.$status.'%')),
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('tracking_number', 'like', $term)->orWhere('supplier_tracking', 'like', $term)->orWhere('mailbox', 'like', $term)->orWhere('merchant', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))),
                'columns' => [
                    'Created' => fn ($package) => $this->date($package->created_at),
                    'Courier' => fn ($package) => optional($package->tenant)->company_name ?: '-',
                    'Tracking' => fn ($package) => $package->tracking_number,
                    'Customer' => fn ($package) => optional(optional($package->customer)->user)->full_name ?: '-',
                    'Mailbox' => fn ($package) => $package->mailbox ?: '-',
                    'Status' => fn ($package) => optional($package->status)->name ?: '-',
                    'Weight / Volume' => fn ($package) => method_exists($package, 'measureDisplay') ? $package->measureDisplay() : number_format((float) $package->weight, 2).' lb',
                    'Total' => fn ($package) => $this->money((float) $package->price_jmd + (float) $package->storage_fee + (float) $package->processing_fee_jmd),
                ],
            ],
            'invoices' => [
                'query' => fn () => Invoice::with(['tenant', 'customer.user']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_filter' => fn ($query, $status) => $query->where(fn ($nested) => $nested->where('payment_status', 'like', '%'.$status.'%')->orWhere('invoice_status', 'like', '%'.$status.'%')),
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('invoice_number', 'like', $term)->orWhere('mailbox', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))->orWhereHas('customer.user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term))),
                'amount_column' => 'total_jmd',
                'columns' => [
                    'Created' => fn ($invoice) => $this->date($invoice->created_at),
                    'Courier' => fn ($invoice) => optional($invoice->tenant)->company_name ?: '-',
                    'Invoice' => fn ($invoice) => $invoice->invoice_number,
                    'Customer' => fn ($invoice) => optional(optional($invoice->customer)->user)->full_name ?: '-',
                    'Payment' => fn ($invoice) => ucfirst($invoice->payment_status),
                    'Status' => fn ($invoice) => ucfirst($invoice->invoice_status),
                    'Total' => fn ($invoice) => $this->money($invoice->total_jmd),
                ],
            ],
            'payments' => [
                'query' => fn () => Payment::with(['tenant', 'customer.user', 'invoice', 'method']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_column' => 'status',
                'amount_column' => 'amount',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('payment_number', 'like', $term)->orWhere('reference_number', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))->orWhereHas('customer.user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term))),
                'columns' => [
                    'Created' => fn ($payment) => $this->date($payment->created_at),
                    'Courier' => fn ($payment) => optional($payment->tenant)->company_name ?: '-',
                    'Payment' => fn ($payment) => $payment->payment_number,
                    'Customer' => fn ($payment) => optional(optional($payment->customer)->user)->full_name ?: '-',
                    'Method' => fn ($payment) => optional($payment->method)->name ?: '-',
                    'Status' => fn ($payment) => ucfirst($payment->status),
                    'Amount' => fn ($payment) => $this->money($payment->amount),
                ],
            ],
            'deliveries' => [
                'query' => fn () => DeliveryRequest::with(['tenant', 'customer.user', 'area', 'assignee']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_column' => 'status',
                'amount_column' => 'delivery_cost',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('request_number', 'like', $term)->orWhere('mailbox', 'like', $term)->orWhere('recipient_name', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))),
                'columns' => [
                    'Created' => fn ($delivery) => $this->date($delivery->created_at),
                    'Courier' => fn ($delivery) => optional($delivery->tenant)->company_name ?: '-',
                    'Request' => fn ($delivery) => $delivery->request_number,
                    'Recipient' => fn ($delivery) => $delivery->recipient_name,
                    'Packages' => fn ($delivery) => number_format($delivery->package_count),
                    'Status' => fn ($delivery) => ucfirst(str_replace('_', ' ', $delivery->status)),
                    'Cost' => fn ($delivery) => $this->money($delivery->delivery_cost),
                ],
            ],
            'manifests' => [
                'query' => fn () => Manifest::with('tenant')->withCount('items'),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_column' => 'status',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('manifest_number', 'like', $term)->orWhere('cargo_company', 'like', $term)->orWhere('master_air_bill', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))),
                'columns' => [
                    'Created' => fn ($manifest) => $this->date($manifest->created_at),
                    'Courier' => fn ($manifest) => optional($manifest->tenant)->company_name ?: '-',
                    'Manifest' => fn ($manifest) => $manifest->manifest_number,
                    'Cargo' => fn ($manifest) => $manifest->cargo_company ?: '-',
                    'Packages' => fn ($manifest) => number_format($manifest->items_count),
                    'Status' => fn ($manifest) => ucfirst($manifest->status),
                    'Departure' => fn ($manifest) => $this->date($manifest->date_of_departure),
                ],
            ],
            'purchase_requests' => [
                'query' => fn () => PurchaseRequest::with(['tenant', 'customer.user']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_column' => 'status',
                'amount_column' => 'price_usd',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('purchase_request_number', 'like', $term)->orWhere('product_url', 'like', $term)->orWhere('description', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))),
                'columns' => [
                    'Created' => fn ($request) => $this->date($request->created_at),
                    'Courier' => fn ($request) => optional($request->tenant)->company_name ?: '-',
                    'Request' => fn ($request) => $request->purchase_request_number,
                    'Customer' => fn ($request) => optional(optional($request->customer)->user)->full_name ?: '-',
                    'Delivery' => fn ($request) => ucfirst($request->delivery_type),
                    'Status' => fn ($request) => ucfirst(str_replace('_', ' ', $request->status)),
                    'Price' => fn ($request) => '$'.number_format((float) $request->price_usd, 2),
                ],
            ],
            'pre_alerts' => [
                'query' => fn () => PreAlert::with(['tenant', 'customer.user']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_column' => 'status',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('pre_alert_number', 'like', $term)->orWhere('supplier_tracking', 'like', $term)->orWhere('merchant', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))),
                'columns' => [
                    'Created' => fn ($alert) => $this->date($alert->created_at),
                    'Courier' => fn ($alert) => optional($alert->tenant)->company_name ?: '-',
                    'Pre-Alert' => fn ($alert) => $alert->pre_alert_number,
                    'Tracking' => fn ($alert) => $alert->supplier_tracking,
                    'Customer' => fn ($alert) => optional(optional($alert->customer)->user)->full_name ?: '-',
                    'Status' => fn ($alert) => ucfirst($alert->status),
                    'Price' => fn ($alert) => '$'.number_format((float) $alert->price_usd, 2),
                ],
            ],
            'support' => [
                'query' => fn () => SupportTicket::with(['tenant', 'user', 'assignee']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_column' => 'status',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('ticket_number', 'like', $term)->orWhere('subject', 'like', $term)->orWhere('message', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))),
                'columns' => [
                    'Created' => fn ($ticket) => $this->date($ticket->created_at),
                    'Courier' => fn ($ticket) => optional($ticket->tenant)->company_name ?: 'Platform',
                    'Ticket' => fn ($ticket) => $ticket->ticket_number,
                    'Subject' => fn ($ticket) => $ticket->subject,
                    'Priority' => fn ($ticket) => ucfirst($ticket->priority),
                    'Status' => fn ($ticket) => ucfirst(str_replace('_', ' ', $ticket->status)),
                    'Assigned' => fn ($ticket) => optional($ticket->assignee)->full_name ?: '-',
                ],
            ],
            'audit' => [
                'query' => fn () => ActivityLog::with(['tenant', 'user']),
                'date_column' => 'created_at',
                'tenant_column' => 'tenant_id',
                'status_column' => 'action',
                'search' => fn ($query, $term) => $query->where(fn ($nested) => $nested->where('description', 'like', $term)->orWhere('module', 'like', $term)->orWhere('action', 'like', $term)->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('company_name', 'like', $term))->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', $term)->orWhere('email', 'like', $term))),
                'columns' => [
                    'When' => fn ($log) => optional($log->created_at)->format('Y-m-d H:i'),
                    'Courier' => fn ($log) => optional($log->tenant)->company_name ?: 'Platform',
                    'User' => fn ($log) => optional($log->user)->full_name ?: optional($log->user)->email ?: 'System',
                    'Module' => fn ($log) => $log->module,
                    'Action' => fn ($log) => $log->action,
                    'Description' => fn ($log) => $log->description,
                    'IP' => fn ($log) => $log->ip_address,
                ],
            ],
        ];
    }

    private function applyFilters($query, array $definition, Request $request): void
    {
        if ($request->filled('tenant_id')) {
            if (isset($definition['tenant_filter'])) {
                $definition['tenant_filter']($query, $request->integer('tenant_id'));
            } elseif (isset($definition['tenant_column'])) {
                $query->where($definition['tenant_column'], $request->integer('tenant_id'));
            }
        }

        if ($request->filled('date_from') && isset($definition['date_column'])) {
            $query->whereDate($definition['date_column'], '>=', $request->date_from);
        }

        if ($request->filled('date_to') && isset($definition['date_column'])) {
            $query->whereDate($definition['date_column'], '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            if (isset($definition['status_filter'])) {
                $definition['status_filter']($query, $request->status);
            } elseif (isset($definition['status_column'])) {
                $query->where($definition['status_column'], 'like', '%'.$request->status.'%');
            }
        }

        if ($request->filled('q') && isset($definition['search'])) {
            $definition['search']($query, '%'.$request->q.'%');
        }
    }

    private function summary($query, array $definition): array
    {
        $summary = ['Records' => number_format((clone $query)->count())];

        if (isset($definition['amount_column'])) {
            $summary['Total Amount'] = $this->money((clone $query)->sum($definition['amount_column']));
        }

        return $summary;
    }

    private function row($record, array $definition): array
    {
        return collect($definition['columns'])->map(fn ($callback) => (string) $callback($record))->values()->all();
    }

    private function financeReport(Request $request, bool $paginate): array
    {
        $summary = $this->financeSummary($request);
        $rows = $this->financeRows($request);

        if ($paginate) {
            $page = LengthAwarePaginator::resolveCurrentPage();
            $rows = new LengthAwarePaginator(
                $rows->forPage($page, 25)->values(),
                $rows->count(),
                25,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return [
            'key' => 'finances',
            'title' => 'Finances',
            'description' => $this->modules()['finances']['description'],
            'columns' => ['Date', 'Source', 'Type', 'Category', 'Description', 'Reference', 'Amount'],
            'summary' => [
                'Income' => $this->money($summary['income']),
                'Manual Income' => $this->money($summary['manualIncome']),
                'SaaS Revenue' => $this->money($summary['subscriptionIncome']),
                'Expenses' => $this->money($summary['expenses']),
                'Profit / Loss' => $this->money($summary['profitLoss']),
            ],
            'rows' => $rows,
        ];
    }

    private function financeRows(Request $request)
    {
        $financeType = $request->input('finance_type');

        $entries = collect();
        if (! $request->filled('tenant_id')) {
            $entries = SuperadminFinanceEntry::query()
                ->when($request->filled('date_from'), fn ($query) => $query->whereDate('entry_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($query) => $query->whereDate('entry_date', '<=', $request->date_to))
                ->when(in_array($financeType, ['income', 'expense'], true), fn ($query) => $query->where('type', $financeType))
                ->get()
                ->map(fn ($entry) => [
                    'sort_date' => optional($entry->entry_date)->timestamp ?: 0,
                    'Date' => $this->date($entry->entry_date),
                    'Source' => 'Manual ledger',
                    'Type' => ucfirst($entry->type),
                    'Category' => $entry->category,
                    'Description' => $entry->description,
                    'Reference' => $entry->reference_number,
                    'Amount' => $this->money($entry->amount),
                ])
                ->toBase();
        }

        $payments = collect();
        if ($financeType !== 'expense') {
            $payments = TenantPayment::with('tenant')
                ->where('status', 'successful')
                ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
                ->when($request->filled('date_from'), fn ($query) => $query->whereDate('paid_at', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($query) => $query->whereDate('paid_at', '<=', $request->date_to))
                ->get()
                ->map(fn ($payment) => [
                    'sort_date' => optional($payment->paid_at ?: $payment->created_at)->timestamp ?: 0,
                    'Date' => $this->date($payment->paid_at ?: $payment->created_at),
                    'Source' => optional($payment->tenant)->company_name ?: 'Subscription payment',
                    'Type' => 'Income',
                    'Category' => 'Subscription payment',
                    'Description' => $payment->transaction_id ?: $payment->payment_method,
                    'Reference' => $payment->transaction_id,
                    'Amount' => $this->money($payment->amount),
                ])
                ->toBase();
        }

        return $entries
            ->merge($payments)
            ->sortByDesc('sort_date')
            ->map(fn ($row) => collect($row)->except('sort_date')->values()->all())
            ->values();
    }

    private function financeSummary(Request $request): array
    {
        $manualIncome = 0.0;
        $expenses = 0.0;

        if (! $request->filled('tenant_id')) {
            $entries = SuperadminFinanceEntry::query()
                ->when($request->filled('date_from'), fn ($query) => $query->whereDate('entry_date', '>=', $request->date_from))
                ->when($request->filled('date_to'), fn ($query) => $query->whereDate('entry_date', '<=', $request->date_to));

            $manualIncome = (float) (clone $entries)
                ->where('type', 'income')
                ->where('category', '<>', 'SaaS Revenue - Service Charges')
                ->sum('amount');
            $expenses = (float) (clone $entries)->where('type', 'expense')->sum('amount');
        }
        $subscriptionIncome = (float) TenantPayment::where('status', 'successful')
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('paid_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('paid_at', '<=', $request->date_to))
            ->sum('amount');
        $income = $manualIncome + $subscriptionIncome;

        return compact('income', 'manualIncome', 'subscriptionIncome', 'expenses') + ['profitLoss' => $income - $expenses];
    }

    private function date($date): string
    {
        return $date ? optional($date)->format('Y-m-d') : '-';
    }

    private function money($amount): string
    {
        return 'JMD '.number_format((float) $amount, 2);
    }
}
