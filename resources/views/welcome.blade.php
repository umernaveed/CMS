<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Courier SaaS') }}</title>
    <style>
        :root {
            --brand: #135fd2;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #dbe3ef;
            --soft: #f6f8fb;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: #fff;
        }
        .site-shell { min-height: 100vh; display: grid; grid-template-rows: auto 1fr; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 18px clamp(18px, 4vw, 56px);
            border-bottom: 1px solid var(--line);
        }
        .brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 900; }
        .brand img { width: 38px; height: 38px; object-fit: contain; }
        .top-actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 8px;
            border: 1px solid var(--line);
            text-decoration: none;
            color: var(--ink);
            font-weight: 800;
            font-size: 14px;
        }
        .btn.primary { border-color: var(--brand); background: var(--brand); color: #fff; }
        .hero {
            display: grid;
            grid-template-columns: minmax(0, .95fr) minmax(320px, 1.05fr);
            align-items: center;
            gap: clamp(24px, 5vw, 64px);
            padding: clamp(32px, 6vw, 74px) clamp(18px, 4vw, 56px);
        }
        .hero-copy { max-width: 640px; }
        h1 {
            margin: 0 0 16px;
            font-size: clamp(34px, 5vw, 64px);
            line-height: 1.02;
            letter-spacing: 0;
        }
        .lead { margin: 0 0 24px; color: var(--muted); font-size: 18px; line-height: 1.6; }
        .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 26px; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .stat { border: 1px solid var(--line); border-radius: 8px; padding: 14px; background: var(--soft); }
        .stat strong { display: block; font-size: 21px; }
        .stat span { color: var(--muted); font-size: 13px; }
        .visual {
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            background: var(--soft);
            box-shadow: 0 24px 60px rgba(15, 23, 42, .12);
        }
        .visual-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: #fff;
            font-weight: 900;
        }
        .visual-body { display: grid; gap: 12px; padding: 16px; }
        .shipment {
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }
        .icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: #e8f1ff;
            color: var(--brand);
            font-weight: 900;
        }
        .shipment strong, .shipment span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .shipment span { color: var(--muted); font-size: 13px; }
        .badge { padding: 6px 9px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 900; }
        @media (max-width: 860px) {
            .hero { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .top-actions, .hero-actions, .btn { width: 100%; }
        }
    </style>
</head>
<body>
    @php
        $logo = \App\Models\SystemSetting::where('key', 'app_logo')->value('value')
            ?: \App\Models\SystemSetting::where('key', 'company_logo')->value('value');
        $logoUrl = $logo ? asset(ltrim($logo, '/')) : asset('uploads/platform/app-logo-20260505221831.png');
        $courierRegistrationValue = strtolower(trim((string) (\App\Models\SystemSetting::where('key', 'courier_registration_enabled')->value('value') ?? '1')));
        $courierRegistrationEnabled = ! in_array($courierRegistrationValue, ['0', 'false', 'off', 'no'], true);
    @endphp
    <div class="site-shell">
        <header class="topbar">
            <div class="brand">
                <img src="{{ $logoUrl }}" alt="{{ config('app.name', 'Courier SaaS') }}">
                <span>{{ config('app.name', 'Courier SaaS') }}</span>
            </div>
            <nav class="top-actions" aria-label="Account links">
                <a href="{{ route('login') }}" class="btn">Staff Login</a>
                <a href="{{ route('login') }}" class="btn">Customer Login</a>
                @if($courierRegistrationEnabled)
                    <a href="{{ route('tenant.register') }}" class="btn primary">Register Courier</a>
                @endif
            </nav>
        </header>

        <main class="hero">
            <section class="hero-copy">
                <h1>Courier operations, billing, and customer portals in one system</h1>
                <p class="lead">Manage packages, manifests, invoices, delivery requests, customers, support, loyalty tiers, and courier-branded communications from one workspace.</p>
                <div class="hero-actions">
                    @if($courierRegistrationEnabled)
                        <a href="{{ route('tenant.register') }}" class="btn primary">Start Courier Setup</a>
                    @endif
                    <a href="{{ route('customer.register') }}" class="btn">Create Customer Account</a>
                </div>
                <div class="stats" aria-label="Platform capabilities">
                    <div class="stat"><strong>3</strong><span>Portal types</span></div>
                    <div class="stat"><strong>CSV</strong><span>Imports and exports</span></div>
                    <div class="stat"><strong>SMTP</strong><span>Courier email control</span></div>
                </div>
            </section>

            <section class="visual" aria-label="Operations preview">
                <div class="visual-head">
                    <span>Today in Operations</span>
                    <span class="badge">Live</span>
                </div>
                <div class="visual-body">
                    <div class="shipment"><div class="icon">PK</div><div><strong>NCLS000184</strong><span>Package received for MBX1028</span></div><span class="badge">Received</span></div>
                    <div class="shipment"><div class="icon">INV</div><div><strong>INV-20260521</strong><span>Invoice generated with loyalty discount</span></div><span class="badge">Ready</span></div>
                    <div class="shipment"><div class="icon">DL</div><div><strong>Delivery Request</strong><span>Route assigned to branch team</span></div><span class="badge">Assigned</span></div>
                    <div class="shipment"><div class="icon">CRM</div><div><strong>Status Email</strong><span>Courier-branded SMTP notification sent</span></div><span class="badge">Sent</span></div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
