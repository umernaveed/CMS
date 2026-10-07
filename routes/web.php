<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TenantRegisterController;
use App\Http\Controllers\Auth\CustomerRegisterController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperadminLogisticsController;
use App\Http\Controllers\SuperadminFinanceController;
use App\Http\Controllers\SuperadminStaffController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\CourierEmailController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\CourierCustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPreAlertController;
use App\Http\Controllers\CustomerPurchaseRequestController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\RateCalculatorController;
use App\Http\Controllers\WeightRateController;
use App\Http\Controllers\StaffUserController;
use App\Http\Controllers\TenantRoleController;
use App\Http\Controllers\PackageStatusController;
use App\Http\Controllers\PackageLogController;
use App\Http\Controllers\DeliveryRequestController;
use App\Http\Controllers\DeliveryAreaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CourierSettingsController;
use App\Http\Controllers\CourierWarehouseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DataExchangeController;
use App\Http\Controllers\DataMaintenanceController;
use App\Http\Controllers\CustomerShippingAddressController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\SupportChatController;
use App\Http\Controllers\CustomsRateController;
use App\Http\Controllers\AccessCodeController;
use App\Http\Controllers\Api\WmsSyncController as ApiWmsSyncController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();

    if ($user->user_type === 'super_admin') {
        foreach ([
            'superadmin_dashboard' => 'superadmin.dashboard',
            'superadmin_courier_workspaces' => 'superadmin.tenants',
            'superadmin_courier_workspaces_create' => 'superadmin.tenants.create',
            'superadmin_courier_workspaces_edit' => 'superadmin.tenants',
            'superadmin_courier_workspaces_delete' => 'superadmin.tenants',
            'superadmin_courier_workspaces_open_portal' => 'superadmin.tenants',
            'superadmin_courier_workspaces_credit' => 'superadmin.tenants',
            'superadmin_courier_customers' => 'superadmin.tenants.customers',
            'superadmin_staff_employees' => 'superadmin.staff.employees',
            'superadmin_staff_roles' => 'superadmin.staff.roles-branches',
            'superadmin_logistics' => 'superadmin.logistics.index',
            'superadmin_logistics_overview' => 'superadmin.logistics.index',
            'superadmin_logistics_manifests' => 'superadmin.logistics.manifests',
            'superadmin_logistics_bulk_check_in' => 'superadmin.logistics.bulk-check-in',
            'superadmin_logistics_bulk_upload' => 'superadmin.logistics.bulk-upload',
            'superadmin_logistics_packages' => 'superadmin.logistics.packages',
            'superadmin_logistics_rates' => 'superadmin.logistics.rates',
            'superadmin_logistics_invoices' => 'superadmin.logistics.invoices',
            'superadmin_crm_chat' => 'superadmin.support.index',
            'superadmin_crm_messaging' => 'superadmin.notifications.index',
            'superadmin_payments' => 'superadmin.payments.index',
            'superadmin_courier_billing' => 'superadmin.finance.bills',
            'superadmin_subscription_plans' => 'superadmin.plans.index',
            'superadmin_expense_income' => 'superadmin.finance.entries',
            'superadmin_profit_losses' => 'superadmin.finance.profit-loss',
            'superadmin_billing_rates' => 'superadmin.finance.billing-rates',
            'superadmin_settings' => 'superadmin.settings.index',
            'superadmin_settings_general' => 'superadmin.settings.index',
            'superadmin_settings_registration' => 'superadmin.settings.index',
            'superadmin_settings_email' => 'superadmin.settings.index',
            'superadmin_settings_sms' => 'superadmin.settings.index',
            'superadmin_settings_shipping' => 'superadmin.settings.index',
            'superadmin_settings_payment' => 'superadmin.settings.index',
            'superadmin_maintenance' => 'superadmin.settings.index',
            'superadmin_settings_master_tech' => 'superadmin.settings.index',
            'superadmin_settings_references' => 'superadmin.settings.index',
            'superadmin_reports' => 'superadmin.reports.index',
            'superadmin_audit' => 'superadmin.audit.index',
        ] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return redirect()->route($route);
            }
        }

        return redirect()->route('profile.edit');
    }

    if (in_array($user->user_type, ['tenant_admin', 'staff'], true)) {
        foreach ([
            'view_dashboard' => 'courier.dashboard',
            'manage_customers' => 'courier.customers.index',
            'manage_tickets' => 'courier.support.index',
            'manage_crm_emails' => 'courier.crm.emails.create',
            'manage_authorized_users' => 'courier.authorized-users.index',
            'manage_packages' => 'courier.packages.index',
            'manage_pre_alerts' => 'courier.pre-alerts.index',
            'manage_purchase_requests' => 'courier.purchase-requests.index',
            'manage_package_exceptions' => 'courier.packages.aging',
            'manage_warehouse' => 'courier.warehouse.unmanifest',
            'manage_invoices' => 'courier.invoices.index',
            'manage_payments' => 'courier.finance.payments',
            'manage_transactions' => 'courier.finance.transactions',
            'manage_billing' => 'courier.finance.bills',
            'view_reports' => 'courier.finance.reports',
            'manage_delivery' => 'courier.delivery.index',
            'manage_manifests' => 'courier.manifests.index',
            'manage_staff' => 'courier.staff.index',
            'manage_roles' => 'courier.roles.index',
            'manage_branches' => 'courier.outlets.index',
            'manage_settings' => 'courier.settings.index',
            'manage_rates' => 'courier.weights.index',
            'manage_customs_rates' => 'courier.customs-rates.index',
            'manage_activity_logs' => 'courier.activity.index',
        ] as $permission => $route) {
            if ($user->hasPermission($permission)) {
                return redirect()->route($route);
            }
        }

        return redirect()->route('profile.edit');
    }

    return redirect()->route('customer.dashboard');
});

Route::post('/admin/package-upload', [ApiWmsSyncController::class, 'packageUpload'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/labels/{trackingNumber}', [ApiWmsSyncController::class, 'label']);
Route::get('/labels/{trackingNumber}/html', [ApiWmsSyncController::class, 'labelHtml']);

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::get('/customer/login', fn () => redirect()->route('login'))->name('customer.login');
Route::post('/customer/login', fn () => redirect()->route('login'))->name('customer.login.store');
Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
Route::get('/register-tenant', [TenantRegisterController::class, 'create'])->name('tenant.register');
Route::post('/register-tenant', [TenantRegisterController::class, 'store'])->name('tenant.register.store');
Route::get('/register-tenant/payment', [TenantRegisterController::class, 'payment'])->name('tenant.register.payment');
Route::post('/register-tenant/payment', [TenantRegisterController::class, 'completePayment'])->name('tenant.register.payment.complete');
Route::get('/register-tenant/payment/{payment}/success', [TenantRegisterController::class, 'paymentSuccess'])->name('tenant.register.payment.success');
Route::get('/register-tenant/payment/{payment}/pending', [TenantRegisterController::class, 'paymentPending'])->name('tenant.register.payment.pending');
Route::get('/register-tenant/payment/{payment}/cancel', [TenantRegisterController::class, 'paymentCancel'])->name('tenant.register.payment.cancel');
Route::get('/register-customer', [CustomerRegisterController::class, 'create'])->name('customer.register');
Route::post('/register-customer', [CustomerRegisterController::class, 'store'])->name('customer.register.store');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/tech-support', [AccessCodeController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('tech-support.login');
Route::post('/tech-support', [AccessCodeController::class, 'enter'])
    ->middleware('throttle:5,1')
    ->name('tech-support.login.store');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::match(['post', 'put'], '/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/calculator', [RateCalculatorController::class, 'index'])->name('calculator');
    Route::get('/notifications/counts', [NotificationController::class, 'counts'])->name('notifications.counts');
    Route::post('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');
    Route::get('/direct-notifications/{notification}/open', [NotificationController::class, 'openDirect'])->name('notifications.direct.open');
    Route::post('/superadmin/return', [SuperAdminController::class, 'returnToSuperadmin'])->name('superadmin.return');
});

