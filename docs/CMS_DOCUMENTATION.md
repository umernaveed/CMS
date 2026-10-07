# CMS Developer Documentation

## Purpose

This application is the main Courier SaaS CMS. It manages courier tenants, customer accounts, packages, invoices, manifests, finance, delivery, support, notifications, mobile customer APIs, and synchronization with the WMS application.

The CMS lives at the repository root:

```text
C:\xampp\htdocs\cms
```

The WMS application is separate and lives under:

```text
C:\xampp\htdocs\cms\warehouse\wms
```

## Technology Stack

- PHP: `^8.0.2`
- Framework: Laravel `^9.19`
- Database: MySQL
- Frontend: Blade templates, Bootstrap-style assets, Vite
- API auth: Laravel Sanctum for mobile API
- Tables/UI helpers: Yajra DataTables
- Package manager: Composer and npm

Important package files:

- `composer.json`
- `package.json`
- `vite.config.js`

## Main Setup

From `C:\xampp\htdocs\cms`:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
php artisan serve
```

For XAMPP, the app may also be served through Apache from the `public` directory. Make sure the virtual host points to:

```text
C:\xampp\htdocs\cms\public
```

## Environment Variables

The root `.env.example` contains the expected CMS configuration. Important keys include:

```env
APP_URL=http://localhost
APP_DOMAIN=localhost
CENTRAL_DOMAINS=localhost
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp

WMS_BASE_URL=https://hub.freitora.online/wms
WMS_SYNC_TOKEN=
WMS_SYNC_TIMEOUT=10
```

Do not commit production `.env` values. `WMS_SYNC_TOKEN` must match the token configured in the WMS app when synchronization is enabled.

## Application Structure

Important directories:

```text
app/Http/Controllers       Web and API controllers
app/Http/Middleware        Auth, permission, tenant, audit, and safety middleware
app/Models                 Eloquent models
database/migrations        Database schema
database/seeders           Default data and permissions
resources/views            Blade screens
routes/web.php             Browser routes
routes/api.php             API routes
public                     Public entrypoint and assets
storage                    Logs, cache, uploaded/generated files
```

## Core User Areas

The CMS has three main user areas:

- Superadmin: manages platform-wide tenants, logistics, finance, staff, plans, settings, reports, audit logs, and maintenance.
- Courier tenant admin/staff: manages a courier workspace, customers, packages, warehouse views, invoices, finance, manifests, rates, branches, roles, settings, delivery, and support.
- Customer: manages packages, pre-alerts, purchase requests, invoices, delivery requests, wallet/top-ups, support, authorized users, and account settings.

The root route `/` redirects users to the first screen they are allowed to access based on `user_type` and permissions.

## Authentication And Authorization

Authentication routes are in `routes/web.php`:

- `/login`
- `/forgot-password`
- `/reset-password/{token}`
- `/register-tenant`
- `/register-customer`
- `/logout`
- `/tech-support`

Main middleware:

- `auth`: requires login
- `user_type`: restricts by user type, such as `super_admin`, `tenant_admin`, `staff`, or `customer`
- `permission`: checks named permissions
- `plan_module`: checks subscription/module access
- `ResolveTenantFromHost`: resolves tenant context from host/domain
- `EnforcePortalHostAccess`: protects host-specific portal access
- `ValidateSupportAccessSession`: support access session checks
- `VerifyDestructiveAction`: extra protection for destructive operations
- `AuditMutatingRequests`: logs mutating requests

## Important Controllers

Superadmin:

- `SuperAdminController`
- `SuperadminLogisticsController`
- `SuperadminFinanceController`
- `SuperadminStaffController`
- `SubscriptionPlanController`
- `DataMaintenanceController`
- `AccessCodeController`

Courier workspace:

- `CourierController`
- `CourierCustomerController`
- `CourierWarehouseController`
- `PackageController`
- `PackageLogController`
- `InvoiceController`
- `FinanceController`
- `ManifestController`
- `WeightRateController`
- `CustomsRateController`
- `DeliveryRequestController`
- `DeliveryAreaController`
- `CourierSettingsController`
- `StaffUserController`
- `TenantRoleController`
- `OutletController`
- `CourierEmailController`

Customer:

- `CustomerController`
- `CustomerPreAlertController`
- `CustomerPurchaseRequestController`
- `CustomerShippingAddressController`

Shared/support:

- `NotificationController`
- `SupportChatController`
- `ProfileController`
- `RateCalculatorController`
- `DataExchangeController`

API:

- `App\Http\Controllers\Api\MobileCustomerController`
- `App\Http\Controllers\Api\WmsSyncController`

## Important Models

Tenant and users:

- `Tenant`
- `User`
- `Customer`
- `Role`
- `Permission`
- `Outlet`
- `AuthorizedUser`

Packages and logistics:

- `Package`
- `PackageLog`
- `PackageStatus`
- `PackageType`
- `PreAlert`
- `PurchaseRequest`
- `Manifest`
- `ManifestItem`
- `WeightRate`
- `ShippingMode`
- `CustomsOffice`
- `LoadingPort`
- `HsCode`

Invoices and finance:

- `Invoice`
- `InvoiceItem`
- `Payment`
- `PaymentMethod`
- `WalletTransaction`
- `FinanceEntry`
- `LedgerCategory`
- `TenantCreditTransaction`
- `SuperadminBill`
- `SuperadminBillingCharge`
- `SuperadminBillPayment`
- `SuperadminFinanceEntry`
- `SuperadminLogisticsInvoice`
- `SuperadminLogisticsInvoiceItem`
- `SuperadminLogisticsInvoicePayment`

Support and messaging:

- `SupportTicket`
- `SupportMessage`
- `SystemNotification`
- `ClientNotification`
- `NotificationDismissal`
- `CrmEmailDraft`
- `CrmEmailQueueItem`
- `EmailLog`
- `MobileDeviceToken`

Settings:

- `SystemSetting`
- `TenantSetting`
- `SubscriptionPlan`
- `TenantSubscription`
- `TenantPayment`

## Main Web Route Groups

Superadmin routes:

```text
/superadmin/*
```

Common modules include dashboard, tenants, staff, logistics, finance, plans, settings, maintenance, notifications, reports, and audit.

Courier workspace routes:

```text
/courier/*
```

Common modules include dashboard, activity, support, CRM emails, customers, authorized users, package exceptions, packages, warehouse, pre-alerts, purchase requests, staff, roles, statuses, settings, maintenance, data import/export, invoices, finance, manifests, weights, customs rates, outlets, delivery, and delivery areas.

Customer routes:

```text
/customer/*
```

Common modules include dashboard, wallet, messages, support, packages, pre-alerts, purchase requests, invoices, delivery, authorized users, and account deletion.

## API Routes

Mobile customer API:

```text
POST /api/mobile/tenant/resolve
POST /api/mobile/login
POST /api/mobile/logout
GET  /api/mobile/bootstrap
GET  /api/mobile/packages
GET  /api/mobile/invoices
GET  /api/mobile/delivery-requests
GET  /api/mobile/addresses
GET  /api/mobile/pre-alerts
GET  /api/mobile/purchase-requests
GET  /api/mobile/wallet
POST /api/mobile/invoices/{invoice}/receipt
POST /api/mobile/device-token
GET  /api/mobile/notifications
```

Most mobile routes require Sanctum authentication after login.

WMS sync API:

```text
POST   /api/wms/customers
POST   /api/wms/customers/export
POST   /api/wms/couriers/export
POST   /api/wms/packages/next-hawb
POST   /api/wms/packages
POST   /api/wms/package-upload
POST   /api/wms/courier/statuses
GET    /api/wms/labels/{trackingNumber}
GET    /api/wms/labels/{trackingNumber}/html
DELETE /api/wms/packages
POST   /api/wms/manifests
```

Compatibility web endpoints also exist:

```text
POST /admin/package-upload
GET  /labels/{trackingNumber}
GET  /labels/{trackingNumber}/html
POST /courier/statuses
```

## CMS And WMS Synchronization

The CMS talks to WMS through `WMS_BASE_URL`, `WMS_SYNC_TOKEN`, and `WMS_SYNC_TIMEOUT`.

The main CMS sync controller is:

```text
app/Http/Controllers/Api/WmsSyncController.php
```

It handles:

- Creating/updating customers from WMS.
- Exporting customers and couriers to WMS.
- Creating/updating packages from WMS.
- Package upload compatibility.
- Package status updates.
- Label HTML/PDF lookup by tracking number.
- Generating next HAWB/package numbers.
- Creating/updating manifests.

Related migration:

```text
database/migrations/2026_07_28_000001_add_wms_sync_columns.php
```

When changing sync fields, update both the CMS controller and the WMS `SaasSyncController`/sync callers together.

## Database And Seeders

Run migrations with:

```bash
php artisan migrate
```

Run seeders with:

```bash
php artisan db:seed
```

Important seeders:

- `DefaultTenantSeeder`
- `PackageStatusesSeeder`
- `PaymentMethodsSeeder`
- `RolesAndPermissionsSeeder`
- `SubscriptionPlansSeeder`
- `SystemSettingsSeeder`

Permission-driven screens depend heavily on seeded permissions. If a new screen is added, make sure the route middleware, sidebar/menu display, and seeders are all updated.

## Common Development Tasks

Clear cache:

```bash
php artisan optimize:clear
```

Rebuild frontend:

```bash
npm run build
```

Run tests:

```bash
php artisan test
```

Create a migration:

```bash
php artisan make:migration add_field_to_table_name
```

Create a controller:

```bash
php artisan make:controller ExampleController
```

## Adding A New Courier Module

1. Add or update the relevant model/migration.
2. Add controller methods under `app/Http/Controllers`.
3. Add routes inside the courier route group in `routes/web.php`.
4. Protect routes with `user_type` and `permission` middleware.
5. Add the permission to `RolesAndPermissionsSeeder`.
6. Add Blade views under `resources/views`.
7. Add menu/sidebar links only for users with the matching permission.
8. Add import/export or print actions if the module follows existing CMS patterns.

## Adding A New Customer Feature

1. Add database changes through migrations.
2. Add customer-facing routes inside the `/customer` group.
3. Use `plan_module` middleware if the feature is plan-gated.
4. Keep customer queries scoped to the authenticated customer and tenant.
5. Update mobile API endpoints if the same feature must appear in the mobile app.

## Files And Uploads

Uploaded/generated files are stored under `storage` or public paths depending on the feature. Keep generated files out of source control where possible. Before changing upload behavior, verify:

- Allowed extensions and MIME types.
- Maximum file size.
- Whether the file is tenant-specific.
- Whether a public URL or authenticated download route is required.

## Safety Notes

- This is a multitenant system. Always scope courier data by tenant.
- Do not bypass permission middleware for admin convenience.
- Be careful with maintenance routes such as backup, restore, clear, and sync.
- WMS sync changes can affect package, customer, courier, manifest, and label workflows.
- Avoid editing production `.env` values or committing credentials.
- Test destructive flows with non-production data first.

## Handoff Checklist

Before handing this project to another developer, provide:

- CMS database dump or migration/seed instructions.
- Current `.env` values through a secure channel.
- Admin/superadmin test login.
- WMS base URL and sync token details.
- Any cron/queue process information if queues are changed from `sync`.
- Production deploy steps and server/PHP version.
- Known custom business rules for rates, invoices, package statuses, and billing.