// Superadmin Routes
Route::middleware(['auth', 'user_type:super_admin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard')->middleware('permission:superadmin_dashboard');
    Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants')->middleware('permission:superadmin_courier_workspaces');
    Route::get('/tenants/export', [SuperAdminController::class, 'exportTenants'])->name('tenants.export')->middleware('permission:superadmin_courier_workspaces');
    Route::get('/tenants/print', [SuperAdminController::class, 'printTenants'])->name('tenants.print')->middleware('permission:superadmin_courier_workspaces');
    Route::get('/tenants/download', [SuperAdminController::class, 'downloadTenants'])->name('tenants.download')->middleware('permission:superadmin_courier_workspaces');
    Route::get('/tenants/create', [SuperAdminController::class, 'createTenant'])->name('tenants.create')->middleware('permission:superadmin_courier_workspaces_create');
    Route::post('/tenants', [SuperAdminController::class, 'storeTenant'])->name('tenants.store')->middleware('permission:superadmin_courier_workspaces_create');
    Route::get('/tenants/customers', [SuperAdminController::class, 'tenantCustomers'])->name('tenants.customers')->middleware('permission:superadmin_courier_customers');
    Route::get('/tenants/customers/export', [SuperAdminController::class, 'exportTenantCustomers'])->name('tenants.customers.export')->middleware('permission:superadmin_courier_customers');
    Route::get('/tenants/customers/print', [SuperAdminController::class, 'printTenantCustomers'])->name('tenants.customers.print')->middleware('permission:superadmin_courier_customers');
    Route::get('/tenants/customers/download', [SuperAdminController::class, 'downloadTenantCustomers'])->name('tenants.customers.download')->middleware('permission:superadmin_courier_customers');
    Route::post('/tenants/{tenant}/open-portal', [SuperAdminController::class, 'openTenantPortal'])->name('tenants.open-portal')->middleware('permission:superadmin_courier_workspaces_open_portal');
    Route::post('/tenants/{tenant}/credit', [SuperAdminController::class, 'creditTenant'])->name('tenants.credit')->middleware('permission:superadmin_courier_workspaces_credit');
    Route::get('/tenants/{tenant}/wallet', [SuperAdminController::class, 'tenantWallet'])->name('tenants.wallet')->middleware('permission:superadmin_courier_workspaces_credit');
    Route::get('/tenants/{tenant}/wallet/print', [SuperAdminController::class, 'printTenantWallet'])->name('tenants.wallet.print')->middleware('permission:superadmin_courier_workspaces_credit');
    Route::get('/tenants/{tenant}/wallet/download', [SuperAdminController::class, 'downloadTenantWallet'])->name('tenants.wallet.download')->middleware('permission:superadmin_courier_workspaces_credit');
    Route::get('/tenants/{tenant}/onboarding-documents/{field}/{index}', [SuperAdminController::class, 'downloadTenantOnboardingDocument'])->name('tenants.onboarding-documents.download')->middleware('permission:superadmin_courier_workspaces');
    Route::get('/tenants/{tenant}/onboarding-documents/{field}/{index}/view', [SuperAdminController::class, 'viewTenantOnboardingDocument'])->name('tenants.onboarding-documents.view')->middleware('permission:superadmin_courier_workspaces');
    Route::get('/tenants/{tenant}/onboarding-documents/{field}/{index}/print', [SuperAdminController::class, 'printTenantOnboardingDocument'])->name('tenants.onboarding-documents.print')->middleware('permission:superadmin_courier_workspaces');
    Route::post('/tenants/{tenant}/onboarding-documents/{field}', [SuperAdminController::class, 'uploadTenantOnboardingDocument'])->name('tenants.onboarding-documents.upload')->middleware('permission:superadmin_courier_workspaces_edit');
    Route::get('/tenants/{tenant}', [SuperAdminController::class, 'showTenant'])->name('tenants.show')->middleware('permission:superadmin_courier_workspaces');
    Route::get('/tenants/{tenant}/edit', [SuperAdminController::class, 'editTenant'])->name('tenants.edit')->middleware('permission:superadmin_courier_workspaces_edit');
    Route::put('/tenants/{tenant}', [SuperAdminController::class, 'updateTenant'])->name('tenants.update')->middleware('permission:superadmin_courier_workspaces_edit');
    Route::delete('/tenants/{tenant}', [SuperAdminController::class, 'destroyTenant'])->name('tenants.destroy')->middleware('permission:superadmin_courier_workspaces_delete');
    Route::get('/payments', [SuperAdminController::class, 'payments'])->name('payments.index')->middleware('permission:superadmin_payments');
    Route::get('/staff/employees', [SuperadminStaffController::class, 'employees'])->name('staff.employees')->middleware('permission:superadmin_staff_employees');
    Route::post('/staff/employees', [SuperadminStaffController::class, 'storeEmployee'])->name('staff.employees.store')->middleware('permission:superadmin_staff_employees');
    Route::put('/staff/employees/{employee}', [SuperadminStaffController::class, 'updateEmployee'])->name('staff.employees.update')->middleware('permission:superadmin_staff_employees');
    Route::delete('/staff/employees/{employee}', [SuperadminStaffController::class, 'destroyEmployee'])->name('staff.employees.destroy')->middleware('permission:superadmin_staff_employees');
    Route::get('/staff/roles-branches', [SuperadminStaffController::class, 'rolesBranches'])->name('staff.roles-branches')->middleware('permission:superadmin_staff_roles,superadmin_staff_branches');
    Route::post('/staff/roles', [SuperadminStaffController::class, 'storeRole'])->name('staff.roles.store')->middleware('permission:superadmin_staff_roles');
    Route::put('/staff/roles/{role}', [SuperadminStaffController::class, 'updateRole'])->name('staff.roles.update')->middleware('permission:superadmin_staff_roles');
    Route::delete('/staff/roles/{role}', [SuperadminStaffController::class, 'destroyRole'])->name('staff.roles.destroy')->middleware('permission:superadmin_staff_roles');
    Route::post('/staff/branches', [SuperadminStaffController::class, 'storeBranch'])->name('staff.branches.store')->middleware('permission:superadmin_staff_branches');
    Route::put('/staff/branches/{branch}', [SuperadminStaffController::class, 'updateBranch'])->name('staff.branches.update')->middleware('permission:superadmin_staff_branches');
    Route::delete('/staff/branches/{branch}', [SuperadminStaffController::class, 'destroyBranch'])->name('staff.branches.destroy')->middleware('permission:superadmin_staff_branches');
    Route::get('/finance/entries', [SuperadminFinanceController::class, 'entries'])->name('finance.entries')->middleware('permission:superadmin_expense_income');
    Route::post('/finance/entries', [SuperadminFinanceController::class, 'storeEntry'])->name('finance.entries.store')->middleware('permission:superadmin_expense_income');
    Route::put('/finance/entries/{entry}', [SuperadminFinanceController::class, 'updateEntry'])->name('finance.entries.update')->middleware('permission:superadmin_expense_income');
    Route::delete('/finance/entries/{entry}', [SuperadminFinanceController::class, 'destroyEntry'])->name('finance.entries.destroy')->middleware('permission:superadmin_expense_income');
    Route::get('/finance/categories', [SuperadminFinanceController::class, 'categories'])->name('finance.categories')->middleware('permission:superadmin_expense_income');
    Route::post('/finance/categories', [SuperadminFinanceController::class, 'storeCategory'])->name('finance.categories.store')->middleware('permission:superadmin_expense_income');
    Route::put('/finance/categories/{category}', [SuperadminFinanceController::class, 'updateCategory'])->name('finance.categories.update')->middleware('permission:superadmin_expense_income');
    Route::delete('/finance/categories/{category}', [SuperadminFinanceController::class, 'destroyCategory'])->name('finance.categories.destroy')->middleware('permission:superadmin_expense_income');
    Route::get('/finance/entries/template', [SuperadminFinanceController::class, 'entriesTemplate'])->name('finance.entries.template')->middleware('permission:superadmin_expense_income');
    Route::post('/finance/entries/import', [SuperadminFinanceController::class, 'importEntries'])->name('finance.entries.import')->middleware('permission:superadmin_expense_income');
    Route::get('/finance/entries/export', [SuperadminFinanceController::class, 'exportEntries'])->name('finance.entries.export')->middleware('permission:superadmin_expense_income');
    Route::get('/finance/entries/print', [SuperadminFinanceController::class, 'printEntries'])->name('finance.entries.print')->middleware('permission:superadmin_expense_income');
    Route::get('/finance/profit-loss', [SuperadminFinanceController::class, 'profitLoss'])->name('finance.profit-loss')->middleware('permission:superadmin_profit_losses');
    Route::get('/finance/billing-rates', [SuperadminFinanceController::class, 'billingRates'])->name('finance.billing-rates')->middleware('permission:superadmin_billing_rates');
    Route::post('/finance/billing-rates', [SuperadminFinanceController::class, 'storeBillingRate'])->name('finance.billing-rates.store')->middleware('permission:superadmin_billing_rates');
    Route::put('/finance/billing-rates/{charge}', [SuperadminFinanceController::class, 'updateBillingRate'])->name('finance.billing-rates.update')->middleware('permission:superadmin_billing_rates');
    Route::delete('/finance/billing-rates/{charge}', [SuperadminFinanceController::class, 'destroyBillingRate'])->name('finance.billing-rates.destroy')->middleware('permission:superadmin_billing_rates');
    Route::get('/finance/bills', [SuperadminFinanceController::class, 'bills'])->name('finance.bills')->middleware('permission:superadmin_courier_billing');
    Route::post('/finance/bills', [SuperadminFinanceController::class, 'storeBill'])->name('finance.bills.store')->middleware('permission:superadmin_courier_billing');
    Route::post('/finance/bills/generate-package-usage', [SuperadminFinanceController::class, 'generatePackageUsageBill'])->name('finance.bills.generate-package-usage')->middleware('permission:superadmin_courier_billing');
    Route::post('/finance/bills/generate-cycle', [SuperadminFinanceController::class, 'generateCycleBill'])->name('finance.bills.generate-cycle')->middleware('permission:superadmin_courier_billing');
    Route::get('/finance/bills/{bill}/download', [SuperadminFinanceController::class, 'downloadBill'])->name('finance.bills.download')->middleware('permission:superadmin_courier_billing');
    Route::post('/finance/bills/{bill}/send', [SuperadminFinanceController::class, 'sendBill'])->name('finance.bills.send')->middleware('permission:superadmin_courier_billing');
    Route::post('/finance/bills/{bill}/void', [SuperadminFinanceController::class, 'voidBill'])->name('finance.bills.void')->middleware('permission:superadmin_courier_billing');
    Route::get('/finance/bills/{bill}', [SuperadminFinanceController::class, 'showBill'])->name('finance.bills.show')->middleware('permission:superadmin_courier_billing');
    Route::put('/finance/bills/{bill}', [SuperadminFinanceController::class, 'updateBill'])->name('finance.bills.update')->middleware('permission:superadmin_courier_billing');
    Route::post('/finance/bill-payments/{payment}/review', [SuperadminFinanceController::class, 'reviewBillPayment'])->name('finance.bill-payments.review')->middleware('permission:superadmin_courier_billing');
    Route::get('/finance/bill-payments/{payment}/receipt', [SuperadminFinanceController::class, 'downloadBillReceipt'])->name('finance.bill-payments.receipt')->middleware('permission:superadmin_courier_billing');
    Route::get('/support', [SupportChatController::class, 'index'])->name('support.index')->middleware('permission:superadmin_crm_chat');
    Route::get('/support/create', [SupportChatController::class, 'create'])->name('support.create')->middleware('permission:superadmin_crm_chat');
    Route::post('/support', [SupportChatController::class, 'store'])->name('support.store')->middleware('permission:superadmin_crm_chat');
    Route::get('/support/{ticket}/transcript', [SupportChatController::class, 'downloadTranscript'])->name('support.transcript')->middleware('permission:superadmin_crm_chat');
    Route::get('/support/{ticket}', [SupportChatController::class, 'show'])->name('support.show')->middleware('permission:superadmin_crm_chat');
    Route::get('/support/{ticket}/messages', [SupportChatController::class, 'messages'])->name('support.messages')->middleware('permission:superadmin_crm_chat');
    Route::post('/support/{ticket}/reply', [SupportChatController::class, 'reply'])->name('support.reply')->middleware('permission:superadmin_crm_chat');
    Route::patch('/support/{ticket}/status', [SupportChatController::class, 'updateStatus'])->name('support.status')->middleware('permission:superadmin_crm_chat');
    Route::delete('/support/{ticket}', [SupportChatController::class, 'destroy'])->name('support.destroy')->middleware('permission:superadmin_crm_chat');
    Route::get('/logistics', [SuperadminLogisticsController::class, 'index'])->name('logistics.index')->middleware('permission:superadmin_logistics,superadmin_logistics_overview');
    Route::get('/logistics/overview', [SuperadminLogisticsController::class, 'index'])->name('logistics.overview')->middleware('permission:superadmin_logistics,superadmin_logistics_overview');
    Route::get('/logistics/manifests', [SuperadminLogisticsController::class, 'manifests'])->name('logistics.manifests')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::get('/logistics/bulk-check-in', [SuperadminLogisticsController::class, 'bulkCheckInPage'])->name('logistics.bulk-check-in')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::post('/logistics/bulk-check-in', [SuperadminLogisticsController::class, 'bulkCheckIn'])->name('logistics.bulk-check-in.store')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::post('/logistics/shipments', [SuperadminLogisticsController::class, 'storeShipment'])->name('logistics.shipments.store')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::put('/logistics/shipments/{shipment}', [SuperadminLogisticsController::class, 'updateShipment'])->name('logistics.shipments.update')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::delete('/logistics/shipments/{shipment}', [SuperadminLogisticsController::class, 'destroyShipment'])->name('logistics.shipments.destroy')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::post('/logistics/shipments/{shipment}/scan', [SuperadminLogisticsController::class, 'scanShipmentPackage'])->name('logistics.shipments.scan')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::post('/logistics/shipments/{shipment}/bulk-upload', [SuperadminLogisticsController::class, 'bulkUploadShipmentPackages'])->name('logistics.shipments.bulk-upload')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::delete('/logistics/shipments/{shipment}/packages/{package}', [SuperadminLogisticsController::class, 'removeShipmentPackage'])->name('logistics.shipments.packages.remove')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::post('/logistics/shipments/{shipment}/generate-bills', [SuperadminLogisticsController::class, 'generateShipmentCourierBills'])->name('logistics.shipments.generate-bills')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_check_in');
    Route::post('/logistics/shipments/{shipment}/generate-invoices', [SuperadminLogisticsController::class, 'generateShipmentInvoices'])->name('logistics.shipments.generate-invoices')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::get('/logistics/bulk-upload', [SuperadminLogisticsController::class, 'bulkUploadPage'])->name('logistics.bulk-upload')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_upload');
    Route::get('/logistics/rates', [SuperadminLogisticsController::class, 'ratesPage'])->name('logistics.rates')->middleware('permission:superadmin_logistics,superadmin_logistics_rates');
    Route::get('/logistics/invoices', [SuperadminLogisticsController::class, 'invoicesPage'])->name('logistics.invoices')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::get('/logistics/packages', [SuperadminLogisticsController::class, 'packagesPage'])->name('logistics.packages')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::get('/logistics/packages/export', [SuperadminLogisticsController::class, 'exportPackages'])->name('logistics.packages.export-all')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::get('/logistics/packages/print', [SuperadminLogisticsController::class, 'printPackages'])->name('logistics.packages.print')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::get('/logistics/packages/labels', [SuperadminLogisticsController::class, 'printPackageLabels'])->name('logistics.packages.labels')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::get('/logistics/packages/{package}/label', [SuperadminLogisticsController::class, 'printPackageLabel'])->name('logistics.packages.label')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::get('/logistics/packages/download', [SuperadminLogisticsController::class, 'downloadPackages'])->name('logistics.packages.download')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::get('/logistics/packages/customers/search', [SuperadminLogisticsController::class, 'searchCustomers'])->name('logistics.packages.customers.search')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::patch('/logistics/packages/{package}/assign', [SuperadminLogisticsController::class, 'assignUnknownPackage'])->name('logistics.packages.assign')->middleware('permission:superadmin_logistics,superadmin_logistics_packages');
    Route::post('/logistics/manifests', [SuperadminLogisticsController::class, 'storeManifest'])->name('logistics.manifests.store')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::get('/logistics/packages/bulk-template', [SuperadminLogisticsController::class, 'bulkUploadTemplate'])->name('logistics.packages.bulk-template')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_upload');
    Route::post('/logistics/packages/bulk-upload', [SuperadminLogisticsController::class, 'bulkUploadPackages'])->name('logistics.packages.bulk-upload')->middleware('permission:superadmin_logistics,superadmin_logistics_bulk_upload');
    Route::get('/logistics/manifests/{manifest}', [SuperadminLogisticsController::class, 'show'])->name('logistics.show')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::put('/logistics/manifests/{manifest}', [SuperadminLogisticsController::class, 'updateManifest'])->name('logistics.manifests.update')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::delete('/logistics/manifests/{manifest}', [SuperadminLogisticsController::class, 'destroyManifest'])->name('logistics.manifests.destroy')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::get('/logistics/manifests/{manifest}/packages/template', [SuperadminLogisticsController::class, 'manifestPackageTemplate'])->name('logistics.packages.template')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::get('/logistics/manifests/{manifest}/packages/export', [SuperadminLogisticsController::class, 'exportManifestPackages'])->name('logistics.packages.export')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::post('/logistics/manifests/{manifest}/packages', [SuperadminLogisticsController::class, 'addPackages'])->name('logistics.packages.store')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::post('/logistics/manifests/{manifest}/packages/bulk-upload', [SuperadminLogisticsController::class, 'bulkAddManifestPackages'])->name('logistics.packages.manifest-bulk-upload')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::put('/logistics/manifests/{manifest}/packages/{package}', [SuperadminLogisticsController::class, 'updatePackage'])->name('logistics.packages.update')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::patch('/logistics/manifests/{manifest}/packages/{package}/reassign', [SuperadminLogisticsController::class, 'reassignPackage'])->name('logistics.packages.reassign')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::post('/logistics/manifests/{manifest}/packages/{package}/unknown-room', [SuperadminLogisticsController::class, 'movePackageToUnknownRoom'])->name('logistics.packages.unknown-room')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::delete('/logistics/manifests/{manifest}/packages/{package}', [SuperadminLogisticsController::class, 'destroyPackage'])->name('logistics.packages.destroy')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::post('/logistics/manifests/{manifest}/check-in', [SuperadminLogisticsController::class, 'checkIn'])->name('logistics.check-in')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::post('/logistics/manifests/{manifest}/invoices', [SuperadminLogisticsController::class, 'createInvoice'])->name('logistics.invoices.store')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::put('/logistics/invoices/{invoice}', [SuperadminLogisticsController::class, 'updateInvoice'])->name('logistics.invoices.update')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::get('/logistics/invoices/{invoice}/download', [SuperadminLogisticsController::class, 'downloadInvoice'])->name('logistics.invoices.download')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::get('/logistics/invoices/{invoice}/thermal', [SuperadminLogisticsController::class, 'downloadThermalInvoice'])->name('logistics.invoices.thermal')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::get('/logistics/invoices/{invoice}/thermal/download', [SuperadminLogisticsController::class, 'downloadThermalInvoiceFile'])->name('logistics.invoices.thermal.download')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::get('/logistics/invoices/{invoice}/receipt', [SuperadminLogisticsController::class, 'viewInvoiceReceipt'])->name('logistics.invoices.receipt.view')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::get('/logistics/invoices/{invoice}/supplier-invoice', [SuperadminLogisticsController::class, 'viewSupplierInvoice'])->name('logistics.invoices.supplier-invoice.view')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/invoices/{invoice}/paid', [SuperadminLogisticsController::class, 'markPaid'])->name('logistics.invoices.paid')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/invoices/{invoice}/payments', [SuperadminLogisticsController::class, 'addInvoicePayment'])->name('logistics.invoices.payments.store')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/invoices/{invoice}/apply-credit', [SuperadminLogisticsController::class, 'applyInvoiceCredit'])->name('logistics.invoices.apply-credit')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/invoices/{invoice}/send', [SuperadminLogisticsController::class, 'sendInvoiceEmail'])->name('logistics.invoices.send')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/invoices/{invoice}/receipt', [SuperadminLogisticsController::class, 'uploadInvoiceReceipt'])->name('logistics.invoices.receipt')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/invoices/{invoice}/void', [SuperadminLogisticsController::class, 'voidInvoice'])->name('logistics.invoices.void')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/invoices/{invoice}/items', [SuperadminLogisticsController::class, 'addInvoiceItems'])->name('logistics.invoices.items.store')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::delete('/logistics/invoices/{invoice}/items/{item}', [SuperadminLogisticsController::class, 'removeInvoiceItem'])->name('logistics.invoices.items.destroy')->middleware('permission:superadmin_logistics,superadmin_logistics_invoices,superadmin_courier_billing');
    Route::post('/logistics/manifests/{manifest}/check-out', [SuperadminLogisticsController::class, 'checkOut'])->name('logistics.check-out')->middleware('permission:superadmin_logistics,superadmin_logistics_manifests');
    Route::post('/logistics/rates', [SuperadminLogisticsController::class, 'storeRate'])->name('logistics.rates.store')->middleware('permission:superadmin_logistics,superadmin_logistics_rates');
    Route::put('/logistics/rates/{rate}', [SuperadminLogisticsController::class, 'updateRate'])->name('logistics.rates.update')->middleware('permission:superadmin_logistics,superadmin_logistics_rates');
    Route::delete('/logistics/rates/{rate}', [SuperadminLogisticsController::class, 'destroyRate'])->name('logistics.rates.destroy')->middleware('permission:superadmin_logistics,superadmin_logistics_rates');
    Route::post('/logistics/pricing', [SuperadminLogisticsController::class, 'updatePricing'])->name('logistics.pricing.update')->middleware('permission:superadmin_logistics,superadmin_logistics_rates');
    Route::get('/settings', [SuperAdminController::class, 'settings'])->name('settings.index')->middleware('permission:superadmin_settings,superadmin_settings_general,superadmin_settings_registration,superadmin_settings_email,superadmin_settings_sms,superadmin_settings_shipping,superadmin_settings_payment,superadmin_maintenance,superadmin_settings_master_tech,superadmin_settings_references');
    Route::put('/settings', [SuperAdminController::class, 'updateSettings'])->name('settings.update')->middleware('permission:superadmin_settings,superadmin_settings_general,superadmin_settings_registration,superadmin_settings_email,superadmin_settings_sms,superadmin_settings_shipping,superadmin_settings_payment');
    Route::post('/settings/personal-security-code', [AccessCodeController::class, 'regeneratePersonalSecurityCode'])->name('settings.personal-security-code')->middleware('permission:superadmin_maintenance');
    Route::put('/settings/personal-security-code', [AccessCodeController::class, 'setPersonalSecurityCode'])->name('settings.personal-security-code.update')->middleware('permission:superadmin_maintenance');
    Route::get('/maintenance/backup', [DataMaintenanceController::class, 'platformBackup'])->name('maintenance.backup')->middleware('permission:superadmin_maintenance');
    Route::post('/maintenance/automatic-backup', [DataMaintenanceController::class, 'automaticBackup'])->name('maintenance.automatic-backup')->middleware('permission:superadmin_maintenance');
    Route::post('/maintenance/restore', [DataMaintenanceController::class, 'platformRestore'])->name('maintenance.restore')->middleware('permission:superadmin_maintenance');
    Route::post('/maintenance/clear', [DataMaintenanceController::class, 'platformClear'])->name('maintenance.clear')->middleware('permission:superadmin_maintenance');
    Route::post('/maintenance/sync', [DataMaintenanceController::class, 'platformSync'])->name('maintenance.sync')->middleware('permission:superadmin_maintenance');
    Route::post('/settings/customs-offices', [SuperAdminController::class, 'storeCustomsOffice'])->name('settings.customs-offices.store')->middleware('permission:superadmin_settings,superadmin_settings_references');
    Route::post('/settings/loading-ports', [SuperAdminController::class, 'storeLoadingPort'])->name('settings.loading-ports.store')->middleware('permission:superadmin_settings,superadmin_settings_references');
    Route::post('/settings/master-tech-codes', [AccessCodeController::class, 'storeMasterCode'])->name('settings.master-tech-codes.store')->middleware('permission:superadmin_settings,superadmin_settings_master_tech');
    Route::delete('/settings/master-tech-codes/{code}', [AccessCodeController::class, 'revokeMasterCode'])->name('settings.master-tech-codes.revoke')->middleware('permission:superadmin_settings,superadmin_settings_master_tech');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('permission:superadmin_crm_messaging');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store')->middleware('permission:superadmin_crm_messaging');
    Route::put('/notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update')->middleware('permission:superadmin_crm_messaging');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy')->middleware('permission:superadmin_crm_messaging');
    Route::get('/reports', [SuperAdminController::class, 'reports'])->name('reports.index')->middleware('permission:superadmin_reports');
    Route::get('/reports/export', [SuperAdminController::class, 'exportReports'])->name('reports.export')->middleware('permission:superadmin_reports');
    Route::get('/reports/print', [SuperAdminController::class, 'printReport'])->name('reports.print')->middleware('permission:superadmin_reports');
    Route::get('/reports/download', [SuperAdminController::class, 'exportReports'])->name('reports.download')->middleware('permission:superadmin_reports');
    Route::get('/audit', [SuperAdminController::class, 'audit'])->name('audit.index')->middleware('permission:superadmin_audit');
    Route::get('/audit/export', [SuperAdminController::class, 'exportAudit'])->name('audit.export')->middleware('permission:superadmin_audit');
    Route::get('/audit/print', [SuperAdminController::class, 'printAudit'])->name('audit.print')->middleware('permission:superadmin_audit');
    Route::get('/audit/download', [SuperAdminController::class, 'exportAudit'])->name('audit.download')->middleware('permission:superadmin_audit');
    Route::resource('plans', SubscriptionPlanController::class)->middleware('permission:superadmin_subscription_plans');
});

// Courier Routes
Route::middleware(['auth', 'user_type:tenant_admin,staff'])->prefix('courier')->name('courier.')->group(function () {
    Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf-token');
    Route::get('/dashboard', [CourierController::class, 'dashboard'])->name('dashboard')->middleware('permission:view_dashboard');
    Route::get('/activity/export', [CourierController::class, 'activityExport'])->name('activity.export')->middleware('permission:manage_activity_logs');
    Route::get('/activity/print', [CourierController::class, 'activityPrint'])->name('activity.print')->middleware('permission:manage_activity_logs');
    Route::get('/activity', [CourierController::class, 'activity'])->name('activity.index')->middleware('permission:manage_activity_logs');
    Route::middleware('permission:manage_tickets')->group(function () {
        Route::get('/support', [SupportChatController::class, 'index'])->name('support.index');
        Route::get('/support/create', [SupportChatController::class, 'create'])->name('support.create');
        Route::post('/support', [SupportChatController::class, 'store'])->name('support.store');
        Route::get('/support/{ticket}/transcript', [SupportChatController::class, 'downloadTranscript'])->name('support.transcript');
        Route::get('/support/{ticket}', [SupportChatController::class, 'show'])->name('support.show');
        Route::get('/support/{ticket}/messages', [SupportChatController::class, 'messages'])->name('support.messages');
        Route::post('/support/{ticket}/reply', [SupportChatController::class, 'reply'])->name('support.reply');
        Route::patch('/support/{ticket}/status', [SupportChatController::class, 'updateStatus'])->name('support.status');
    });
    Route::middleware('permission:manage_crm_emails')->group(function () {
        Route::get('/crm/emails/create', [CourierEmailController::class, 'create'])->name('crm.emails.create');
        Route::get('/crm/emails/recipients/preview', [CourierEmailController::class, 'previewRecipients'])->name('crm.emails.recipients.preview');
        Route::get('/crm/emails/drafts/{draft}/edit', [CourierEmailController::class, 'edit'])->name('crm.emails.drafts.edit');
        Route::put('/crm/emails/drafts/{draft}', [CourierEmailController::class, 'update'])->name('crm.emails.drafts.update');
        Route::post('/crm/emails/drafts/{draft}/send', [CourierEmailController::class, 'sendSaved'])->name('crm.emails.drafts.send');
        Route::post('/crm/emails', [CourierEmailController::class, 'store'])->name('crm.emails.send');
    });
    Route::middleware('permission:manage_customers')->group(function () {
        Route::post('/customers/{customer}/password-reset', [CourierCustomerController::class, 'sendPasswordReset'])->name('customers.password-reset');
        Route::post('/customers/{customer}/welcome-email', [CourierCustomerController::class, 'resendWelcomeEmail'])->name('customers.welcome-email');
        Route::post('/customers/{customer}/wallet-credit', [CourierCustomerController::class, 'creditWallet'])->name('customers.wallet-credit');
        Route::post('/customers/{customer}/wallet-balance', [CourierCustomerController::class, 'setWalletBalance'])->name('customers.wallet-balance');
        Route::get('/customers/{customer}/business-document', [CourierCustomerController::class, 'downloadBusinessDocument'])->name('customers.business-document');
        Route::get('/customers/{customer}/registration-documents/{field}/{index}', [CourierCustomerController::class, 'downloadRegistrationDocument'])->name('customers.registration-documents.download');
        Route::post('/customers/{customer}/registration-documents', [CourierCustomerController::class, 'uploadRegistrationDocuments'])->name('customers.registration-documents.upload');
        Route::get('/customers/{customer}/wallet-top-ups/{transaction}/receipt', [CourierCustomerController::class, 'walletTopUpReceipt'])->name('customers.wallet-top-ups.receipt');
        Route::patch('/customers/{customer}/wallet-top-ups/{transaction}/approve', [CourierCustomerController::class, 'approveWalletTopUp'])->name('customers.wallet-top-ups.approve');
        Route::patch('/customers/{customer}/wallet-top-ups/{transaction}/reject', [CourierCustomerController::class, 'rejectWalletTopUp'])->name('customers.wallet-top-ups.reject');
        Route::post('/customers/{customer}/addresses', [CustomerShippingAddressController::class, 'store'])->name('customers.addresses.store');
        Route::put('/customers/{customer}/addresses/{address}', [CustomerShippingAddressController::class, 'update'])->name('customers.addresses.update');
        Route::delete('/customers/{customer}/addresses/{address}', [CustomerShippingAddressController::class, 'destroy'])->name('customers.addresses.destroy');
        Route::post('/customers/import-wms', [CourierCustomerController::class, 'importToWms'])->name('customers.import-wms');
        Route::get('/customers/print', [CourierCustomerController::class, 'print'])->name('customers.print');
        Route::resource('customers', CourierCustomerController::class);
    });
    Route::middleware('permission:manage_authorized_users')->group(function () {
        Route::get('/authorized-users', [CourierController::class, 'authorizedUsers'])->name('authorized-users.index');
        Route::get('/authorized-users/{authorizedUser}', [CourierController::class, 'showAuthorizedUser'])->name('authorized-users.show');
    });
    Route::middleware('permission:manage_package_exceptions')->group(function () {
        Route::get('/packages/aging', [PackageController::class, 'aging'])->name('packages.aging');
        Route::post('/packages/aging/reminders', [PackageController::class, 'sendAgingReminders'])->name('packages.aging.reminders');
    });
    Route::middleware('permission:manage_packages')->group(function () {
        Route::get('/packages/export', [PackageController::class, 'export'])->name('packages.export');
        Route::get('/packages/template', [PackageController::class, 'template'])->name('packages.template');
        Route::post('/packages/import', [PackageController::class, 'import'])->name('packages.import');
        Route::get('/packages/print', [PackageController::class, 'print'])->name('packages.print');
        Route::post('/packages/{package}/logs', [PackageLogController::class, 'store'])->name('packages.logs.store');
        Route::patch('/packages/{package}/reassign', [PackageController::class, 'reassign'])->name('packages.reassign');
        Route::get('/packages/{package}/label', [PackageController::class, 'label'])->name('packages.label');
        Route::get('/packages/{package}/receipt', [PackageController::class, 'downloadReceipt'])->name('packages.receipt');
        Route::get('/packages/{package}/purchase-receipts/{receipt}', [PackageController::class, 'purchaseReceipt'])->name('packages.purchase-receipts.show');
        Route::resource('packages', PackageController::class);
    });
    Route::get('/warehouse/unmanifest', [CourierWarehouseController::class, 'unmanifest'])->name('warehouse.unmanifest')->middleware('permission:manage_warehouse');
    Route::middleware('permission:manage_pre_alerts')->group(function () {
        Route::get('/pre-alerts', [PackageController::class, 'preAlerts'])->name('pre-alerts.index');
        Route::get('/pre-alert-receipts/{receipt}', [PackageController::class, 'preAlertReceipt'])->name('pre-alerts.receipt');
    });
    Route::middleware('permission:manage_purchase_requests')->group(function () {
        Route::get('/purchase-requests', [CourierController::class, 'purchaseRequests'])->name('purchase-requests.index');
        Route::get('/purchase-requests/receipts/{receipt}', [CourierController::class, 'purchaseRequestReceipt'])->name('purchase-requests.receipt');
        Route::get('/purchase-requests/{purchaseRequest}', [CourierController::class, 'showPurchaseRequest'])->name('purchase-requests.show');
        Route::patch('/purchase-requests/{purchaseRequest}/status', [CourierController::class, 'updatePurchaseRequestStatus'])->name('purchase-requests.status.update');
    });
    Route::patch('/staff/{staff}/deactivate', [StaffUserController::class, 'deactivate'])->name('staff.deactivate')->middleware(['user_type:tenant_admin', 'permission:manage_staff']);
    Route::resource('staff', StaffUserController::class)->middleware(['user_type:tenant_admin', 'permission:manage_staff']);
    Route::resource('roles', TenantRoleController::class)->middleware(['user_type:tenant_admin', 'permission:manage_roles']);
    Route::resource('statuses', PackageStatusController::class)->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::get('/settings', [CourierSettingsController::class, 'index'])->name('settings.index')->middleware(['user_type:tenant_admin', 'permission:manage_settings']);
    Route::put('/settings', [CourierSettingsController::class, 'update'])->name('settings.update')->middleware(['user_type:tenant_admin', 'permission:manage_settings']);
    Route::post('/settings/personal-security-code', [AccessCodeController::class, 'regeneratePersonalSecurityCode'])->name('settings.personal-security-code')->middleware(['user_type:tenant_admin', 'permission:manage_settings']);
    Route::put('/settings/personal-security-code', [AccessCodeController::class, 'setPersonalSecurityCode'])->name('settings.personal-security-code.update')->middleware(['user_type:tenant_admin', 'permission:manage_settings']);
    Route::post('/settings/support-access-codes', [AccessCodeController::class, 'storeCourierCode'])->name('settings.support-access-codes.store')->middleware(['user_type:tenant_admin', 'permission:manage_settings']);
    Route::delete('/settings/support-access-codes/{code}', [AccessCodeController::class, 'revokeCourierCode'])->name('settings.support-access-codes.revoke')->middleware(['user_type:tenant_admin', 'permission:manage_settings']);
    Route::get('/maintenance/backup', [DataMaintenanceController::class, 'courierBackup'])->name('maintenance.backup')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::post('/maintenance/automatic-backup', [DataMaintenanceController::class, 'automaticBackup'])->name('maintenance.automatic-backup')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::post('/maintenance/restore', [DataMaintenanceController::class, 'courierRestore'])->name('maintenance.restore')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::post('/maintenance/clear', [DataMaintenanceController::class, 'courierClear'])->name('maintenance.clear')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::post('/maintenance/sync', [DataMaintenanceController::class, 'courierSync'])->name('maintenance.sync')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::post('/settings/tiers', [CourierSettingsController::class, 'storeTier'])->name('settings.tiers.store')->middleware(['user_type:tenant_admin', 'permission:manage_rates']);
    Route::get('/settings/tiers/rates/template', [CourierSettingsController::class, 'ratesTemplate'])->name('settings.tiers.rates.template')->middleware(['user_type:tenant_admin', 'permission:manage_rates']);
    Route::put('/settings/tiers/{tier}', [CourierSettingsController::class, 'updateTier'])->name('settings.tiers.update')->middleware(['user_type:tenant_admin', 'permission:manage_rates']);
    Route::delete('/settings/tiers/{tier}', [CourierSettingsController::class, 'destroyTier'])->name('settings.tiers.destroy')->middleware(['user_type:tenant_admin', 'permission:manage_rates']);
    Route::post('/settings/tiers/{tier}/rates/import', [CourierSettingsController::class, 'importRates'])->name('settings.tiers.rates.import')->middleware(['user_type:tenant_admin', 'permission:manage_rates']);
    Route::post('/settings/notifications', [CourierSettingsController::class, 'storeNotification'])->name('settings.notifications.store')->middleware(['user_type:tenant_admin', 'permission:manage_notifications']);
    Route::put('/settings/notifications/{notification}', [CourierSettingsController::class, 'updateNotification'])->name('settings.notifications.update')->middleware(['user_type:tenant_admin', 'permission:manage_notifications']);
    Route::delete('/settings/notifications/{notification}', [CourierSettingsController::class, 'destroyNotification'])->name('settings.notifications.destroy')->middleware(['user_type:tenant_admin', 'permission:manage_notifications']);
    Route::get('/client-messages', [NotificationController::class, 'courierClientMessages'])->name('client-messages.index')->middleware('permission:manage_crm_emails');
    Route::get('/data/export/{table}', [DataExchangeController::class, 'export'])->name('data.export')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::get('/data/template/{table}', [DataExchangeController::class, 'template'])->name('data.template')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::post('/data/import/{table}', [DataExchangeController::class, 'import'])->name('data.import')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::delete('/data/delete/{table}', [DataExchangeController::class, 'massDelete'])->name('data.mass-delete')->middleware(['user_type:tenant_admin', 'permission:manage_system']);
    Route::middleware('permission:manage_invoices')->group(function () {
        Route::get('/invoices/mass-payments', [InvoiceController::class, 'massPayments'])->name('invoices.mass');
        Route::post('/invoices/mass-payments', [InvoiceController::class, 'updateMassPayments'])->name('invoices.mass.update');
        Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
        Route::get('/invoices/print', [InvoiceController::class, 'printList'])->name('invoices.print');
        Route::post('/invoices/bulk-generate', [InvoiceController::class, 'bulkStore'])->name('invoices.bulk-generate');
        Route::post('/invoices/send-selected', [InvoiceController::class, 'sendSelected'])->name('invoices.send-selected');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay')->middleware('permission:manage_payments,manage_invoices');
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'voidInvoice'])->name('invoices.void');
        Route::post('/invoices/{invoice}/verify', [InvoiceController::class, 'verify'])->name('invoices.verify')->middleware('permission:manage_payments');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::resource('invoices', InvoiceController::class);
    });
    Route::get('/finance/transactions', [FinanceController::class, 'transactions'])->name('finance.transactions')->middleware('permission:manage_transactions');
    Route::get('/finance/entries', [FinanceController::class, 'entries'])->name('finance.entries')->middleware('permission:manage_transactions');
    Route::post('/finance/entries', [FinanceController::class, 'storeEntry'])->name('finance.entries.store')->middleware('permission:manage_transactions');
    Route::put('/finance/entries/{entry}', [FinanceController::class, 'updateEntry'])->name('finance.entries.update')->middleware('permission:manage_transactions');
    Route::delete('/finance/entries/{entry}', [FinanceController::class, 'destroyEntry'])->name('finance.entries.destroy')->middleware('permission:manage_transactions');
    Route::get('/finance/categories', [FinanceController::class, 'categories'])->name('finance.categories')->middleware('permission:manage_transactions');
    Route::post('/finance/categories', [FinanceController::class, 'storeCategory'])->name('finance.categories.store')->middleware('permission:manage_transactions');
    Route::put('/finance/categories/{category}', [FinanceController::class, 'updateCategory'])->name('finance.categories.update')->middleware('permission:manage_transactions');
    Route::delete('/finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->name('finance.categories.destroy')->middleware('permission:manage_transactions');
    Route::get('/finance/profit-loss', [FinanceController::class, 'profitLoss'])->name('finance.profit-loss')->middleware('permission:view_reports');
    Route::get('/finance/reports', [FinanceController::class, 'reports'])->name('finance.reports')->middleware('permission:view_reports');
    Route::get('/finance/balance-sheet', [FinanceController::class, 'balanceSheet'])->name('finance.balance-sheet')->middleware('permission:view_reports');
    Route::get('/finance/bills', [FinanceController::class, 'bills'])->name('finance.bills')->middleware('permission:manage_billing');
    Route::get('/finance/wallet', [FinanceController::class, 'wallet'])->name('finance.wallet')->middleware('permission:manage_billing');
    Route::get('/finance/wallet/print', [FinanceController::class, 'printWallet'])->name('finance.wallet.print')->middleware('permission:manage_billing');
    Route::get('/finance/wallet/download', [FinanceController::class, 'downloadWallet'])->name('finance.wallet.download')->middleware('permission:manage_billing');
    Route::get('/finance/bills/{bill}', [FinanceController::class, 'showBill'])->name('finance.bills.show')->middleware('permission:manage_billing');
    Route::post('/finance/bills/{bill}/payments', [FinanceController::class, 'uploadBillPayment'])->name('finance.bills.payments.store')->middleware('permission:manage_billing,manage_payments');
    Route::get('/finance/logistics-invoices/{invoice}/download', [FinanceController::class, 'downloadLogisticsInvoice'])->name('finance.logistics-invoices.download')->middleware('permission:manage_billing');
    Route::get('/finance/logistics-invoices/{invoice}/thermal', [FinanceController::class, 'downloadThermalLogisticsInvoice'])->name('finance.logistics-invoices.thermal')->middleware('permission:manage_billing');
    Route::get('/finance/logistics-invoices/{invoice}/thermal/download', [FinanceController::class, 'downloadThermalLogisticsInvoiceFile'])->name('finance.logistics-invoices.thermal.download')->middleware('permission:manage_billing');
    Route::get('/finance/logistics-invoices/{invoice}/supplier-invoice', [FinanceController::class, 'viewLogisticsSupplierInvoice'])->name('finance.logistics-invoices.supplier-invoice.view')->middleware('permission:manage_billing');
    Route::post('/finance/logistics-invoices/{invoice}/receipt', [FinanceController::class, 'uploadLogisticsInvoiceReceipt'])->name('finance.logistics-invoices.receipt')->middleware('permission:manage_billing,manage_payments');
    Route::post('/finance/logistics-invoices/{invoice}/supplier-invoice', [FinanceController::class, 'uploadLogisticsSupplierInvoice'])->name('finance.logistics-invoices.supplier-invoice')->middleware('permission:manage_billing,manage_payments');
    Route::post('/finance/logistics-invoices/{invoice}/manifest', [FinanceController::class, 'generateManifestFromLogisticsInvoice'])->name('finance.logistics-invoices.manifest')->middleware('permission:manage_billing,manage_manifests');
    Route::get('/finance/bill-payments/{payment}/receipt', [FinanceController::class, 'downloadBillReceipt'])->name('finance.bill-payments.receipt')->middleware('permission:manage_billing,manage_payments');
    Route::get('/finance/payments', [FinanceController::class, 'payments'])->name('finance.payments')->middleware('permission:manage_payments');
    Route::get('/finance/payments/{payment}/receipt', [FinanceController::class, 'downloadPaymentReceipt'])->name('finance.payments.receipt')->middleware('permission:manage_payments');
    Route::post('/finance/payments/{payment}/approve', [FinanceController::class, 'approvePayment'])->name('finance.payments.approve')->middleware('permission:manage_payments');
    Route::post('/finance/payments/{payment}/receipt', [FinanceController::class, 'uploadPaymentReceipt'])->name('finance.payments.receipt.upload')->middleware('permission:manage_payments');
    Route::get('/finance/{section}/export', [FinanceController::class, 'export'])->name('finance.export')->middleware('permission:manage_transactions,view_reports,manage_payments');
    Route::get('/finance/{section}/print', [FinanceController::class, 'print'])->name('finance.print')->middleware('permission:manage_transactions,view_reports,manage_payments');
    Route::get('/finance/{section}/download', [FinanceController::class, 'export'])->name('finance.download')->middleware('permission:manage_transactions,view_reports,manage_payments');
    Route::middleware('permission:manage_manifests')->group(function () {
        Route::post('/manifests/{manifest}/packages', [ManifestController::class, 'addPackages'])->name('manifests.packages.add');
        Route::delete('/manifests/{manifest}/packages/{package}', [ManifestController::class, 'removePackage'])->name('manifests.packages.remove');
        Route::post('/manifests/{manifest}/packages/status', [ManifestController::class, 'updatePackageStatuses'])->name('manifests.packages.update-status');
        Route::get('/manifests/export', [ManifestController::class, 'exportList'])->name('manifests.export-list');
        Route::get('/manifests/print', [ManifestController::class, 'printList'])->name('manifests.print-list');
        Route::get('/manifests/template', [ManifestController::class, 'template'])->name('manifests.template');
        Route::post('/manifests/import', [ManifestController::class, 'import'])->name('manifests.import');
        Route::post('/manifests/{manifest}/submit', [ManifestController::class, 'submit'])->name('manifests.submit');
        Route::get('/manifests/{manifest}/bulk-invoices', [InvoiceController::class, 'bulkCreateForManifest'])->name('manifests.bulk-invoices')->middleware('permission:manage_invoices');
        Route::post('/manifests/{manifest}/bulk-invoices', [InvoiceController::class, 'bulkStoreForManifest'])->name('manifests.bulk-invoices.store')->middleware('permission:manage_invoices');
        Route::get('/manifests/{manifest}/export', [ManifestController::class, 'export'])->name('manifests.export');
        Route::get('/manifests/{manifest}/print', [ManifestController::class, 'print'])->name('manifests.print');
        Route::get('/manifests/{manifest}/download', [ManifestController::class, 'download'])->name('manifests.download');
        Route::resource('manifests', ManifestController::class);
    });
    Route::get('/weights/shipping-modes/create', [WeightRateController::class, 'createMode'])->name('weights.shipping-modes.create')->middleware('permission:manage_rates');
    Route::post('/weights/shipping-modes', [WeightRateController::class, 'storeMode'])->name('weights.shipping-modes.store')->middleware('permission:manage_rates');
    Route::put('/weights/shipping-modes/{shippingMode}', [WeightRateController::class, 'updateMode'])->name('weights.shipping-modes.update')->middleware('permission:manage_rates');
    Route::delete('/weights/shipping-modes/{shippingMode}', [WeightRateController::class, 'destroyMode'])->name('weights.shipping-modes.destroy')->middleware('permission:manage_rates');
    Route::resource('weights', WeightRateController::class)->middleware('permission:manage_rates');
    Route::get('/customs-rates/export', [CustomsRateController::class, 'export'])->name('customs-rates.export')->middleware('permission:manage_customs_rates');
    Route::get('/customs-rates/template', [CustomsRateController::class, 'template'])->name('customs-rates.template')->middleware('permission:manage_customs_rates');
    Route::post('/customs-rates/import', [CustomsRateController::class, 'import'])->name('customs-rates.import')->middleware('permission:manage_customs_rates');
    Route::put('/customs-rates/settings', [CustomsRateController::class, 'updateSettings'])->name('customs-rates.settings')->middleware('permission:manage_customs_rates');
    Route::resource('customs-rates', CustomsRateController::class)->parameters(['customs-rates' => 'customsRate'])->except(['show'])->middleware('permission:manage_customs_rates');
    Route::resource('outlets', OutletController::class)->middleware(['user_type:tenant_admin', 'permission:manage_branches']);
    Route::middleware('permission:manage_delivery')->group(function () {
        Route::get('/delivery', [DeliveryRequestController::class, 'courierIndex'])->name('delivery.index');
        Route::get('/delivery/history', [DeliveryRequestController::class, 'history'])->name('delivery.history');
        Route::get('/delivery/export', [DeliveryRequestController::class, 'export'])->name('delivery.export');
        Route::get('/delivery/print', [DeliveryRequestController::class, 'print'])->name('delivery.print');
        Route::get('/delivery/download', [DeliveryRequestController::class, 'download'])->name('delivery.download');
        Route::patch('/delivery/{delivery}/status', [DeliveryRequestController::class, 'updateStatus'])->name('delivery.status');
    });
    Route::post('/delivery-areas/{deliveryArea}/schedule-days', [DeliveryAreaController::class, 'storeTimeSlot'])->name('delivery-areas.slots.store')->middleware(['user_type:tenant_admin', 'permission:manage_delivery']);
    Route::delete('/delivery-areas/{deliveryArea}/schedule-days/{slot}', [DeliveryAreaController::class, 'destroyTimeSlot'])->name('delivery-areas.slots.destroy')->middleware(['user_type:tenant_admin', 'permission:manage_delivery']);
    Route::resource('delivery-areas', DeliveryAreaController::class)->except(['show'])->middleware(['user_type:tenant_admin', 'permission:manage_delivery']);
});

// Customer Routes
Route::middleware(['auth', 'user_type:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [CustomerController::class, 'dashboard'])->name('dashboard');
    Route::get('/affiliate-loyalty', [CustomerController::class, 'affiliateLoyalty'])->name('affiliate-loyalty');
    Route::get('/affiliate-loyalty/top-ups', [CustomerController::class, 'walletTopUpHistory'])->name('wallet.top-ups.history');
    Route::get('/affiliate-loyalty/top-ups/print', [CustomerController::class, 'printWalletTopUps'])->name('wallet.top-ups.print');
    Route::get('/affiliate-loyalty/top-ups/download', [CustomerController::class, 'downloadWalletTopUps'])->name('wallet.top-ups.download');
    Route::post('/wallet/top-up', [CustomerController::class, 'storeWalletTopUp'])->name('wallet.top-up');
    Route::get('/messages', [NotificationController::class, 'customerMessages'])->name('messages.index');
    Route::middleware('plan_module:live_chat')->group(function () {
        Route::get('/support', [SupportChatController::class, 'index'])->name('support.index');
        Route::get('/support/create', [SupportChatController::class, 'create'])->name('support.create');
        Route::post('/support', [SupportChatController::class, 'store'])->name('support.store');
        Route::get('/support/{ticket}/transcript', [SupportChatController::class, 'downloadTranscript'])->name('support.transcript');
        Route::get('/support/{ticket}', [SupportChatController::class, 'show'])->name('support.show');
        Route::get('/support/{ticket}/messages', [SupportChatController::class, 'messages'])->name('support.messages');
        Route::post('/support/{ticket}/reply', [SupportChatController::class, 'reply'])->name('support.reply');
    });
    Route::middleware('plan_module:packages')->group(function () {
        Route::get('/packages', [CustomerController::class, 'packages'])->name('packages');
        Route::get('/packages/create', [CustomerController::class, 'createPackage'])->name('packages.create');
        Route::post('/packages', [CustomerController::class, 'storePackage'])->name('packages.store');
        Route::post('/packages/{id}/receipt', [CustomerController::class, 'uploadPackageReceipt'])->name('packages.receipt.upload');
        Route::get('/packages/{id}/receipts/{receipt}', [CustomerController::class, 'packagePurchaseReceipt'])->name('packages.receipts.show');
        Route::get('/packages/{id}/receipt', [CustomerController::class, 'packageReceipt'])->name('packages.receipt');
        Route::get('/packages/{id}', [CustomerController::class, 'packageDetails'])->name('packages.show');
        Route::get('/pre-alerts', [CustomerPreAlertController::class, 'index'])->name('pre-alerts.index');
        Route::get('/pre-alerts/create', [CustomerPreAlertController::class, 'create'])->name('pre-alerts.create');
        Route::post('/pre-alerts', [CustomerPreAlertController::class, 'store'])->name('pre-alerts.store');
        Route::get('/pre-alerts/{preAlert}/edit', [CustomerPreAlertController::class, 'edit'])->name('pre-alerts.edit');
        Route::put('/pre-alerts/{preAlert}', [CustomerPreAlertController::class, 'update'])->name('pre-alerts.update');
        Route::delete('/pre-alerts/{preAlert}', [CustomerPreAlertController::class, 'destroy'])->name('pre-alerts.destroy');
        Route::get('/pre-alert-receipts/{receipt}', [CustomerPreAlertController::class, 'receipt'])->name('pre-alerts.receipt');
    });

    Route::middleware('plan_module:packages')->group(function () {
        Route::get('/purchase-requests', [CustomerPurchaseRequestController::class, 'index'])->name('purchase-requests.index');
        Route::get('/purchase-requests/create', [CustomerPurchaseRequestController::class, 'create'])->name('purchase-requests.create');
        Route::post('/purchase-requests', [CustomerPurchaseRequestController::class, 'store'])->name('purchase-requests.store');
        Route::get('/purchase-requests/{purchaseRequest}/edit', [CustomerPurchaseRequestController::class, 'edit'])->name('purchase-requests.edit');
        Route::put('/purchase-requests/{purchaseRequest}', [CustomerPurchaseRequestController::class, 'update'])->name('purchase-requests.update');
        Route::delete('/purchase-requests/{purchaseRequest}', [CustomerPurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');
        Route::get('/purchase-requests/receipts/{receipt}', [CustomerPurchaseRequestController::class, 'receipt'])->name('purchase-requests.receipt');
    });

    Route::middleware('plan_module:invoices,payments')->group(function () {
        Route::get('/invoices', [CustomerController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{id}/print', [CustomerController::class, 'invoicePrint'])->name('invoices.print');
        Route::get('/invoices/{id}/thermal', [CustomerController::class, 'invoiceThermal'])->name('invoices.thermal');
        Route::get('/invoices/{id}/download', [CustomerController::class, 'invoiceDownload'])->name('invoices.download');
        Route::post('/invoices/{id}/payment-receipt', [CustomerController::class, 'uploadInvoiceReceipt'])->name('invoices.receipt.upload');
        Route::get('/invoices/{id}', [CustomerController::class, 'invoiceDetails'])->name('invoices.show');
    });
    Route::middleware('plan_module:delivery')->group(function () {
        Route::get('/delivery', [DeliveryRequestController::class, 'customerIndex'])->name('delivery.index');
        Route::get('/delivery/history', [DeliveryRequestController::class, 'customerHistory'])->name('delivery.history');
        Route::get('/delivery/create', [DeliveryRequestController::class, 'create'])->name('delivery.create');
        Route::post('/delivery', [DeliveryRequestController::class, 'store'])->name('delivery.store');
    });
    Route::get('/authorized-users', [CustomerController::class, 'authorizedUsers'])->name('authorized-users.index');
    Route::get('/authorized-users/create', [CustomerController::class, 'createAuthorizedUser'])->name('authorized-users.create');
    Route::post('/authorized-users', [CustomerController::class, 'storeAuthorizedUser'])->name('authorized-users.store');
    Route::get('/authorized-users/{authorizedUser}/edit', [CustomerController::class, 'editAuthorizedUser'])->name('authorized-users.edit');
    Route::put('/authorized-users/{authorizedUser}', [CustomerController::class, 'updateAuthorizedUser'])->name('authorized-users.update');
    Route::delete('/authorized-users/{authorizedUser}', [CustomerController::class, 'destroyAuthorizedUser'])->name('authorized-users.destroy');
    Route::get('/account/delete', [CustomerController::class, 'deleteAccount'])->name('account.delete');
    Route::delete('/account', [CustomerController::class, 'destroyAccount'])->name('account.destroy');
});

Route::post('/courier/statuses', [ApiWmsSyncController::class, 'status'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);






