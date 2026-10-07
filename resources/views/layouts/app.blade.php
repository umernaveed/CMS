<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>@yield('title', $platformName ?? config('app.name', 'Courier SaaS'))</title>
    <?php if (! empty($platformFavicon)) { ?>
        <link rel="icon" href="{{ $platformFavicon }}">
    <?php } ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f3f4f6;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --surface-tint: #eef6ff;
            --ink: #111827;
            --muted: #64748b;
            --line: #e5e7eb;
            --nav: {{ $platformSettings['brand_secondary_color'] ?? '#073b70' }};
            --nav-soft: {{ $platformSettings['brand_primary_color'] ?? '#135fd2' }};
            --brand: {{ $platformSettings['brand_primary_color'] ?? '#135fd2' }};
            --brand-strong: {{ $platformSettings['brand_secondary_color'] ?? '#073b70' }};
            --brand-glow: rgba(19, 95, 210, .28);
            --blue: #135fd2;
            --purple: #5b12ff;
            --amber: #f59e0b;
            --red: #dc2626;
            --shadow: 0 14px 35px rgba(15, 23, 42, .07);
            --shadow-strong: 0 22px 48px rgba(15, 23, 42, .11);
        }
        * { letter-spacing: 0; }
        body {
            margin: 0;
            background:
                radial-gradient(circle at 22% 8%, rgba(19, 95, 210, .09), transparent 27%),
                linear-gradient(180deg, #f8fbff 0, var(--bg) 360px);
            color: var(--ink);
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 15px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { width: 100%; max-width: 100%; overflow-x: hidden; }
        a { color: var(--brand); text-decoration: none; }
        a:hover { color: var(--brand-strong); }
        .app-shell { min-height: 100vh; }
        .app-shell,
        .content-wrap,
        .page-pad,
        .topbar,
        .panel,
        .dashboard-card,
        .table-responsive,
        main {
            min-width: 0;
            max-width: 100%;
        }
        .sidebar {
            width: 282px;
            min-height: 100vh;
            position: fixed;
            inset: 0 auto 0 0;
            overflow-y: auto;
            background:
                linear-gradient(180deg, rgba(255,255,255,.08), transparent 210px),
                linear-gradient(160deg, var(--nav), #062a52 64%, #061d3d);
            color: #fff;
            padding: 22px 16px;
            border-right: 1px solid rgba(255,255,255,.12);
            box-shadow: 18px 0 50px rgba(15, 23, 42, .08);
        }
        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 4px 8px 24px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            margin-bottom: 18px;
        }
        .brand-mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: linear-gradient(180deg, var(--purple), #8d48ff 56%, #b87aff);
            color: #fff;
            box-shadow: 0 10px 22px rgba(19, 95, 210, .28);
            flex: 0 0 auto;
        }
        .brand-title { font-size: 16px; font-weight: 750; line-height: 1.15; }
        .brand-subtitle { color: #94a3b8; font-size: 12px; margin-top: 3px; }
        .sidebar-section {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 18px 12px 8px;
        }
        .sidebar .nav-link {
            color: #dbeafe;
            display: flex;
            align-items: center;
            gap: 11px;
            border-radius: 8px;
            padding: 11px 12px;
            margin-bottom: 4px;
            font-weight: 650;
            transition: background .16s ease, color .16s ease;
        }
        .sidebar .nav-link i {
            width: 22px;
            text-align: center;
            color: #94a3b8;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: var(--nav-soft);
        }
        .sidebar .nav-link.active i { color: #fff; }
        .content-wrap {
            margin-left: 282px;
            min-height: 100vh;
        }
        .topbar {
            min-height: 78px;
            margin: 18px 24px 0;
            padding: 14px 18px;
            background:
                linear-gradient(135deg, rgba(255,255,255,.98), rgba(248,250,252,.94)),
                var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }
        .page-title {
            font-size: 24px;
            line-height: 1.2;
            font-weight: 800;
            margin: 0;
        }
        .breadcrumb-line { color: var(--muted); font-size: 14px; margin-top: 4px; }
        .topbar-action {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: var(--surface-soft);
            color: var(--ink);
            display: grid;
            place-items: center;
            position: relative;
            transition: border-color .16s ease, background .16s ease;
        }
        .topbar-action:hover {
            border-color: var(--brand);
            background: #fff;
        }
        .topbar-action.has-unread {
            color: var(--red);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, .12);
        }
        .notification-badge {
            position: absolute;
            top: -7px;
            right: -7px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: var(--red);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
        }
        .notification-menu {
            width: min(380px, calc(100vw - 28px));
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow-strong);
            padding: 0;
            overflow: hidden;
        }
        .notification-item {
            padding: 12px 14px;
            border-top: 1px solid var(--line);
            white-space: normal;
        }
        .notification-item:first-child { border-top: 0; }
        .nav-link.has-unread-notification {
            background: rgba(220, 38, 38, .12);
            color: #fff;
        }
        body.customer-portal .nav-link.has-unread-notification {
            background: rgba(220, 38, 38, .1);
            color: #991b1b;
        }
        .notification-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            display: inline-block;
        }
        .user-menu {
            min-width: 218px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface-soft);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
        }
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--blue);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 800;
            flex: 0 0 auto;
        }
        .page-pad { padding: 24px; }
        .panel,
        .dashboard-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
            transition: border-color .16s ease, box-shadow .16s ease;
            max-width: 100%;
        }
        .panel:hover,
        .dashboard-card:hover {
            border-color: #d3dce9;
            box-shadow: var(--shadow-strong);
        }
        .metric,
        .dashboard-card {
            min-height: 140px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .metric::before,
        .dashboard-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, var(--purple), var(--blue), var(--nav));
        }
        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 20px;
            margin-bottom: 18px;
        }
        .metric-value {
            font-size: 34px;
            line-height: 1;
            font-weight: 850;
            margin-bottom: 8px;
        }
        .metric-label {
            color: var(--muted);
            font-weight: 700;
            margin-bottom: 12px;
        }
        .metric-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-weight: 650;
            color: var(--muted);
        }
        .metric-progress {
            width: 100%;
            height: 7px;
            border-radius: 999px;
            background: #e5e7eb;
            margin-top: 16px;
            overflow: hidden;
        }
        .metric-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--brand);
        }
        .text-muted { color: var(--muted) !important; }
        .badge-soft {
            background: linear-gradient(180deg, #eff6ff, #dbeafe);
            color: var(--brand-strong);
            border: 1px solid #bfdbfe;
            font-weight: 750;
        }
        .btn {
            border-radius: 8px;
            font-weight: 750;
            white-space: nowrap;
            transition: transform .16s ease, box-shadow .16s ease, background .16s ease, border-color .16s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-sm {
            min-height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .table-responsive .dropdown-menu {
            z-index: 3050;
        }
        @media (max-width: 992px) {
            .table-responsive .dropdown {
                position: static;
            }
            .table-responsive .dropdown-menu.show {
                position: fixed !important;
                max-width: calc(100vw - 16px);
                max-height: calc(100vh - 16px);
                overflow-y: auto;
            }
        }
        .dropdown-menu form {
            margin: 0;
        }
        .dropdown-item {
            font-weight: 700;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--brand), var(--brand-strong));
            border-color: var(--brand);
            box-shadow: 0 10px 18px var(--brand-glow);
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background: var(--brand-strong);
            border-color: var(--brand-strong);
        }
        .btn-outline-primary { color: var(--brand); border-color: #bfd3f4; background: #fff; }
        .btn-outline-primary:hover { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn-outline-secondary { background: #fff; border-color: #d8e0eb; color: #475569; }
        .btn-outline-secondary:hover { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }
        .btn-outline-danger { background: #fff; }
        .form-control,
        .form-select {
            border-color: var(--line);
            border-radius: 8px;
            min-height: 42px;
            background-color: #fff;
            max-width: 100%;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 .2rem rgba(19, 95, 210, .14);
        }
        .form-label { color: #334155; font-weight: 750; }
        .table { color: var(--ink); margin-bottom: 0; }
        .table thead th {
            color: #475569;
            background: linear-gradient(180deg, #f8fafc, #f1f5f9);
            border-bottom: 1px solid var(--line);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 850;
            white-space: nowrap;
        }
        .table > :not(caption) > * > * {
            padding: .95rem .9rem;
            vertical-align: middle;
            border-color: var(--line);
        }
        .table tbody tr { transition: background .14s ease; }
        .table tbody tr:hover { background: #f8fafc; }
        .table-responsive {
            border-radius: 8px;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
        }
        .table-responsive > .table {
            min-width: max-content;
        }
        .table td,
        .table th {
            vertical-align: middle;
        }
        .dataTables_wrapper {
            padding: 14px;
        }
        .dataTables_wrapper .row:first-child,
        .dataTables_wrapper .row:last-child {
            align-items: center;
            row-gap: 12px;
        }
        .dataTables_length label,
        .dataTables_filter label,
        .dataTables_info {
            color: var(--muted);
            font-weight: 650;
        }
        .dataTables_filter input,
        .dataTables_length select {
            border: 1px solid var(--line);
            border-radius: 8px;
            min-height: 38px;
            margin-left: 8px;
        }
        .dataTables_filter input:focus,
        .dataTables_length select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 .2rem rgba(19, 95, 210, .12);
            outline: 0;
        }
        .page-item .page-link {
            border-color: var(--line);
            color: var(--brand);
            font-weight: 750;
            min-width: 36px;
            text-align: center;
        }
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--brand), var(--brand-strong));
            border-color: var(--brand);
            box-shadow: 0 8px 18px var(--brand-glow);
        }
        /* Pagination safety net for Laravel/Tailwind paginator markup on Bootstrap pages. */
        nav[role="navigation"] .pagination,
        .pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0;
            margin: 0;
        }
        nav[role="navigation"] svg,
        nav[role="navigation"] .w-5,
        nav[role="navigation"] .h-5,
        nav[role="navigation"] a svg,
        .pagination svg,
        svg.w-5.h-5 {
            display: inline-block !important;
            width: 1rem !important;
            height: 1rem !important;
            min-width: 1rem !important;
            min-height: 1rem !important;
            max-width: 1rem !important;
            max-height: 1rem !important;
            flex: 0 0 1rem !important;
            vertical-align: text-bottom;
        }
        nav[role="navigation"] a,
        nav[role="navigation"] span {
            max-width: 100%;
        }
        nav[role="navigation"] a[rel="next"],
        nav[role="navigation"] a[rel="prev"],
        nav[role="navigation"] span[aria-disabled="true"] {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: auto !important;
            height: 38px !important;
            min-width: 38px !important;
            overflow: hidden;
        }
        nav[role="navigation"] p {
            margin-bottom: 0;
        }
        nav[role="navigation"] > div {
            max-width: 100%;
        }
        .table td.text-end {
            white-space: nowrap;
            min-width: max-content;
        }
        .table td.text-end > .d-inline-flex,
        .table td.text-end > .d-flex,
        .table td.text-end .d-inline-flex,
        .table td.text-end .d-flex {
            flex-wrap: nowrap !important;
            align-items: center;
            justify-content: flex-end;
            white-space: nowrap;
        }
        .table td.text-end form {
            display: inline-flex;
            flex: 0 0 auto;
            margin: 0;
        }
        .table td.text-end .btn {
            flex: 0 0 auto;
        }
        .page-pad > .d-flex.justify-content-between,
        .page-pad > .d-flex.align-items-center,
        .page-pad > .d-flex.align-items-start {
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-pad > .d-flex.justify-content-between > * {
            min-width: 0;
        }
        form.panel,
        .panel form,
        .panel .row,
        form.panel .row {
            max-width: 100%;
        }
        .row.align-items-end {
            min-width: 0;
        }
        .row.align-items-end > [class*="col"] {
            min-width: 0;
        }
        .row.align-items-end .d-flex {
            min-width: 0;
            flex-wrap: wrap;
        }
        .row.align-items-end .btn {
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .row.align-items-end .d-flex.gap-2 > .btn,
        .row.align-items-end .d-flex.gap-2 > a.btn {
            flex: 1 1 auto;
        }
        form.panel {
            overflow: hidden;
        }
        .alert { border-radius: 8px; border: 1px solid transparent; }
        .app-alert-stack {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }
        .app-alert-card {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr) auto;
            gap: 14px;
            align-items: flex-start;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background:
                linear-gradient(135deg, rgba(255,255,255,.98), rgba(248,250,252,.94)),
                #fff;
            box-shadow: var(--shadow-strong);
            position: relative;
            overflow: hidden;
            animation: alertIn .22s ease-out;
        }
        .app-alert-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--alert-color, var(--brand));
        }
        .app-alert-icon {
            width: 42px;
            height: 42px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: color-mix(in srgb, var(--alert-color, var(--brand)) 12%, white);
            color: var(--alert-color, var(--brand));
            font-size: 17px;
        }
        .app-alert-title {
            color: #0f172a;
            font-weight: 900;
            margin-bottom: 3px;
        }
        .app-alert-message {
            color: var(--muted);
            line-height: 1.45;
        }
        .app-alert-actions {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .app-alert-close {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            color: #64748b;
            display: grid;
            place-items: center;
        }
        .app-alert-close:hover {
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .app-alert-card.alert-successful { --alert-color: #16a34a; }
        .app-alert-card.alert-info-modern { --alert-color: #135fd2; }
        .app-alert-card.alert-warning-modern { --alert-color: #f59e0b; }
        .app-alert-card.alert-danger-modern { --alert-color: #dc2626; }
        @keyframes alertIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .app-confirm-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2050;
            display: grid;
            place-items: center;
            padding: 18px;
            background: rgba(15, 23, 42, .58);
            backdrop-filter: blur(7px);
            animation: confirmFadeIn .16s ease-out;
        }
        .app-confirm-backdrop[hidden] { display: none; }
        .app-confirm-modal {
            width: min(520px, 100%);
            border-radius: 18px;
            background:
                linear-gradient(135deg, rgba(19, 95, 210, .08), rgba(255,255,255,0) 42%),
                #fff;
            border: 1px solid rgba(255,255,255,.78);
            box-shadow: 0 28px 90px rgba(2, 6, 23, .34);
            overflow: hidden;
            animation: confirmRise .18s ease-out;
        }
        .app-confirm-head {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 24px 24px 18px;
        }
        .app-confirm-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--brand), var(--brand-strong));
            color: #fff;
            box-shadow: 0 14px 30px var(--brand-glow);
            flex: 0 0 auto;
        }
        .app-confirm-title {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 5px;
        }
        .app-confirm-message {
            color: #475569;
            line-height: 1.5;
            font-size: 15px;
        }
        .app-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 18px 24px 24px;
            background: rgba(248,250,252,.72);
            border-top: 1px solid var(--line);
        }
        .app-confirm-actions .btn {
            min-width: 104px;
            min-height: 42px;
        }
        @keyframes confirmFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes confirmRise {
            from { opacity: 0; transform: translateY(12px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .login-shell {
            min-height: 100vh;
            background: #f3f4f6;
            padding: 28px 0;
        }
        .login-shell.register-shell {
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--brand-strong) 92%, transparent), color-mix(in srgb, var(--brand) 62%, transparent)),
                url("{{ $platformSettings['login_background_url'] ?? 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1600&q=80' }}") center/cover fixed;
        }
        .login-card {
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.8);
            box-shadow: 0 28px 80px rgba(15, 23, 42, .16);
        }
        .login-aside {
            background:
                linear-gradient(120deg, rgba(255,255,255,.06) 0 12%, transparent 12% 24%, rgba(255,255,255,.04) 24% 36%, transparent 36%),
                var(--brand-strong);
            min-height: 520px;
            position: relative;
        }
        .login-aside::before {
            content: "";
            position: absolute;
            inset: 28px auto 28px 28px;
            width: 5px;
            border-radius: 999px;
            background: linear-gradient(180deg, #5b12ff, var(--brand), #fff);
            opacity: .75;
        }
        .login-aside > * { position: relative; }
        .auth-product-tile {
            width: min(260px, 100%);
            padding: 18px;
            border-radius: 18px;
            background: #fff;
            color: #0f172a;
            box-shadow: 0 24px 52px rgba(2, 6, 23, .22);
            border: 1px solid rgba(255,255,255,.8);
        }
        .auth-product-mark {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-strong), var(--brand) 62%, #fff 62%);
            font-size: 28px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, .18);
            margin-bottom: 12px;
        }
        .auth-product-title {
            font-size: 19px;
            line-height: 1.12;
            font-weight: 950;
            margin: 0 0 6px;
        }
        .auth-product-subtitle {
            color: #64748b;
            font-size: 12px;
            line-height: 1.45;
            margin-bottom: 14px;
        }
        .auth-product-point {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 10px;
            border-radius: 10px;
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            margin-top: 8px;
        }
        .auth-product-point i {
            color: var(--brand);
            width: 16px;
            text-align: center;
        }
        .register-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            color: #dbeafe;
            font-size: 12px;
            font-weight: 850;
            text-transform: uppercase;
        }
        .register-feature {
            padding: 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.18);
            background: rgba(2, 6, 23, .16);
            box-shadow: 0 14px 34px rgba(2, 6, 23, .12);
        }
        @media (max-width: 991px) {
            .login-aside {
                min-height: 0;
            }
            .auth-product-tile {
                margin-bottom: 24px;
            }
        }
        .register-form-pane {
            background:
                linear-gradient(135deg, rgba(91,18,255,.05), rgba(19,95,210,.03) 42%, rgba(255,255,255,0) 42%),
                #fff;
        }
        .register-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--brand-strong);
            font-weight: 900;
        }
        .register-section-title i {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: #dbeafe;
            color: var(--brand);
        }
        .plan-option {
            cursor: pointer;
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }
        .plan-option:hover {
            transform: translateY(-2px);
            border-color: #93c5fd;
            box-shadow: 0 18px 36px rgba(19, 95, 210, .12);
        }

        /* ============================================ */
        /* COURIER ADMIN PORTAL - OLOG STYLE            */
        /* ============================================ */
        body.courier-portal {
            --sidebar-text: #ffffff;
            --sidebar-muted-text: #f8fafc;
            --sidebar-icon-text: #e2e8f0;
            --sidebar-active-text: #ffffff;
            background: #f8fafc;
            color: #0f172a;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }
        body.courier-portal .app-shell {
            display: flex;
            min-height: 100vh;
            height: 100vh;
            overflow: hidden;
        }
        body.courier-portal .sidebar {
            width: 256px;
            flex: 0 0 256px;
            position: relative;
            inset: auto;
            height: 100vh;
            min-height: 0;
            padding: 0;
            background: var(--brand-strong);
            color: var(--sidebar-text);
            border-right: 0;
            box-shadow: none;
            overflow-y: auto;
        }
        body.courier-portal .brand-wrap {
            min-height: 64px;
            margin: 0;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255,255,255,.14);
            background: var(--brand-strong);
            gap: 10px;
        }
        body.courier-portal .brand-mark {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--brand);
            box-shadow: none;
            font-weight: 900;
        }
        body.courier-portal .brand-title {
            color: var(--sidebar-text);
            font-size: 20px;
            font-weight: 850;
        }
        body.courier-portal .brand-subtitle {
            color: var(--sidebar-muted-text);
            font-size: 12px;
        }
        body.courier-portal .courier-sidebar-nav {
            padding: 12px;
        }
        body.courier-portal .sidebar-section {
            color: var(--sidebar-muted-text);
            font-size: 11px;
            padding: 16px 12px 7px;
        }
        body.courier-portal .sidebar .nav-link,
        body.courier-portal .courier-menu-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--sidebar-text);
            background: transparent;
            border: 0;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 2px;
            font-size: 14px;
            font-weight: 650;
            text-align: left;
            transition: background .16s ease, color .16s ease;
        }
        body.courier-portal .sidebar .nav-link i,
        body.courier-portal .courier-menu-toggle i:first-child {
            width: 18px;
            color: var(--sidebar-icon-text);
            text-align: center;
        }
        body.courier-portal .sidebar .nav-link:hover,
        body.courier-portal .courier-menu-toggle:hover {
            color: var(--sidebar-text);
            background: color-mix(in srgb, var(--brand) 22%, var(--brand-strong));
            transform: none;
        }
        body.courier-portal .sidebar .nav-link.active {
            color: var(--sidebar-active-text);
            background: var(--brand);
            transform: none;
        }
        body.courier-portal .sidebar .nav-link.active i { color: var(--sidebar-active-text); }
        body.courier-portal .courier-menu-toggle[aria-expanded="true"] {
            background: color-mix(in srgb, var(--brand) 18%, var(--brand-strong));
            color: var(--sidebar-text);
        }
        body.courier-portal .courier-menu-toggle .fa-chevron-right {
            margin-left: auto;
            width: auto;
            color: var(--sidebar-icon-text);
            flex: 0 0 16px;
            font-size: 13px;
            line-height: 1;
            transition: transform .16s ease;
        }
        body.courier-portal .courier-menu-toggle[aria-expanded="true"] .fa-chevron-right {
            transform: rotate(90deg);
            color: var(--sidebar-icon-text);
        }
        body.courier-portal .courier-menu-title {
            flex: 1 1 auto;
            color: inherit;
            text-decoration: none;
        }
        body.courier-portal .courier-menu-toggle .courier-menu-title:hover {
            color: var(--sidebar-text);
        }
        body.courier-portal .courier-subnav {
            padding: 3px 0 6px;
        }
        body.courier-portal .courier-subnav .nav-link {
            padding: 8px 12px 8px 44px;
            font-size: 13px;
            color: var(--sidebar-muted-text);
        }
        body.courier-portal .courier-subnav .nav-link:hover {
            color: var(--sidebar-text);
        }
        body.courier-portal .courier-subnav .nav-link.active {
            color: var(--sidebar-active-text);
        }
        body.courier-portal .courier-sidebar-footer {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid color-mix(in srgb, var(--sidebar-text) 22%, transparent);
        }
        body.courier-portal .content-wrap {
            flex: 1 1 auto;
            margin-left: 0;
            height: 100vh;
            min-height: 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        body.courier-portal .topbar {
            min-height: 64px;
            margin: 0;
            padding: 12px 24px;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 0;
            background: #fff;
            box-shadow: none;
            backdrop-filter: none;
        }
        body.courier-portal .page-title {
            font-size: 20px;
            font-weight: 750;
            text-transform: capitalize;
        }
        body.courier-portal .breadcrumb-line { display: none; }
        body.courier-portal .courier-top-search {
            position: relative;
            width: min(360px, 34vw);
        }
        body.courier-portal .courier-top-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13px;
        }
        body.courier-portal .courier-top-search input {
            width: 100%;
            min-height: 36px;
            border: 0;
            border-radius: 8px;
            background: #f1f5f9;
            padding: 8px 12px 8px 36px;
            color: #334155;
            font-size: 14px;
        }
        body.courier-portal .courier-top-search input:focus {
            outline: 0;
            background: #fff;
            box-shadow: 0 0 0 3px var(--brand-glow);
        }
        body.courier-portal .topbar-action {
            border: 0;
            border-radius: 999px;
            background: #fff;
            color: #64748b;
        }
        body.courier-portal .topbar-action:hover { background: #f1f5f9; }
        body.courier-portal .user-menu {
            min-width: 0;
            border: 0;
            background: #fff;
            padding: 0;
        }
        body.courier-portal .avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--brand), var(--brand-strong));
            color: #fff;
            box-shadow: 0 0 0 2px #f1f5f9;
        }
        body.courier-portal .page-pad {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 24px;
            background: #f8fafc;
        }
        body.courier-portal .page-pad > * {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
        }
        body.courier-portal .panel,
        body.courier-portal .dashboard-card {
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        body.courier-portal .panel:hover,
        body.courier-portal .dashboard-card:hover {
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
            border-color: #e2e8f0;
        }
        body.courier-portal .btn { border-radius: 8px; }
        
        /* ============================================ */
        /* CUSTOMER PORTAL STYLES - ONLY FOR CUSTOMER   */
        /* ============================================ */
        body.customer-portal {
            background: #f8fafc;
            color: #1e293b;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 14px;
        }
        body.customer-portal .app-shell {
            display: flex;
            height: 100vh;
            min-height: 100vh;
            overflow: hidden;
        }
        body.customer-portal .sidebar {
            width: 260px;
            position: relative;
            inset: auto;
            flex: 0 0 260px;
            min-height: 100vh;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #fff;
            color: #334155;
            padding: 0;
            border-right: 1px solid #e2e8f0;
            box-shadow: none;
            overflow-y: auto;
            transition: flex 0.3s ease, width 0.3s ease;
        }
        body.customer-portal .sidebar.collapsed {
            flex: 0 0 72px;
            width: 72px;
        }
        body.customer-portal .sidebar.collapsed .brand-text,
        body.customer-portal .sidebar.collapsed .nav-text,
        body.customer-portal .sidebar.collapsed .sidebar-section,
        body.customer-portal .sidebar.collapsed .customer-menu-chevron,
        body.customer-portal .sidebar.collapsed .customer-nav-control span,
        body.customer-portal .sidebar.collapsed .customer-mailbox-card .label,
        body.customer-portal .sidebar.collapsed .customer-mailbox-card .value span,
        body.customer-portal .sidebar.collapsed .customer-logout-btn span {
            display: none;
        }
        body.customer-portal .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 10px;
        }
        body.customer-portal .sidebar.collapsed .nav-link i {
            margin: 0;
            font-size: 18px;
        }
        body.customer-portal .sidebar.collapsed .customer-subnav {
            position: absolute;
            left: 72px;
            background: #fff;
            min-width: 180px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 6px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 100;
        }
        body.customer-portal .sidebar.collapsed .customer-subnav.hidden {
            display: none;
        }
        body.customer-portal .sidebar.collapsed .customer-subnav .nav-link {
            justify-content: flex-start;
            padding: 7px 10px;
        }
        body.customer-portal .sidebar.collapsed .customer-subnav .nav-link i {
            font-size: 12px;
            width: 18px;
        }
        body.customer-portal .sidebar.collapsed .customer-subnav .nav-text {
            display: inline;
            font-size: 13px;
        }
        body.customer-portal .brand-wrap {
            position: relative;
            margin: 0;
            padding: 16px 20px;
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        body.customer-portal .sidebar.collapsed .brand-wrap {
            padding: 16px 12px;
            justify-content: center;
        }
        body.customer-portal .sidebar-collapse-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 5px;
            border-radius: 6px;
            transition: all 0.2s ease;
            margin-left: auto;
        }
        body.customer-portal .sidebar-collapse-btn i {
            font-size: 16px;
            line-height: 1;
        }
        body.customer-portal .sidebar-collapse-btn:hover {
            background: #f1f5f9;
            color: var(--brand);
        }
        body.customer-portal .sidebar.collapsed .sidebar-collapse-btn {
            margin-left: 0;
            transform: rotate(180deg);
        }
        body.customer-portal .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--brand);
            box-shadow: none;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        body.customer-portal .brand-mark span {
            font-size: 18px;
        }
        body.customer-portal .brand-title {
            color: #0f172a;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0;
        }
        body.customer-portal .brand-subtitle {
            color: var(--brand);
            font-weight: 700;
            font-size: 11px;
        }
        body.customer-portal .sidebar-nav {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 12px 10px;
        }
        body.customer-portal .sidebar .nav {
            gap: 2px;
        }
        body.customer-portal .sidebar-section {
            color: #94a3b8;
            padding: 14px 12px 6px;
            font-size: 10px;
            transition: opacity 0.2s ease;
        }
        body.customer-portal .sidebar .nav-link {
            color: #475569;
            border-radius: 10px;
            padding: 9px 10px;
            margin-bottom: 2px;
            font-weight: 500;
            gap: 10px;
            border: 0;
            background: transparent;
            width: 100%;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 13px;
        }
        body.customer-portal .sidebar .nav-link i {
            color: #94a3b8;
            width: 20px;
            font-size: 15px;
            transition: all 0.2s ease;
        }
        body.customer-portal .sidebar .nav-link:hover {
            color: #0f172a;
            background: #f8fafc;
        }
        body.customer-portal .sidebar .nav-link.active {
            color: var(--brand-strong);
            background: color-mix(in srgb, var(--brand) 12%, white);
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        body.customer-portal .sidebar .nav-link.active i { color: var(--brand); }
        body.customer-portal .customer-menu-chevron {
            margin-left: auto;
            color: #94a3b8;
            transition: transform .16s ease;
            font-size: 11px;
        }
        body.customer-portal .customer-menu-toggle[aria-expanded="true"] .customer-menu-chevron {
            transform: rotate(180deg);
            color: var(--brand);
        }
        body.customer-portal .customer-subnav {
            margin: 2px 0 6px 18px;
            padding-left: 12px;
            border-left: 1px solid #e2e8f0;
            display: block;
            gap: 2px;
        }
        body.customer-portal .customer-subnav.hidden {
            display: none;
        }
        body.customer-portal .customer-subnav .nav-link {
            padding: 7px 10px;
            font-size: 12px;
            border-radius: 8px;
            margin: 0;
        }
        body.customer-portal .nav-dot {
            font-size: 6px !important;
            width: 14px !important;
        }
        body.customer-portal .sidebar-footer {
            flex-shrink: 0;
            border-top: 1px solid #f1f5f9;
            background: #fff;
            padding-bottom: 12px;
        }
        body.customer-portal .customer-nav-control {
            margin: 0 12px 6px;
            padding: 10px 12px;
            color: #475569;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        body.customer-portal .customer-nav-control button {
            border: 0;
            background: transparent;
            color: var(--brand);
            padding: 0;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
        }
        body.customer-portal .customer-mailbox-card {
            margin: 6px 12px 10px;
            padding: 12px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand), var(--brand-strong));
            color: #fff;
            box-shadow: 0 8px 20px var(--brand-glow);
            transition: all 0.2s ease;
        }
        body.customer-portal .customer-mailbox-card .label {
            color: rgba(255,255,255,.8);
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 3px;
        }
        body.customer-portal .customer-mailbox-card .value {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: 18px;
            line-height: 1.1;
            font-weight: 800;
        }
        body.customer-portal .customer-mailbox-card button {
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 6px;
            background: rgba(255,255,255,.16);
            color: #fff;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        body.customer-portal .customer-mailbox-card button:hover {
            background: rgba(255,255,255,.3);
        }
        body.customer-portal .customer-logout-btn {
            margin: 0 12px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #fef2f2;
            color: #dc2626;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            width: calc(100% - 24px);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        body.customer-portal .customer-logout-btn:hover {
            background: #fee2e2;
        }
        body.customer-portal .customer-logout-btn i {
            font-size: 16px;
        }
        body.customer-portal .content-wrap {
            flex: 1 1 auto;
            margin-left: 0;
            height: 100vh;
            min-height: 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        body.customer-portal .topbar {
            position: sticky;
            top: 0;
            z-index: 4;
            min-height: 60px;
            margin: 0;
            padding: 10px 24px;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 0;
            background: #fff;
            box-shadow: none;
            backdrop-filter: none;
        }
        body.customer-portal .page-title,
        body.customer-portal .breadcrumb-line {
            display: none;
        }
        body.customer-portal .customer-top-search {
            position: relative;
            width: min(380px, 40vw);
        }
        body.customer-portal .customer-top-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13px;
        }
        body.customer-portal .customer-top-search input {
            width: 100%;
            min-height: 38px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            padding: 7px 12px 7px 36px;
            color: #334155;
            font-size: 13px;
        }
        body.customer-portal .customer-top-search input:focus {
            outline: 0;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-glow);
            background: #fff;
        }
        body.customer-portal .topbar-action {
            border-radius: 999px;
            border: 0;
            background: #fff;
            color: #475569;
            width: 38px;
            height: 38px;
        }
        body.customer-portal .topbar-action:hover { background: #f1f5f9; }
        body.customer-portal .user-menu {
            min-width: 0;
            border: 0;
            background: #fff;
            padding: 0;
            cursor: pointer;
        }
        body.customer-portal .avatar-circle {
            border-radius: 999px;
            background: color-mix(in srgb, var(--brand) 12%, white);
            color: var(--brand-strong);
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .1);
            width: 38px;
            height: 38px;
        }
        body.customer-portal .page-pad {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 20px;
        }
        body.customer-portal .page-pad > * {
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Responsive adjustments for customer portal */
        @media (max-width: 1200px) {
            body.customer-portal .sidebar {
                width: 240px;
                flex: 0 0 240px;
            }
            body.customer-portal .sidebar.collapsed {
                flex: 0 0 72px;
                width: 72px;
            }
        }
        
        @media (max-width: 992px) {
            body.customer-portal .sidebar {
                position: fixed;
                left: -260px;
                transition: left 0.3s ease;
                z-index: 1000;
                flex: 0 0 260px;
            }
            body.customer-portal .sidebar.open {
                left: 0;
            }
            body.customer-portal .sidebar.collapsed {
                left: -72px;
            }
            body.customer-portal .sidebar.collapsed.open {
                left: 0;
            }
            body.customer-portal .content-wrap {
                width: 100%;
            }
            body.customer-portal .topbar {
                margin: 0;
                padding: 10px 16px;
            }
            body.customer-portal .customer-top-search { display: none; }
            body.customer-portal .page-pad {
                padding: 16px;
            }
        }
        
        @media (max-width: 768px) {
            body.customer-portal .page-pad {
                padding: 12px;
            }
            body.customer-portal .topbar {
                padding: 8px 12px;
            }
        }
        
        /* Sidebar toggle button for mobile */
        .sidebar-mobile-toggle {
            display: none;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 2450;
            background: var(--brand);
            color: white;
            border: none;
            border-radius: 10px;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: left .2s ease, background .16s ease, color .16s ease;
        }
        
        @media (max-width: 992px) {
            .sidebar-mobile-toggle {
                display: flex;
            }            .topbar {
                min-height: auto;
                align-items: flex-start;
                gap: 10px;
            }
            .topbar > .d-flex {
                min-width: 0;
                flex-wrap: wrap;
            }
            .page-title {
                white-space: normal;
                overflow-wrap: anywhere;
            }
            .btn {
                white-space: normal;
            }
            .page-pad > .d-flex.justify-content-between .d-flex,
            .page-pad > .d-flex.align-items-center .d-flex,
            .page-pad > .d-flex.align-items-start .d-flex {
                flex-wrap: wrap;
            }
            .page-pad > .d-flex.justify-content-between .btn,
            .page-pad > .d-flex.align-items-center .btn,
            .page-pad > .d-flex.align-items-start .btn {
                max-width: 100%;
            }
            body.sidebar-drawer-open .sidebar-mobile-toggle {
                left: 274px;
                background: #fff;
                color: #0f172a;
            }
            body.customer-portal .topbar {
                padding-left: 60px;
            }
            body.courier-portal {
                overflow: auto;
            }
            body.courier-portal .app-shell {
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }
            body.courier-portal .sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 1000;
                transform: translateX(-100%);
                transition: transform .2s ease;
            }
            body.courier-portal .sidebar.open {
                transform: translateX(0);
            }
            body.courier-portal .content-wrap {
                width: 100%;
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }
            body.courier-portal .topbar {
                padding-left: 60px;
            }
            body.courier-portal .page-pad {
                overflow: visible;
                padding: 20px;
            }
        }


        /* Mobile app-style cleanup for customer and courier portals */
        @media (max-width: 768px) {
            body.customer-portal,
            body.courier-portal {
                background: #f8fafc;
                overflow-x: hidden;
            }

            body.customer-portal .app-shell,
            body.courier-portal .app-shell {
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }

            body.customer-portal .content-wrap,
            body.courier-portal .content-wrap {
                margin-left: 0 !important;
                width: 100% !important;
                min-width: 0;
                overflow: visible;
            }

            body.customer-portal .topbar,
            body.courier-portal .topbar {
                position: sticky;
                top: 0;
                z-index: 900;
                margin: 0;
                padding: 10px 12px 10px 58px;
                gap: 8px;
                align-items: center !important;
                border-radius: 0;
                border-left: 0;
                border-right: 0;
                box-shadow: 0 1px 0 rgba(15, 23, 42, 0.08);
            }

            body.customer-portal .topbar > .d-flex:first-child,
            body.courier-portal .topbar > .d-flex:first-child {
                flex: 1 1 auto;
                min-width: 0;
            }

            body.customer-portal .page-title,
            body.courier-portal .page-title {
                font-size: 1.15rem;
                line-height: 1.2;
                max-width: 100%;
                white-space: normal;
            }

            body.customer-portal .breadcrumb-line,
            body.courier-portal .breadcrumb-line {
                display: none !important;
            }

            body.customer-portal .customer-top-search,
            body.courier-portal .courier-top-search {
                display: none !important;
            }

            body.customer-portal .user-menu,
            body.courier-portal .user-menu {
                min-width: 0;
                padding: 6px;
                gap: 6px;
            }

            body.customer-portal .user-menu .flex-grow-1,
            body.courier-portal .user-menu .flex-grow-1 {
                display: none;
            }

            body.customer-portal .topbar-action,
            body.courier-portal .topbar-action {
                width: 36px;
                height: 36px;
                flex: 0 0 36px;
            }

            body.customer-portal .avatar-circle,
            body.courier-portal .avatar-circle {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            body.customer-portal .page-pad,
            body.courier-portal .page-pad {
                padding: 10px !important;
            }

            body.customer-portal .panel,
            body.customer-portal .dashboard-card,
            body.courier-portal .panel,
            body.courier-portal .dashboard-card {
                border-radius: 8px;
                box-shadow: none;
            }

            body.customer-portal .page-pad > .d-flex.justify-content-between,
            body.customer-portal .page-pad > .d-flex.align-items-center,
            body.customer-portal .page-pad > .d-flex.align-items-start,
            body.courier-portal .page-pad > .d-flex.justify-content-between,
            body.courier-portal .page-pad > .d-flex.align-items-center,
            body.courier-portal .page-pad > .d-flex.align-items-start {
                align-items: stretch !important;
                gap: 10px;
            }

            body.customer-portal .page-pad > .d-flex.justify-content-between > .d-flex,
            body.customer-portal .page-pad > .d-flex.align-items-center > .d-flex,
            body.customer-portal .page-pad > .d-flex.align-items-start > .d-flex,
            body.courier-portal .page-pad > .d-flex.justify-content-between > .d-flex,
            body.courier-portal .page-pad > .d-flex.align-items-center > .d-flex,
            body.courier-portal .page-pad > .d-flex.align-items-start > .d-flex {
                width: 100%;
            }

            body.customer-portal .table-responsive,
            body.courier-portal .table-responsive {
                margin-left: -10px;
                margin-right: -10px;
                width: calc(100% + 20px);
                max-width: calc(100% + 20px);
                border-radius: 0;
                -webkit-overflow-scrolling: touch;
            }

            body.customer-portal .table > :not(caption) > * > *,
            body.courier-portal .table > :not(caption) > * > * {
                padding: 0.68rem 0.62rem;
                vertical-align: middle;
            }

            body.customer-portal .btn,
            body.courier-portal .btn {
                min-height: 40px;
            }

            body.customer-portal nav[role="navigation"],
            body.courier-portal nav[role="navigation"] {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            body.customer-portal nav[role="navigation"] > div,
            body.courier-portal nav[role="navigation"] > div {
                gap: 8px;
            }

            body.customer-portal .pagination,
            body.courier-portal .pagination {
                flex-wrap: nowrap;
                justify-content: center;
                gap: 4px;
                margin-bottom: 0;
            }

            body.customer-portal .pagination .page-link,
            body.courier-portal .pagination .page-link {
                min-width: 34px;
                padding: 0.42rem 0.55rem;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            body.customer-portal .page-pad > .d-flex.justify-content-between,
            body.customer-portal .page-pad > .d-flex.align-items-center,
            body.customer-portal .page-pad > .d-flex.align-items-start,
            body.courier-portal .page-pad > .d-flex.justify-content-between,
            body.courier-portal .page-pad > .d-flex.align-items-center,
            body.courier-portal .page-pad > .d-flex.align-items-start {
                display: grid !important;
                grid-template-columns: 1fr;
            }

            body.customer-portal .page-pad > .d-flex.justify-content-between .btn,
            body.customer-portal .page-pad > .d-flex.align-items-center .btn,
            body.customer-portal .page-pad > .d-flex.align-items-start .btn,
            body.courier-portal .page-pad > .d-flex.justify-content-between .btn,
            body.courier-portal .page-pad > .d-flex.align-items-center .btn,
            body.courier-portal .page-pad > .d-flex.align-items-start .btn {
                width: 100%;
                justify-content: center;
            }

            body.customer-portal .table,
            body.courier-portal .table {
                font-size: 0.84rem;
            }

            body.customer-portal nav[role="navigation"] .hidden,
            body.courier-portal nav[role="navigation"] .hidden {
                display: none !important;
            }

            body.customer-portal .pagination .page-item:not(.active):not(:first-child):not(:last-child),
            body.courier-portal .pagination .page-item:not(.active):not(:first-child):not(:last-child) {
                display: none;
            }
        }

        @media (max-width: 992px) {
            body.customer-portal .app-shell {
                height: auto;
                min-height: 100dvh;
                overflow: visible;
            }
            body.customer-portal .sidebar {
                height: 100dvh;
                min-height: 100dvh;
                max-height: 100dvh;
                overflow-y: auto;
                overscroll-behavior: contain;
                -webkit-overflow-scrolling: touch;
                padding-bottom: max(18px, env(safe-area-inset-bottom));
            }
            body.customer-portal .sidebar-nav {
                flex: 1 1 auto;
                min-height: 0;
            }
            body.customer-portal .sidebar-footer {
                position: sticky;
                bottom: 0;
                z-index: 2;
                margin-top: auto;
                padding-bottom: max(12px, env(safe-area-inset-bottom));
                box-shadow: 0 -10px 24px rgba(15, 23, 42, .08);
            }
            body.customer-portal .content-wrap {
                height: auto;
                min-height: 100dvh;
                overflow: visible;
            }
        }

        @media (max-width: 768px) {
            body.customer-portal .sidebar-footer {
                padding-bottom: max(18px, env(safe-area-inset-bottom));
            }
            body.customer-portal .customer-mailbox-card {
                margin-bottom: 8px;
                padding: 10px 12px;
            }
            body.customer-portal .customer-logout-btn {
                min-height: 44px;
            }
        }
        @media print {
            .sidebar-mobile-toggle {
                display: none !important;
            }
        }

        body.superadmin-portal {
            background: #f8fafc;
            color: #1e293b;
            overflow: hidden;
        }
        body.superadmin-portal .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
            background: #f8fafc;
        }
        body.superadmin-portal .sidebar {
            width: 256px;
            min-height: 100vh;
            padding: 0;
            background: #0b1727;
            border-right: 1px solid #1e293b;
            box-shadow: none;
            overflow-y: auto;
        }
        body.superadmin-portal .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        body.superadmin-portal .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        body.superadmin-portal .sidebar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, .3);
            border-radius: 20px;
        }
        body.superadmin-portal .brand-wrap {
            height: 64px;
            position: sticky;
            top: 0;
            z-index: 20;
            margin: 0;
            padding: 0 24px;
            gap: 10px;
            border-bottom: 1px solid #1e293b;
            background: #07101b;
        }
        body.superadmin-portal .brand-mark {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            background: #2563eb;
            box-shadow: none;
        }
        body.superadmin-portal .brand-title {
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.1;
        }
        body.superadmin-portal .brand-subtitle {
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            margin-top: 2px;
            text-transform: uppercase;
        }
        body.superadmin-portal .superadmin-menu {
            padding: 16px 0 24px;
        }
        body.superadmin-portal .superadmin-menu-section {
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            margin: 22px 0 8px;
            padding: 0 24px;
            text-transform: uppercase;
        }
        body.superadmin-portal .superadmin-menu-section:first-child {
            margin-top: 0;
        }
        body.superadmin-portal .superadmin-menu-group {
            padding: 0 12px;
        }
        body.superadmin-portal .sidebar .nav-link,
        body.superadmin-portal .superadmin-menu-toggle {
            width: 100%;
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 4px;
            padding: 9px 12px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #cbd5e1;
            font-size: 14px;
            font-weight: 650;
            line-height: 1.2;
            text-align: left;
            transition: background .16s ease, color .16s ease;
        }
        body.superadmin-portal .sidebar .nav-link > span,
        body.superadmin-portal .superadmin-menu-toggle > span:first-child {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        body.superadmin-portal .sidebar .nav-link i,
        body.superadmin-portal .superadmin-menu-toggle i {
            width: 16px;
            color: var(--sidebar-icon-text);
            text-align: center;
        }
        body.superadmin-portal .sidebar .nav-link:hover,
        body.superadmin-portal .sidebar .nav-link.active,
        body.superadmin-portal .superadmin-menu-toggle:hover,
        body.superadmin-portal .superadmin-menu-toggle.active {
            background: #2563eb;
            color: #fff;
        }
        body.superadmin-portal .sidebar .nav-link:hover i,
        body.superadmin-portal .sidebar .nav-link.active i,
        body.superadmin-portal .superadmin-menu-toggle:hover i,
        body.superadmin-portal .superadmin-menu-toggle.active i {
            color: #fff;
        }
        body.superadmin-portal .superadmin-subnav {
            margin: 4px 0 10px 31px;
            padding-left: 8px;
            border-left: 1px solid #334155;
        }
        body.superadmin-portal .superadmin-subnav .nav-link {
            min-height: 30px;
            padding: 7px 8px;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
        }
        body.superadmin-portal .superadmin-subnav .nav-link:hover,
        body.superadmin-portal .superadmin-subnav .nav-link.active {
            background: rgba(30, 41, 59, .8);
            color: #fff;
        }
        body.superadmin-portal .superadmin-footer {
            margin: 18px 12px 0;
            padding-top: 14px;
            border-top: 1px solid #334155;
        }
        body.superadmin-portal .content-wrap {
            margin-left: 256px;
            height: 100vh;
            min-height: 0;
            min-width: 0;
            display: flex;
            flex: 1;
            flex-direction: column;
            overflow: hidden;
            background: #f8fafc;
        }
        body.superadmin-portal .topbar {
            position: relative;
            z-index: 1100;
            min-height: 64px;
            margin: 0;
            padding: 0 32px;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 0;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
            overflow: visible;
        }
        body.superadmin-portal .topbar .dropdown,
        body.superadmin-portal .topbar .dropdown-menu {
            z-index: 1200;
        }
        body.superadmin-portal .page-title {
            color: #1e293b;
            font-size: 20px;
            font-weight: 800;
        }
        body.superadmin-portal .breadcrumb-line {
            color: #64748b;
            font-size: 12px;
            margin-top: 2px;
        }
        body.superadmin-portal .page-pad {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 32px;
            background: #f8fafc;
        }
        body.superadmin-portal .panel,
        body.superadmin-portal .dashboard-card,
        body.superadmin-portal .metric,
        body.superadmin-portal .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        body.superadmin-portal .table {
            color: #334155;
        }
        body.superadmin-portal .table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        body.superadmin-portal .form-control,
        body.superadmin-portal .form-select {
            border-color: #cbd5e1;
            border-radius: 8px;
        }
        body.superadmin-portal .btn {
            border-radius: 8px;
            font-weight: 700;
        }
        body.superadmin-portal .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
        }
        body.superadmin-portal .topbar-action {
            border: 0;
            background: transparent;
            color: #94a3b8;
        }
        body.superadmin-portal .topbar-action:hover {
            background: #f8fafc;
            color: #2563eb;
        }
        body.superadmin-portal .notification-badge {
            top: 8px;
            right: 8px;
            width: 10px;
            min-width: 10px;
            height: 10px;
            padding: 0;
            border: 2px solid #fff;
            font-size: 0;
        }
        body.superadmin-portal .user-menu {
            min-width: 0;
            border: 0;
            border-left: 1px solid #e2e8f0;
            border-radius: 0;
            background: transparent;
            padding: 0 0 0 16px;
        }
        body.superadmin-portal .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #0f172a;
        }
        @media (max-width: 992px) {
            body.superadmin-portal {
                overflow: auto;
            }
            body.superadmin-portal .app-shell {
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }
            body.superadmin-portal .sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 2400;
                width: min(256px, calc(100vw - 64px));
                max-width: 86vw;
                height: 100dvh;
                min-height: 100dvh;
                transform: translateX(-100%);
                transition: transform .2s ease;
            }
            body.superadmin-portal .sidebar.show,
            body.superadmin-portal .sidebar.open {
                transform: translateX(0);
                box-shadow: 18px 0 42px rgba(2, 6, 23, .32);
            }
            body.sidebar-drawer-open.superadmin-portal .topbar {
                z-index: 1;
            }
            body.sidebar-drawer-open.superadmin-portal .page-pad {
                position: relative;
                z-index: 0;
            }
            body.superadmin-portal .content-wrap {
                margin-left: 0;
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }
            body.superadmin-portal .topbar {
                padding-left: 60px;
            }
            body.superadmin-portal .page-pad {
                overflow: visible;
                padding: 20px;
            }
        }
    </style>
</head>
@php
    $themeTenantId = auth()->check() && auth()->user()->tenant_id
        ? auth()->user()->tenant_id
        : ((app()->bound('currentTenant') && app('currentTenant')) ? app('currentTenant')->id : null);
    $themePrimary = $themeTenantId
        ? \App\Models\TenantSetting::getValue($themeTenantId, 'brand_primary_color', $platformSettings['brand_primary_color'] ?? '#135fd2')
        : null;
    $themeSecondary = $themeTenantId
        ? \App\Models\TenantSetting::getValue($themeTenantId, 'brand_secondary_color', $platformSettings['brand_secondary_color'] ?? '#073b70')
        : null;
    $soundPromptsEnabled = $themeTenantId
        ? (string) \App\Models\TenantSetting::getValue($themeTenantId, 'sound_prompts_enabled', '1') !== '0'
        : true;
    $hexLuminance = function (?string $color): float {
        $hex = ltrim(trim((string) $color), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return 0;
        }
        $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $linear = array_map(function ($channel) {
            $value = $channel / 255;
            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    };
    $sidebarUsesDarkText = $hexLuminance($themeSecondary) > 0.179;
    $sidebarText = $sidebarUsesDarkText ? '#0f172a' : '#ffffff';
    $sidebarMutedText = $sidebarUsesDarkText ? '#334155' : '#f8fafc';
    $sidebarIconText = $sidebarUsesDarkText ? '#475569' : '#e2e8f0';
    $activeUsesDarkText = $hexLuminance($themePrimary) > 0.179;
    $sidebarActiveText = $activeUsesDarkText ? '#0f172a' : '#ffffff';
@endphp
<body class="{{ auth()->check() ? (auth()->user()->user_type === 'customer' ? 'customer-portal' : (auth()->user()->user_type === 'super_admin' ? 'superadmin-portal' : (in_array(auth()->user()->user_type, ['tenant_admin', 'staff']) ? 'courier-portal' : ''))) : '' }}"
    @if($themeTenantId)
        style="--brand: {{ $themePrimary }}; --nav-soft: {{ $themePrimary }}; --brand-strong: {{ $themeSecondary }}; --nav: {{ $themeSecondary }}; --sidebar-text: {{ $sidebarText }}; --sidebar-muted-text: {{ $sidebarMutedText }}; --sidebar-icon-text: {{ $sidebarIconText }}; --sidebar-active-text: {{ $sidebarActiveText }};"
    @endif>
<?php if (auth()->check()) { ?>
    @php
        $user = auth()->user();
        $roleLabel = str_replace('_', ' ', $user->user_type);
        $displayName = $user->full_name ?: $user->email;
        $customerMailbox = $user->mailbox ?? optional($user->customer)->mailbox ?? null;
        $pageTitle = trim($__env->yieldContent('page_title')) ?: trim($__env->yieldContent('title')) ?: 'Dashboard';
        $pageTitle = html_entity_decode($pageTitle, ENT_QUOTES, 'UTF-8');
        $breadcrumbTitle = trim($__env->yieldContent('breadcrumb')) ?: $pageTitle;
        $breadcrumbTitle = html_entity_decode($breadcrumbTitle, ENT_QUOTES, 'UTF-8');
        $logoUrl = function (?string $path) {
            if (! $path) {
                return null;
            }

            if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])) {
                return $path;
            }

            $path = ltrim($path, '/');

            return \Illuminate\Support\Str::startsWith($path, 'storage/')
                ? asset($path)
                : asset($path);
        };
        $tenantLogo = $user->tenant && $user->tenant->logo ? $logoUrl($user->tenant->logo) : null;
        $tenantScopedUser = in_array($user->user_type, ['tenant_admin', 'staff', 'customer']);
        $sidebarLogo = $tenantScopedUser ? $tenantLogo : ($platformLogo ?? null);
        $sidebarName = $tenantScopedUser && $user->tenant ? $user->tenant->company_name : ($platformName ?? config('app.name', 'Courier SaaS'));
        $sidebarSubtitle = $tenantScopedUser
            ? ($platformSettings['platform_subtitle'] ?? 'Courier Portal')
            : ($platformSettings['platform_tagline'] ?? 'Platform Control');
        $notificationQuery = \App\Models\SystemNotification::query()
            ->activeNow()
            ->visibleToUser($user)
            ->whereDoesntHave('dismissals', fn ($query) => $query->where('user_id', auth()->id()))
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end");
        $availableNotifications = (clone $notificationQuery)->latest()->limit(8)->get();
        $loginAlerts = $availableNotifications->take(3);
        $notificationCount = $availableNotifications->count();
        $directNotificationQuery = \App\Models\ClientNotification::where('user_id', $user->id);
        $directNotificationCount = (clone $directNotificationQuery)->where('is_read', false)->count();
        $directNotifications = (clone $directNotificationQuery)->latest('created_at')->limit(6)->get();
        $deliveryRequestNotificationCount = (clone $directNotificationQuery)
            ->where('type', 'delivery_request')
            ->where('is_read', false)
            ->count();
        $paymentUploadNotificationCount = (clone $directNotificationQuery)
            ->where('type', 'payment_upload')
            ->where('is_read', false)
            ->count();
        $walletTopUpNotificationCount = (clone $directNotificationQuery)
            ->where('type', 'wallet_top_up')
            ->where('is_read', false)
            ->count();
        $unreadChatNotificationCount = (clone $directNotificationQuery)
            ->where('type', 'support_message')
            ->where('is_read', false)
            ->count();
        $purchaseRequestNotificationCount = (clone $directNotificationQuery)
            ->where('type', 'purchase_request')
            ->where('is_read', false)
            ->count();
        $billIssuedNotificationCount = (clone $directNotificationQuery)
            ->where('type', 'bill_issued')
            ->where('is_read', false)
            ->count();
        $agingPackageCount = 0;
        if (in_array($user->user_type, ['tenant_admin', 'staff']) && $user->hasPermission('manage_package_exceptions')) {
            $agingPackageCount = \App\Models\Package::where('tenant_id', $user->tenant_id)
                ->when($user->user_type === 'staff' && $user->outlet_id, fn ($query) => $query->where('outlet_id', $user->outlet_id))
                ->currentlyAging()
                ->count();
        }
        $notificationCount += $directNotificationCount;
    @endphp
    
    <!-- Mobile Sidebar Toggle Button -->
    <button class="sidebar-mobile-toggle" id="mobileSidebarToggle" type="button" aria-label="Toggle sidebar" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="app-shell">
        <aside class="sidebar" id="mainSidebar">
            <div class="brand-wrap">
                <div class="brand-mark">
                    <?php if ($sidebarLogo) { ?>
                        <img src="{{ $sidebarLogo }}" alt="{{ $sidebarName }} logo" style="width: 38px; height: 38px; object-fit: contain; border-radius: 10px; background: #fff; padding: 4px;">
                    <?php } elseif ($user->user_type === 'customer') { ?>
                        <span style="font-size: 18px; font-weight: bold;">OL</span>
                    <?php } else { ?>
                        <i class="fas fa-route"></i>
                    <?php } ?>
                </div>
                <div class="brand-text">
                    <div class="brand-title">{{ $sidebarName }}</div>
                    <div class="brand-subtitle">{{ $sidebarSubtitle }}</div>
                </div>
                <?php if ($user->user_type === 'customer') { ?>
                    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                <?php } ?>
            </div>

            <?php if ($user->user_type === 'super_admin') { ?>
                @php
                    $canSuperDashboard = $user->hasPermission('superadmin_dashboard');
                    $canSuperCourierWorkspaces = $user->hasAnyPermission([
                        'superadmin_courier_workspaces',
                        'superadmin_courier_workspaces_create',
                        'superadmin_courier_workspaces_edit',
                        'superadmin_courier_workspaces_delete',
                        'superadmin_courier_workspaces_open_portal',
                        'superadmin_courier_workspaces_credit',
                    ]);
                    $canSuperCourierCustomers = $user->hasPermission('superadmin_courier_customers');
                    $canSuperStaffEmployees = $user->hasPermission('superadmin_staff_employees');
                    $canSuperStaffRoles = $user->hasPermission('superadmin_staff_roles');
                    $canSuperStaffBranches = $user->hasPermission('superadmin_staff_branches');
                    $canSuperLogisticsOverview = $user->hasAnyPermission(['superadmin_logistics', 'superadmin_logistics_overview']);
                    $canSuperLogisticsManifests = $user->hasAnyPermission(['superadmin_logistics', 'superadmin_logistics_manifests']);
                    $canSuperLogisticsBulkCheckIn = $user->hasAnyPermission(['superadmin_logistics', 'superadmin_logistics_bulk_check_in']);
                    $canSuperLogisticsBulkUpload = $user->hasAnyPermission(['superadmin_logistics', 'superadmin_logistics_bulk_upload']);
                    $canSuperLogisticsRates = $user->hasAnyPermission(['superadmin_logistics', 'superadmin_logistics_rates']);
                    $canSuperLogisticsInvoices = $user->hasAnyPermission(['superadmin_logistics', 'superadmin_logistics_invoices']);
                    $canSuperLogisticsPackages = $user->hasAnyPermission(['superadmin_logistics', 'superadmin_logistics_packages']);
                    $canSuperLogistics = $canSuperLogisticsOverview || $canSuperLogisticsManifests || $canSuperLogisticsBulkCheckIn || $canSuperLogisticsBulkUpload || $canSuperLogisticsRates || $canSuperLogisticsInvoices || $canSuperLogisticsPackages;
                    $canSuperCrmChat = $user->hasPermission('superadmin_crm_chat');
                    $canSuperCrmMessaging = $user->hasPermission('superadmin_crm_messaging');
                    $canSuperPayments = $user->hasPermission('superadmin_payments');
                    $canSuperCourierBilling = $user->hasPermission('superadmin_courier_billing');
                    $canSuperPlans = $user->hasPermission('superadmin_subscription_plans');
                    $canSuperExpenseIncome = $user->hasPermission('superadmin_expense_income');
                    $canSuperProfitLosses = $user->hasPermission('superadmin_profit_losses');
                    $canSuperBillingRates = $user->hasPermission('superadmin_billing_rates');
                    $canSuperSettings = $user->hasAnyPermission([
                        'superadmin_settings',
                        'superadmin_settings_general',
                        'superadmin_settings_registration',
                        'superadmin_settings_email',
                        'superadmin_settings_sms',
                        'superadmin_settings_shipping',
                        'superadmin_settings_payment',
                        'superadmin_settings_master_tech',
                        'superadmin_settings_references',
                    ]);
                    $canSuperMaintenance = $user->hasPermission('superadmin_maintenance');
                    $canSuperReports = $user->hasPermission('superadmin_reports');
                    $canSuperAudit = $user->hasPermission('superadmin_audit');
                    $showSuperCouriers = $canSuperCourierWorkspaces || $canSuperCourierCustomers;
                    $showSuperStaff = $canSuperStaffEmployees || $canSuperStaffRoles || $canSuperStaffBranches;
                    $showSuperCrm = $canSuperCrmChat || $canSuperCrmMessaging;
                    $showSuperFinance = $canSuperPayments || $canSuperCourierBilling || $canSuperPlans || $canSuperExpenseIncome || $canSuperProfitLosses || $canSuperBillingRates;
                    $showSuperSettings = $canSuperSettings || $canSuperMaintenance;
                    $couriersOpen = request()->routeIs('superadmin.tenants*');
                    $logisticsOpen = request()->routeIs('superadmin.logistics*');
                    $crmOpen = request()->routeIs('superadmin.support*') || request()->routeIs('superadmin.notifications*');
                    $staffOpen = request()->routeIs('superadmin.staff*');
                    $financeOpen = request()->routeIs('superadmin.payments*') || request()->routeIs('superadmin.plans*') || request()->routeIs('superadmin.finance*');
                    $settingsOpen = request()->routeIs('superadmin.settings*');
                    $courierWorkspacesActive = request()->routeIs('superadmin.tenants') || request()->routeIs('superadmin.tenants.create') || request()->routeIs('superadmin.tenants.show') || request()->routeIs('superadmin.tenants.edit');
                @endphp
                <nav class="superadmin-menu">
                    @if($canSuperDashboard)
                        <div class="superadmin-menu-section">Main</div>
                        <div class="superadmin-menu-group">
                            <a class="nav-link {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}" href="{{ route('superadmin.dashboard') }}">
                                <span><i class="fas fa-table-columns"></i>Dashboard</span>
                            </a>
                        </div>
                    @endif

                    @if($showSuperCouriers)
                        <div class="superadmin-menu-section">Couriers</div>
                        <div class="superadmin-menu-group">
                            <button class="superadmin-menu-toggle {{ $couriersOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#superadminCouriersMenu" aria-expanded="{{ $couriersOpen ? 'true' : 'false' }}">
                                <span><i class="fas fa-users"></i>Couriers</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="superadminCouriersMenu" class="collapse superadmin-subnav {{ $couriersOpen ? 'show' : '' }}">
                                @if($canSuperCourierWorkspaces)
                                    <a class="nav-link {{ $courierWorkspacesActive ? 'active' : '' }}" href="{{ route('superadmin.tenants') }}">Courier Workspaces</a>
                                @endif
                                @if($canSuperCourierCustomers)
                                    <a class="nav-link {{ request()->routeIs('superadmin.tenants.customers') ? 'active' : '' }}" href="{{ route('superadmin.tenants.customers') }}">Customers</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($showSuperStaff)
                        <div class="superadmin-menu-section">Staff</div>
                        <div class="superadmin-menu-group">
                            <button class="superadmin-menu-toggle {{ $staffOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#superadminStaffMenu" aria-expanded="{{ $staffOpen ? 'true' : 'false' }}">
                                <span><i class="fas fa-briefcase"></i>Staff</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="superadminStaffMenu" class="collapse superadmin-subnav {{ $staffOpen ? 'show' : '' }}">
                                @if($canSuperStaffEmployees)
                                    <a class="nav-link {{ request()->routeIs('superadmin.staff.employees') ? 'active' : '' }}" href="{{ route('superadmin.staff.employees') }}">Employees</a>
                                @endif
                                @if($canSuperStaffRoles || $canSuperStaffBranches)
                                    <a class="nav-link {{ request()->routeIs('superadmin.staff.roles-branches') ? 'active' : '' }}" href="{{ route('superadmin.staff.roles-branches') }}">Roles & Permissions</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($canSuperLogistics)
                        <div class="superadmin-menu-section">Logistics</div>
                        <div class="superadmin-menu-group">
                            <button class="superadmin-menu-toggle {{ $logisticsOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#superadminLogisticsMenu" aria-expanded="{{ $logisticsOpen ? 'true' : 'false' }}">
                                <span><i class="fas fa-truck-ramp-box"></i>Logistics</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="superadminLogisticsMenu" class="collapse superadmin-subnav {{ $logisticsOpen ? 'show' : '' }}">
                                @if($canSuperLogisticsOverview)
                                    <a class="nav-link {{ request()->routeIs('superadmin.logistics.index') || request()->routeIs('superadmin.logistics.overview') ? 'active' : '' }}" href="{{ route('superadmin.logistics.index') }}">Overview</a>
                                @endif
                                @if($canSuperLogisticsManifests)
                                    <a class="nav-link {{ request()->routeIs('superadmin.logistics.manifests') || request()->routeIs('superadmin.logistics.show') ? 'active' : '' }}" href="{{ route('superadmin.logistics.manifests') }}">Manifests</a>
                                @endif
                                @if($canSuperLogisticsBulkCheckIn)
                                    <a class="nav-link {{ request()->routeIs('superadmin.logistics.bulk-check-in*') || request()->routeIs('superadmin.logistics.shipments*') ? 'active' : '' }}" href="{{ route('superadmin.logistics.bulk-check-in') }}">Bulk Check-In</a>
                                @endif
                                @if($canSuperLogisticsBulkUpload)
                                    <a class="nav-link {{ request()->routeIs('superadmin.logistics.bulk-upload') || request()->routeIs('superadmin.logistics.packages.bulk-*') ? 'active' : '' }}" href="{{ route('superadmin.logistics.bulk-upload') }}">Bulk Upload</a>
                                @endif
                                @if($canSuperLogisticsRates)
                                    <a class="nav-link {{ request()->routeIs('superadmin.logistics.rates') || request()->routeIs('superadmin.logistics.pricing*') ? 'active' : '' }}" href="{{ route('superadmin.logistics.rates') }}">Rates & Pricing</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($showSuperCrm)
                        <div class="superadmin-menu-section">CRM</div>
                        <div class="superadmin-menu-group">
                            <button class="superadmin-menu-toggle {{ $crmOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#superadminCrmMenu" aria-expanded="{{ $crmOpen ? 'true' : 'false' }}">
                                <span><i class="fas fa-message"></i>CRM</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="superadminCrmMenu" class="collapse superadmin-subnav {{ $crmOpen ? 'show' : '' }}">
                                @if($canSuperCrmChat)
                                    <a class="nav-link {{ request()->routeIs('superadmin.support*') ? 'active' : '' }}" href="{{ route('superadmin.support.index') }}">Chat <span class="badge bg-danger ms-2" data-support-chat-badge @if(! $unreadChatNotificationCount) hidden @endif>{{ $unreadChatNotificationCount }}</span></a>
                                @endif
                                @if($canSuperCrmMessaging)
                                    <a class="nav-link {{ request()->routeIs('superadmin.notifications*') ? 'active' : '' }}" href="{{ route('superadmin.notifications.index') }}">Email / Messaging</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($showSuperFinance)
                        <div class="superadmin-menu-section">Finances</div>
                        <div class="superadmin-menu-group">
                            <button class="superadmin-menu-toggle {{ $financeOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#superadminFinanceMenu" aria-expanded="{{ $financeOpen ? 'true' : 'false' }}">
                                <span><i class="fas fa-dollar-sign"></i>Finances</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="superadminFinanceMenu" class="collapse superadmin-subnav {{ $financeOpen ? 'show' : '' }}">
                                @if($canSuperPayments)
                                    <a class="nav-link {{ request()->routeIs('superadmin.payments*') ? 'active' : '' }}" href="{{ route('superadmin.payments.index') }}">Invoices & Payments</a>
                                @endif
                                @if($canSuperCourierBilling)
                                    <a class="nav-link {{ request()->routeIs('superadmin.finance.bills*') || request()->routeIs('superadmin.finance.bill-payments*') ? 'active' : '' }}" href="{{ route('superadmin.finance.bills') }}">Courier Billing</a>
                                @endif
                                @if($canSuperPlans)
                                    <a class="nav-link {{ request()->routeIs('superadmin.plans*') ? 'active' : '' }}" href="{{ route('superadmin.plans.index') }}">Subscription Plans</a>
                                @endif
                                @if($canSuperExpenseIncome)
                                    <a class="nav-link {{ request()->routeIs('superadmin.finance.entries') ? 'active' : '' }}" href="{{ route('superadmin.finance.entries') }}">Expense & Income</a>
                                    <a class="nav-link {{ request()->routeIs('superadmin.finance.categories*') ? 'active' : '' }}" href="{{ route('superadmin.finance.categories') }}">Ledger Categories</a>
                                @endif
                                @if($canSuperProfitLosses)
                                    <a class="nav-link {{ request()->routeIs('superadmin.finance.profit-loss') ? 'active' : '' }}" href="{{ route('superadmin.finance.profit-loss') }}">Profit & Losses</a>
                                @endif
                                @if($canSuperBillingRates)
                                    <a class="nav-link {{ request()->routeIs('superadmin.finance.billing-rates*') ? 'active' : '' }}" href="{{ route('superadmin.finance.billing-rates') }}">Billing Rates</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($showSuperSettings)
                        <div class="superadmin-menu-section">Configuration</div>
                        <div class="superadmin-menu-group">
                            <button class="superadmin-menu-toggle {{ $settingsOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#superadminSettingsMenu" aria-expanded="{{ $settingsOpen ? 'true' : 'false' }}">
                                <span><i class="fas fa-gear"></i>Settings</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="superadminSettingsMenu" class="collapse superadmin-subnav {{ $settingsOpen ? 'show' : '' }}">
                                @if($canSuperSettings)
                                    <a class="nav-link {{ request()->routeIs('superadmin.settings*') && request('tab') !== 'maintenance' ? 'active' : '' }}" href="{{ route('superadmin.settings.index') }}">System Config</a>
                                @endif
                                @if($canSuperMaintenance)
                                    <a class="nav-link {{ request()->routeIs('superadmin.settings*') && request('tab') === 'maintenance' ? 'active' : '' }}" href="{{ route('superadmin.settings.index', ['tab' => 'maintenance']) }}">Backup & Restore</a>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($canSuperReports || $canSuperAudit)
                        <div class="superadmin-menu-section">System</div>
                        <div class="superadmin-menu-group">
                            @if($canSuperReports)
                                <a class="nav-link {{ request()->routeIs('superadmin.reports*') ? 'active' : '' }}" href="{{ route('superadmin.reports.index') }}">
                                    <span><i class="fas fa-chart-line"></i>Reports</span>
                                </a>
                            @endif
                            @if($canSuperAudit)
                                <a class="nav-link {{ request()->routeIs('superadmin.audit*') ? 'active' : '' }}" href="{{ route('superadmin.audit.index') }}">
                                    <span><i class="fas fa-wave-square"></i>Audit Trail</span>
                                </a>
                            @endif
                        </div>
                    @endif

                    <div class="superadmin-footer">
                        <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                            <span><i class="fas fa-user-cog"></i>Profile</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button type="submit" class="nav-link text-danger">
                                <span><i class="fas fa-arrow-right-from-bracket"></i>Sign Out</span>
                            </button>
                        </form>
                    </div>
                </nav>
            <?php } elseif (in_array($user->user_type, ['tenant_admin', 'staff'])) { ?>
                @php
                    $canDashboard = $user->hasPermission('view_dashboard');
                    $canCustomers = $user->hasPermission('manage_customers');
                    $canAuthorizedUsers = $user->hasPermission('manage_authorized_users');
                    $canCrmEmails = $user->hasPermission('manage_crm_emails');
                    $canPackages = $user->hasPermission('manage_packages');
                    $canPreAlerts = $user->hasPermission('manage_pre_alerts');
                    $canPurchaseRequests = $user->hasPermission('manage_purchase_requests');
                    $canPackageExceptions = $user->hasPermission('manage_package_exceptions');
                    $canWarehouse = $user->hasPermission('manage_warehouse');
                    $canTickets = $user->hasPermission('manage_tickets');
                    $canManifests = $user->hasPermission('manage_manifests');
                    $canDelivery = $user->hasPermission('manage_delivery');
                    $canInvoices = $user->hasPermission('manage_invoices');
                    $canPayments = $user->hasPermission('manage_payments');
                    $canTransactions = $user->hasPermission('manage_transactions');
                    $canBilling = $user->hasPermission('manage_billing');
                    $canReports = $user->hasPermission('view_reports');
                    $canStaff = $user->hasPermission('manage_staff');
                    $canBranches = $user->hasPermission('manage_branches');
                    $canSettings = $user->hasPermission('manage_settings');
                    $canRates = $user->hasPermission('manage_rates');
                    $canCustomsRates = $user->hasPermission('manage_customs_rates');
                    $canRoles = $user->hasPermission('manage_roles');
                    $canSystem = $user->hasPermission('manage_system');
                    $canActivity = $user->hasPermission('manage_activity_logs');
                    $showCrmMenu = $canCustomers || $canAuthorizedUsers || $canCrmEmails || $canTickets;
                    $showPackagesMenu = $canPackages || $canPreAlerts || $canPurchaseRequests || $canPackageExceptions;
                    $showLogisticsMenu = $canWarehouse || $canManifests || $canDelivery;
                    $showFinanceMenu = $canInvoices || $canPayments || $canTransactions || $canBilling || $canReports;
                    $showStaffMenu = $user->user_type === 'tenant_admin' && ($canStaff || $canRoles || $canBranches);
                    $showSettingsMenu = $canSettings || $canRates || $canCustomsRates || $canSystem || $canActivity;
                    $financeHref = $canInvoices
                        ? route('courier.invoices.index')
                        : ($canTransactions
                            ? route('courier.finance.transactions')
                            : ($canBilling
                                ? route('courier.finance.bills')
                                : ($canReports
                                ? route('courier.finance.reports')
                                : route('courier.finance.payments'))));
                    $crmHref = $canCustomers
                        ? route('courier.customers.index')
                        : ($canAuthorizedUsers
                            ? route('courier.authorized-users.index')
                            : ($canCrmEmails
                                ? route('courier.crm.emails.create')
                                : route('courier.support.index')));
                    $packagesHref = $canPackages
                        ? route('courier.packages.index')
                        : ($canPreAlerts
                            ? route('courier.pre-alerts.index')
                            : ($canPurchaseRequests
                                ? route('courier.purchase-requests.index')
                                : route('courier.packages.aging')));
                    $logisticsHref = $canManifests
                        ? route('courier.manifests.index')
                        : ($canWarehouse
                            ? route('courier.warehouse.unmanifest')
                            : route('courier.delivery.index'));
                    $staffHref = $canStaff
                        ? route('courier.staff.index')
                        : ($canRoles
                            ? route('courier.roles.index')
                            : route('courier.outlets.index'));
                    $settingsHref = $canSettings
                        ? route('courier.settings.index')
                        : ($canRates
                            ? route('courier.weights.index')
                            : ($canCustomsRates
                                ? route('courier.customs-rates.index')
                                : ($canSystem
                                ? route('courier.statuses.index')
                                : route('courier.activity.index'))));
                    $crmOpen = request()->routeIs('courier.customers*') || request()->routeIs('courier.crm.emails*') || request()->routeIs('courier.client-messages*') || request()->routeIs('courier.support*') || request()->routeIs('courier.authorized-users*');
                    $packagesOpen = request()->routeIs('courier.packages*') || request()->routeIs('courier.pre-alerts*') || request()->routeIs('courier.purchase-requests*');
                    $logisticsOpen = request()->routeIs('courier.manifests*') || request()->routeIs('courier.warehouse*') || request()->routeIs('courier.delivery.*') || request()->routeIs('courier.delivery-areas*');
                    $financeOpen = request()->routeIs('courier.invoices*') || request()->routeIs('courier.finance*');
                    $staffOpen = request()->routeIs('courier.staff*') || request()->routeIs('courier.roles*') || request()->routeIs('courier.outlets*');
                    $settingsOpen = request()->routeIs('courier.settings*') || request()->routeIs('courier.weights*') || request()->routeIs('courier.customs-rates*') || request()->routeIs('courier.statuses*') || request()->routeIs('courier.activity*');
                @endphp
                <nav class="courier-sidebar-nav">
                    @if($canDashboard)
                        <a class="nav-link {{ request()->routeIs('courier.dashboard') ? 'active' : '' }}" href="{{ route('courier.dashboard') }}"><i class="fas fa-chart-line"></i>Dashboard</a>
                    @endif

                    @if($showCrmMenu)
                        <button class="courier-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courierCrmMenu" aria-expanded="{{ $crmOpen ? 'true' : 'false' }}">
                            <i class="fas fa-users"></i><a class="courier-menu-title" href="{{ $crmHref }}">CRM</a><i class="fas fa-chevron-right"></i>
                        </button>
                        <div id="courierCrmMenu" class="collapse courier-subnav {{ $crmOpen ? 'show' : '' }}">
                            @if($canCustomers)
                                <a class="nav-link {{ request()->routeIs('courier.customers*') ? 'active' : '' }} {{ $walletTopUpNotificationCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.customers.index') }}">Users Accounts <span class="badge bg-danger ms-auto" data-direct-notification-badge="wallet_top_up" @if(! $walletTopUpNotificationCount) hidden @endif>{{ $walletTopUpNotificationCount }}</span></a>
                            @endif
                            @if($canCrmEmails)
                                <a class="nav-link {{ request()->routeIs('courier.crm.emails*') ? 'active' : '' }}" href="{{ route('courier.crm.emails.create') }}">Marketing Emails</a>
                                <a class="nav-link {{ request()->routeIs('courier.client-messages*') ? 'active' : '' }}" href="{{ route('courier.client-messages.index') }}">Client Messages</a>
                            @endif
                            @if($canAuthorizedUsers)
                                <a class="nav-link {{ request()->routeIs('courier.authorized-users*') ? 'active' : '' }}" href="{{ route('courier.authorized-users.index') }}">Authorized Users</a>
                            @endif
                            @if($canTickets)
                                <a class="nav-link {{ request()->routeIs('courier.support*') ? 'active' : '' }} {{ $unreadChatNotificationCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.support.index') }}">Live Chat <span class="badge bg-danger ms-2" data-support-chat-badge data-direct-notification-badge="support_message" @if(! $unreadChatNotificationCount) hidden @endif>{{ $unreadChatNotificationCount }}</span></a>
                            @endif
                        </div>
                    @endif

                    @if($showPackagesMenu)
                        <button class="courier-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courierPackagesMenu" aria-expanded="{{ $packagesOpen ? 'true' : 'false' }}">
                            <i class="fas fa-box"></i><a class="courier-menu-title" href="{{ $packagesHref }}">Package Management</a><i class="fas fa-chevron-right"></i>
                        </button>
                        <div id="courierPackagesMenu" class="collapse courier-subnav {{ $packagesOpen ? 'show' : '' }}">
                            @if($canPackages)
                                <a class="nav-link {{ request()->routeIs('courier.packages.index') ? 'active' : '' }}" href="{{ route('courier.packages.index') }}">Packages</a>
                            @endif
                            @if($canPreAlerts)
                                <a class="nav-link {{ request()->routeIs('courier.pre-alerts*') ? 'active' : '' }}" href="{{ route('courier.pre-alerts.index') }}">Pre-Alerts</a>
                            @endif
                            @if($canPurchaseRequests)
                                <a class="nav-link {{ request()->routeIs('courier.purchase-requests*') ? 'active' : '' }} {{ $purchaseRequestNotificationCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.purchase-requests.index') }}">Purchase Requests <span class="badge bg-danger ms-auto" data-direct-notification-badge="purchase_request" @if(! $purchaseRequestNotificationCount) hidden @endif>{{ $purchaseRequestNotificationCount }}</span></a>
                            @endif
                            @if($canPackageExceptions)
                                <a class="nav-link {{ request()->routeIs('courier.packages.aging') ? 'active' : '' }} {{ $agingPackageCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.packages.aging') }}">Aging packages <span class="badge bg-danger ms-auto" data-direct-notification-badge="aging_package" @if(! $agingPackageCount) hidden @endif>{{ $agingPackageCount }}</span></a>
                            @endif
                        </div>
                    @endif

                    @if($showLogisticsMenu)
                        <button class="courier-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courierLogisticsMenu" aria-expanded="{{ $logisticsOpen ? 'true' : 'false' }}">
                            <i class="fas fa-map-location-dot"></i><a class="courier-menu-title" href="{{ $logisticsHref }}">Logistics</a><i class="fas fa-chevron-right"></i>
                        </button>
                        <div id="courierLogisticsMenu" class="collapse courier-subnav {{ $logisticsOpen ? 'show' : '' }}">
                            @if($canWarehouse)
                                <a class="nav-link {{ request()->routeIs('courier.warehouse*') ? 'active' : '' }}" href="{{ route('courier.warehouse.unmanifest') }}">Unmanifest</a>
                            @endif
                            @if($canManifests)
                                <a class="nav-link {{ request()->routeIs('courier.manifests*') ? 'active' : '' }}" href="{{ route('courier.manifests.index') }}">Manifest</a>
                            @endif
                            @if($canDelivery)
                                <a class="nav-link {{ request()->routeIs('courier.delivery.*') ? 'active' : '' }} {{ $deliveryRequestNotificationCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.delivery.index') }}">Delivery requests <span class="badge bg-danger ms-auto" data-direct-notification-badge="delivery_request" @if(! $deliveryRequestNotificationCount) hidden @endif>{{ $deliveryRequestNotificationCount }}</span></a>
                                @if($user->user_type === 'tenant_admin')
                                    <a class="nav-link {{ request()->routeIs('courier.delivery-areas*') ? 'active' : '' }}" href="{{ route('courier.delivery-areas.index') }}">Delivery locations</a>
                                @endif
                            @endif
                        </div>
                    @endif

                    @if($showFinanceMenu)
                        <button class="courier-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courierFinanceMenu" aria-expanded="{{ $financeOpen ? 'true' : 'false' }}">
                            <i class="fas fa-dollar-sign"></i><a class="courier-menu-title" href="{{ $financeHref }}">Finance</a><i class="fas fa-chevron-right"></i>
                        </button>
                        <div id="courierFinanceMenu" class="collapse courier-subnav {{ $financeOpen ? 'show' : '' }}">
                    @if($canInvoices)
                                <a class="nav-link {{ request()->routeIs('courier.invoices*') ? 'active' : '' }} {{ $paymentUploadNotificationCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.invoices.index') }}">Invoices <span class="badge bg-danger ms-auto" data-direct-notification-badge="payment_upload" @if(! $paymentUploadNotificationCount) hidden @endif>{{ $paymentUploadNotificationCount }}</span></a>
                            @endif
                            @if($canTransactions)
                                <a class="nav-link {{ request()->routeIs('courier.finance.transactions') ? 'active' : '' }}" href="{{ route('courier.finance.transactions') }}">Transactions</a>
                                <a class="nav-link {{ request()->routeIs('courier.finance.entries') ? 'active' : '' }}" href="{{ route('courier.finance.entries') }}">Income / Expense</a>
                                <a class="nav-link {{ request()->routeIs('courier.finance.categories*') ? 'active' : '' }}" href="{{ route('courier.finance.categories') }}">Ledger Categories</a>
                            @endif
                            @if($canBilling)
                                <a class="nav-link {{ request()->routeIs('courier.finance.bills*') || request()->routeIs('courier.finance.bill-payments*') ? 'active' : '' }} {{ $billIssuedNotificationCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.finance.bills') }}">Billing <span class="badge bg-danger ms-auto" data-direct-notification-badge="bill_issued" @if(! $billIssuedNotificationCount) hidden @endif>{{ $billIssuedNotificationCount }}</span></a>
                                <a class="nav-link {{ request()->routeIs('courier.finance.wallet*') ? 'active' : '' }}" href="{{ route('courier.finance.wallet') }}">Courier Wallet</a>
                            @endif
                            @if($canReports)
                                <a class="nav-link {{ request()->routeIs('courier.finance.profit-loss') ? 'active' : '' }}" href="{{ route('courier.finance.profit-loss') }}">Profit / Loss</a>
                                <a class="nav-link {{ request()->routeIs('courier.finance.balance-sheet') ? 'active' : '' }}" href="{{ route('courier.finance.balance-sheet') }}">Balance Sheet</a>
                                <a class="nav-link {{ request()->routeIs('courier.finance.reports') ? 'active' : '' }}" href="{{ route('courier.finance.reports') }}">Reports</a>
                            @endif
                            @if($canPayments)
                                <a class="nav-link {{ request()->routeIs('courier.finance.payments') ? 'active' : '' }} {{ $paymentUploadNotificationCount ? 'has-unread-notification' : '' }}" href="{{ route('courier.finance.payments') }}">Payments <span class="badge bg-danger ms-auto" data-direct-notification-badge="payment_upload" @if(! $paymentUploadNotificationCount) hidden @endif>{{ $paymentUploadNotificationCount }}</span></a>
                            @endif
                        </div>
                    @endif

                    @if($showStaffMenu)
                        <button class="courier-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courierStaffMenu" aria-expanded="{{ $staffOpen ? 'true' : 'false' }}">
                            <i class="fas fa-briefcase"></i><a class="courier-menu-title" href="{{ $staffHref }}">Staff</a><i class="fas fa-chevron-right"></i>
                        </button>
                        <div id="courierStaffMenu" class="collapse courier-subnav {{ $staffOpen ? 'show' : '' }}">
                            @if($canStaff)
                                <a class="nav-link {{ request()->routeIs('courier.staff*') ? 'active' : '' }}" href="{{ route('courier.staff.index') }}">Employees</a>
                            @endif
                            @if($canRoles)
                                <a class="nav-link {{ request()->routeIs('courier.roles*') ? 'active' : '' }}" href="{{ route('courier.roles.index') }}">Roles & Permissions</a>
                            @endif
                            @if($canBranches)
                                <a class="nav-link {{ request()->routeIs('courier.outlets*') ? 'active' : '' }}" href="{{ route('courier.outlets.index') }}">Branches</a>
                            @endif
                        </div>
                    @endif

                    <a class="nav-link {{ request()->routeIs('calculator') ? 'active' : '' }}" href="{{ route('calculator') }}"><i class="fas fa-calculator"></i>Tool (Calculator)</a>

                    @if($showSettingsMenu)
                        <button class="courier-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#courierSettingsMenu" aria-expanded="{{ $settingsOpen ? 'true' : 'false' }}">
                            <i class="fas fa-gear"></i><a class="courier-menu-title" href="{{ $settingsHref }}">System Management</a><i class="fas fa-chevron-right"></i>
                        </button>
                        <div id="courierSettingsMenu" class="collapse courier-subnav {{ $settingsOpen ? 'show' : '' }}">
                            @if($user->user_type === 'tenant_admin' && $canSettings)
                                <a class="nav-link {{ request()->routeIs('courier.settings*') ? 'active' : '' }}" href="{{ route('courier.settings.index') }}">General Settings</a>
                            @endif
                            @if($user->user_type === 'tenant_admin' && $canRates)
                                <a class="nav-link {{ request()->routeIs('courier.weights*') ? 'active' : '' }}" href="{{ route('courier.weights.index') }}">Rates Management</a>
                            @endif
                            @if($user->user_type === 'tenant_admin' && $canCustomsRates)
                                <a class="nav-link {{ request()->routeIs('courier.customs-rates*') ? 'active' : '' }}" href="{{ route('courier.customs-rates.index') }}">Customs Rates</a>
                            @endif
                            @if($user->user_type === 'tenant_admin' && $canSystem)
                                <a class="nav-link {{ request()->routeIs('courier.statuses*') ? 'active' : '' }}" href="{{ route('courier.statuses.index') }}">Package Status</a>
                            @endif
                            @if($canActivity)
                                <a class="nav-link {{ request()->routeIs('courier.activity*') ? 'active' : '' }}" href="{{ route('courier.activity.index') }}">Activity Log</a>
                            @endif
                        </div>
                    @endif

                    <div class="courier-sidebar-footer">
                        @if(session('impersonated_by'))
                            <form method="POST" action="{{ route('superadmin.return') }}" class="m-0">
                                @csrf
                                <button class="nav-link text-info" type="submit"><i class="fas fa-arrow-left"></i>Back to System Admin</button>
                            </form>
                        @endif
                        <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}"><i class="fas fa-user-cog"></i>Profile</a>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">
                            @csrf
                            <button class="nav-link text-danger" type="submit"><i class="fas fa-arrow-right-from-bracket"></i>Logout</button>
                        </form>
                    </div>
                </nav>
            <?php } elseif ($user->user_type === 'customer') { ?>
                <!-- ============================================ -->
                <!-- CUSTOMER SIDEBAR - WITH EXPAND/COLLAPSE       -->
                <!-- ============================================ -->
                @php
                    $customerCanPackages = $user->subscriptionAllowsModule('packages');
                    $customerCanDelivery = $user->subscriptionAllowsModule('delivery');
                    $customerCanInvoices = $user->subscriptionAllowsModule('invoices') || $user->subscriptionAllowsModule('payments');
                    $customerCanChat = $user->subscriptionAllowsModule('live_chat');
                @endphp
                <div class="sidebar-nav">
                    <ul class="nav flex-column">
                        <li class="sidebar-section">Overview</li>
                        <li>
                            <a class="nav-link {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}" href="{{ route('customer.dashboard') }}">
                                <i class="fas fa-chart-line"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                        
                       
                        @if($customerCanPackages)
                        <li class="nav-item">
                            <button type="button" class="nav-link w-100 text-start customer-menu-toggle" data-menu="packages">
                                <i class="fas fa-box-open"></i>
                                <span class="nav-text">My Packages</span>
                                <i class="fas fa-chevron-down customer-menu-chevron ms-auto"></i>
                            </button>
                            <div class="customer-subnav hidden" data-submenu="packages">
                                <a class="nav-link {{ request()->routeIs('customer.packages') && !request()->routeIs('customer.packages.create') ? 'active' : '' }}" href="{{ route('customer.packages') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">All Packages</span>
                                </a>
                                <a class="nav-link {{ request()->routeIs('customer.pre-alerts*') ? 'active' : '' }}" href="{{ route('customer.pre-alerts.index') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">Pre-Alerts</span>
                                </a>
                                <a class="nav-link {{ request()->routeIs('customer.purchase-requests*') ? 'active' : '' }}" href="{{ route('customer.purchase-requests.index') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">Purchase Requests</span>
                                </a>
                            </div>
                        </li>
                        @endif
                        
                        @if($customerCanDelivery)
                        <li class="nav-item">
                            <button type="button" class="nav-link w-100 text-start customer-menu-toggle" data-menu="delivery">
                                <i class="fas fa-truck"></i>
                                <span class="nav-text">Delivery Requests</span>
                                <i class="fas fa-chevron-down customer-menu-chevron ms-auto"></i>
                            </button>
                            <div class="customer-subnav hidden" data-submenu="delivery">
                                <a class="nav-link {{ request()->routeIs('customer.delivery.index') ? 'active' : '' }}" href="{{ route('customer.delivery.index') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">All Requests</span>
                                </a>
                                <a class="nav-link {{ request()->routeIs('customer.delivery.create') ? 'active' : '' }}" href="{{ route('customer.delivery.create') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">Request Delivery</span>
                                </a>
                            </div>
                        </li>
                        @endif
                        
                        
                        @if($customerCanInvoices)
                        <li>
                            <a class="nav-link {{ request()->routeIs('customer.invoices*') ? 'active' : '' }}" href="{{ route('customer.invoices') }}">
                                <i class="fas fa-receipt"></i>
                                <span class="nav-text">My Invoices</span>
                            </a>
                        </li>
                        @endif
                        <li>
                            <a class="nav-link {{ request()->routeIs('customer.affiliate-loyalty') ? 'active' : '' }}" href="{{ route('customer.affiliate-loyalty') }}">
                                <i class="fas fa-gift"></i>
                                <span class="nav-text">Affiliate & Loyalty</span>
                            </a>
                        </li>
                        
                    
                        @if($customerCanChat)
                        <li>
                                <a class="nav-link {{ request()->routeIs('customer.support*') ? 'active' : '' }}" href="{{ route('customer.support.index') }}">
                                    <i class="fas fa-comments"></i>
                                    <span class="nav-text">Live Chat</span>
                                    <span class="badge bg-danger ms-auto" data-support-chat-badge @if(! $unreadChatNotificationCount) hidden @endif>{{ $unreadChatNotificationCount }}</span>
                                </a>
                        </li>
                        @endif
                        <li>
                            <a class="nav-link {{ request()->routeIs('customer.messages*') ? 'active' : '' }}" href="{{ route('customer.messages.index') }}">
                                <i class="fas fa-bell"></i>
                                <span class="nav-text">Messages & Alerts</span>
                            </a>
                        </li>
                        
                      
                        <li>
                            <a class="nav-link {{ request()->routeIs('calculator') ? 'active' : '' }}" href="{{ route('calculator') }}">
                                <i class="fas fa-calculator"></i>
                                <span class="nav-text">Calculator</span>
                            </a>
                        </li>
                        
                       
                        <li class="nav-item">
                            <button type="button" class="nav-link w-100 text-start customer-menu-toggle" data-menu="account">
                                <i class="fas fa-user-cog"></i>
                                <span class="nav-text">Account Management</span>
                                <i class="fas fa-chevron-down customer-menu-chevron ms-auto"></i>
                            </button>
                            <div class="customer-subnav hidden" data-submenu="account">
                                <a class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">Edit Profile</span>
                                </a>
                                <a class="nav-link {{ request()->routeIs('customer.authorized-users.*') ? 'active' : '' }}" href="{{ route('customer.authorized-users.index') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">Authorized Users</span>
                                </a>
                                <a class="nav-link {{ request()->routeIs('customer.account.delete') ? 'active' : '' }}" href="{{ route('customer.account.delete') }}">
                                    <i class="fas fa-circle nav-dot"></i>
                                    <span class="nav-text">Delete Account</span>
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <!-- Fixed Bottom Section (Customer Only) -->
                <div class="sidebar-footer">
                    <div class="customer-nav-control d-flex align-items-center justify-content-between">
                        <span>Auto-close menus</span>
                        <button type="button" id="autoHideToggle" aria-label="Toggle auto-hide menus">
                            <i class="fas fa-toggle-off"></i>
                        </button>
                    </div>
                    
                    <?php if ($customerMailbox) { ?>
                        <div class="customer-mailbox-card">
                            <div class="label">Your Mailbox</div>
                            <div class="value">
                                <span>{{ $customerMailbox }}</span>
                                <button type="button" id="copyMailboxBtn" data-mailbox="{{ $customerMailbox }}" aria-label="Copy mailbox">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    <?php } ?>
                    
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="customer-logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Log Out</span>
                        </button>
                    </form>
                </div>
            <?php } ?>
        </aside>

        <main class="content-wrap">
            <nav class="topbar d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="page-title">{{ $pageTitle }}</h1>
                    <div class="breadcrumb-line">
                        {{ $sidebarName }} / {{ $breadcrumbTitle }}
                    </div>
                    <?php if ($user->user_type === 'customer') { ?>
                        <form method="GET" action="{{ route('customer.packages') }}" class="customer-top-search">
                            <i class="fas fa-search"></i>
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search tracking numbers, packages..." aria-label="Search customer portal">
                        </form>
                    <?php } ?>
                    <?php if (in_array($user->user_type, ['tenant_admin', 'staff'])) { ?>
                        <form method="GET" action="{{ route('courier.packages.index') }}" class="courier-top-search">
                            <i class="fas fa-search"></i>
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search mailbox, name, tracking..." aria-label="Search courier portal">
                        </form>
                    <?php } ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button type="button" class="topbar-action {{ $notificationCount > 0 ? 'has-unread' : '' }}" data-notification-button data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" data-notification-badge @if($notificationCount <= 0) hidden @endif>{{ $notificationCount }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-menu">
                            <div class="p-3 d-flex justify-content-between align-items-center">
                                <strong>Notifications</strong>
                                <?php if ($user->user_type === 'super_admin') { ?>
                                    <a href="{{ route('superadmin.notifications.index') }}" class="small fw-bold">Manage</a>
                                <?php } ?>
                            </div>
                            @foreach($directNotifications as $notification)
                                <a class="notification-item d-block text-dark text-decoration-none {{ $notification->is_read ? '' : 'bg-light' }}" href="{{ route('notifications.direct.open', $notification) }}">
                                    <div class="fw-bold"><span class="notification-dot me-2" style="background: {{ $notification->is_read ? '#94a3b8' : '#dc2626' }}"></span>{{ $notification->title }}</div>
                                    <div class="text-muted small mt-1">{{ $notification->messagePreview() }}</div>
                                </a>
                            @endforeach
                            @forelse($availableNotifications as $notification)
                                @php($dot = $notification->priority === 'high' ? '#dc2626' : ($notification->priority === 'medium' ? '#f59e0b' : '#135fd2'))
                                <div class="notification-item">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div class="fw-bold"><span class="notification-dot me-2" style="background: {{ $dot }}"></span>{{ $notification->title }}</div>
                                        <form method="POST" action="{{ route('notifications.dismiss', $notification) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                        </form>
                                    </div>
                                    <div class="text-muted small mt-1">{{ $notification->messagePreview() }}</div>
                                </div>
                            @empty
                                @if($directNotifications->isEmpty())
                                    <div class="p-4 text-center text-muted">No new notifications.</div>
                                @endif
                            @endforelse
                        </div>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="user-menu text-start" data-bs-toggle="dropdown" aria-expanded="false">
                            @if(optional(auth()->user())->image)
                                <img src="{{ asset(auth()->user()->image) }}" alt="{{ $displayName }}" class="avatar-circle" style="object-fit: cover;">
                            @else
                                <span class="avatar-circle">{{ strtoupper(substr($displayName, 0, 2)) }}</span>
                            @endif
                            <span class="flex-grow-1">
                                <span class="d-block fw-bold text-dark">{{ $displayName }}</span>
                                <span class="d-block text-muted small text-capitalize">{{ $roleLabel }}</span>
                            </span>
                            <i class="fas fa-chevron-down text-muted"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="fas fa-arrow-right-from-bracket me-2"></i>Sign Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="page-pad">
                <?php if ($loginAlerts->count() || session('success') || session('warning') || session('fresh_security_code') || session('success_security_code') || (isset($errors) && $errors->any())) { ?>
                    <div class="app-alert-stack">
                        <?php foreach ($loginAlerts as $alert) { ?>
                            <?php
                                $alertClass = $alert->priority === 'high' ? 'alert-danger-modern' : ($alert->priority === 'medium' ? 'alert-warning-modern' : 'alert-info-modern');
                                $alertIcon = $alert->priority === 'high' ? 'fa-triangle-exclamation' : ($alert->priority === 'medium' ? 'fa-circle-exclamation' : 'fa-circle-info');
                            ?>
                            <div class="app-alert-card {{ $alertClass }}" data-app-alert>
                                <div class="app-alert-icon"><i class="fas {{ $alertIcon }}"></i></div>
                                <div>
                                    <div class="app-alert-title">{{ $alert->title }}</div>
                                    <div class="app-alert-message">{!! $alert->messageHtml() !!}</div>
                                </div>
                                <div class="app-alert-actions">
                                    <form method="POST" action="{{ route('notifications.dismiss', $alert) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                    </form>
                                    <button type="button" class="app-alert-close" data-app-alert-close aria-label="Close alert"><i class="fas fa-xmark"></i></button>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (session('fresh_security_code')) { ?>
                            <div class="app-alert-card alert-info-modern" data-app-alert>
                                <div class="app-alert-icon"><i class="fas fa-key"></i></div>
                                <div>
                                    <div class="app-alert-title">Single-use security code</div>
                                    <div class="app-alert-message">Your destructive-action code is <strong>{{ session('fresh_security_code') }}</strong>. It is replaced after one successful use.</div>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (session('success_security_code')) { ?>
                            <div class="app-alert-card alert-info-modern" data-app-alert>
                                <div class="app-alert-icon"><i class="fas fa-shield"></i></div>
                                <div>
                                    <div class="app-alert-title">Security code rotated</div>
                                    <div class="app-alert-message">{{ session('success_security_code') }}</div>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (session('success')) { ?>
                            <div class="app-alert-card alert-successful" data-app-alert data-auto-dismiss>
                                <div class="app-alert-icon"><i class="fas fa-check"></i></div>
                                <div>
                                    <div class="app-alert-title">Success</div>
                                    <div class="app-alert-message">{{ session('success') }}</div>
                                </div>
                                <div class="app-alert-actions">
                                    <button type="button" class="app-alert-close" data-app-alert-close aria-label="Close alert"><i class="fas fa-xmark"></i></button>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (session('warning')) { ?>
                            <div class="app-alert-card alert-warning-modern" data-app-alert>
                                <div class="app-alert-icon"><i class="fas fa-circle-exclamation"></i></div>
                                <div>
                                    <div class="app-alert-title">Access notice</div>
                                    <div class="app-alert-message">{{ session('warning') }}</div>
                                </div>
                                <div class="app-alert-actions">
                                    <button type="button" class="app-alert-close" data-app-alert-close aria-label="Close alert"><i class="fas fa-xmark"></i></button>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($errors) && $errors->any()) { ?>
                            <div class="app-alert-card alert-danger-modern" data-app-alert>
                                <div class="app-alert-icon"><i class="fas fa-triangle-exclamation"></i></div>
                                <div>
                                    <div class="app-alert-title">Please check the form</div>
                                    <div class="app-alert-message">{{ $errors->first() }}</div>
                                </div>
                                <div class="app-alert-actions">
                                    <button type="button" class="app-alert-close" data-app-alert-close aria-label="Close alert"><i class="fas fa-xmark"></i></button>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
                @yield('content')
            </div>
        </main>
    </div>
<?php } ?>
<?php if (! auth()->check()) { ?>
    <main>
        @yield('content')
    </main>
<?php } ?>
<div class="app-confirm-backdrop" data-confirm-backdrop hidden>
    <div class="app-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle" aria-describedby="appConfirmMessage">
        <div class="app-confirm-head">
            <div class="app-confirm-icon"><i class="fas fa-circle-question"></i></div>
            <div>
                <div class="app-confirm-title" id="appConfirmTitle" data-confirm-title>Confirm action</div>
                <div class="app-confirm-message" id="appConfirmMessage" data-confirm-message>Are you sure you want to continue?</div>
            </div>
        </div>
        <div class="app-confirm-verification" data-confirm-verification hidden>
            <label class="form-label small fw-bold">Type the exact phrase</label>
            <input class="form-control mb-2" data-confirm-phrase-input value="" placeholder="I understand this action is permanent">
            <div class="small text-muted mb-2">
                Required phrase: <strong data-confirm-required-phrase>I understand this action is permanent</strong>
                <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-confirm-use-phrase>Use phrase</button>
            </div>
            <label class="form-label small fw-bold">Personal destructive-action code</label>
            <input class="form-control" data-confirm-code-input value="{{ session('fresh_security_code', '') }}" placeholder="Use the current single-use code from your alerts">
            @auth
                @if(in_array(auth()->user()->user_type, ['tenant_admin', 'super_admin'], true))
                    <form method="POST" action="{{ auth()->user()->user_type === 'super_admin' ? route('superadmin.settings.personal-security-code') : route('courier.settings.personal-security-code') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-key me-1"></i>Generate new code
                        </button>
                        <span class="small text-muted ms-1">Replaces the previous code.</span>
                    </form>
                @endif
            @endauth
        </div>
        <div class="app-confirm-actions">
            <button type="button" class="btn btn-outline-secondary" data-confirm-cancel>Cancel</button>
            <button type="button" class="btn btn-primary" data-confirm-ok>Continue</button>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
const appSoundPromptsEnabled = @json($soundPromptsEnabled ?? true);

function playTone(frequency, duration, volume) {
    if (!appSoundPromptsEnabled) return;
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(frequency, audioCtx.currentTime);
        gain.gain.setValueAtTime(volume, audioCtx.currentTime);
        oscillator.connect(gain);
        gain.connect(audioCtx.destination);
        oscillator.start();
        oscillator.stop(audioCtx.currentTime + (duration / 1000));
        oscillator.onended = function () {
            audioCtx.close();
        };
    } catch (error) {
        console.warn('Audio alarm unavailable:', error);
    }
}

function playNotificationSound() {
    playTone(880, 120, 0.15);
    setTimeout(function () { playTone(660, 120, 0.12); }, 140);
}

function playErrorSound() {
    playTone(440, 260, 0.35);
    setTimeout(function () { playTone(360, 260, 0.28); }, 180);
    setTimeout(function () { playTone(320, 200, 0.22); }, 420);
}

function speakAppAction(message) {
    if (!appSoundPromptsEnabled) return;
    if (!message || !('speechSynthesis' in window)) return;
    try {
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.rate = 0.95;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
    } catch (error) {}
}

function playAppActionSound(action) {
    const normalized = String(action || '').toLowerCase().replace(/[_-]+/g, ' ').trim();
    const labels = {
        success: 'Saved',
        save: 'Saved',
        saved: 'Saved',
        failed: 'Failed',
        fail: 'Failed',
        error: 'Failed',
        'new notification': 'New notification',
        notification: 'New notification',
        'new message': 'Sent',
        message: 'Sent',
        sent: 'Sent',
        'invoice created': 'Invoice created',
        invoice: 'Invoice created',
        'manifest created': 'Manifest created',
        manifest: 'Manifest created',
        'account created': 'Account created',
        account: 'Account created',
        'check in': 'Check In',
        'check out': 'Check Out',
        duplicate: 'Duplicate'
    };

    if (['failed', 'fail', 'error', 'duplicate'].includes(normalized)) {
        playErrorSound();
    } else {
        playNotificationSound();
    }

    speakAppAction(labels[normalized] || action);
}

document.addEventListener('DOMContentLoaded', function () {
    function normalizePaginationControls(root) {
        const scope = root || document;
        scope.querySelectorAll('nav[role="navigation"] svg, .pagination svg').forEach(function (svg) {
            svg.style.setProperty('width', '16px', 'important');
            svg.style.setProperty('height', '16px', 'important');
            svg.style.setProperty('min-width', '16px', 'important');
            svg.style.setProperty('min-height', '16px', 'important');
            svg.style.setProperty('max-width', '16px', 'important');
            svg.style.setProperty('max-height', '16px', 'important');
            svg.style.setProperty('display', 'inline-block', 'important');
            svg.style.setProperty('flex', '0 0 16px', 'important');

            const control = svg.closest('a, span');
            if (control) {
                control.style.setProperty('display', 'inline-flex', 'important');
                control.style.setProperty('align-items', 'center', 'important');
                control.style.setProperty('justify-content', 'center', 'important');
                control.style.setProperty('width', 'auto', 'important');
                control.style.setProperty('height', '38px', 'important');
                control.style.setProperty('min-width', '38px', 'important');
                control.style.setProperty('overflow', 'hidden', 'important');
            }
        });
    }

    normalizePaginationControls(document);
    const paginationObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) {
                    normalizePaginationControls(node);
                }
            });
        });
    });
    paginationObserver.observe(document.body, { childList: true, subtree: true });
    document.querySelectorAll('.page-pad table.table, .page-pad table.admin-table, .page-pad table.roles-ui-table').forEach(function (table) {
        if (table.closest('.table-responsive') || table.closest('.no-responsive-wrap')) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
    const notificationCountsUrl = @json(auth()->check() ? route('notifications.counts') : null);
    if (notificationCountsUrl) {
        const bellButton = document.querySelector('[data-notification-button]');
        const bellBadge = document.querySelector('[data-notification-badge]');
        let lastNotificationTotal = bellBadge ? parseInt(bellBadge.textContent || '0', 10) || 0 : 0;
        let lastDirectNotificationId = null;

        function setCountBadge(badge, count) {
            if (!badge) return;
            const total = parseInt(count || 0, 10);
            badge.textContent = total;
            badge.hidden = total <= 0;
            const navLink = badge.closest('.nav-link');
            if (navLink) {
                navLink.classList.toggle('has-unread-notification', total > 0);
            }
        }

        function escapeNotificationText(value) {
            return String(value || '').replace(/[<>&]/g, function (char) {
                return ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' })[char];
            });
        }

        function showDirectNotificationToast(notification) {
            if (!notification || !notification.id) return;
            const toast = document.createElement('a');
            toast.href = notification.url || '#';
            toast.className = 'notification-item bg-white text-dark text-decoration-none shadow-lg';
            toast.style.position = 'fixed';
            toast.style.right = '22px';
            toast.style.top = '86px';
            toast.style.zIndex = '1080';
            toast.style.width = 'min(360px, calc(100vw - 32px))';
            toast.style.border = '1px solid #dbe3ef';
            toast.style.borderRadius = '8px';
            toast.innerHTML = '<div class="fw-bold"><span class="notification-dot me-2" style="background:#dc2626"></span>' + escapeNotificationText(notification.title || 'New notification') + '</div><div class="text-muted small mt-1">' + escapeNotificationText(notification.message || '') + '</div>';
            document.body.appendChild(toast);
            setTimeout(function () { toast.remove(); }, 8500);
        }

        function applyNotificationCounts(payload, playSound) {
            const total = parseInt(payload.total || 0, 10);
            setCountBadge(bellBadge, total);
            if (bellButton) {
                bellButton.classList.toggle('has-unread', total > 0);
            }

            const types = payload.types || {};
            document.querySelectorAll('[data-direct-notification-badge]').forEach(function (badge) {
                setCountBadge(badge, types[badge.dataset.directNotificationBadge] || 0);
            });

            document.querySelectorAll('[data-support-chat-badge]').forEach(function (badge) {
                setCountBadge(badge, types.support_message || 0);
            });

            if (playSound && total > lastNotificationTotal && typeof window.playNotificationSound === 'function') {
                window.playNotificationSound();
            }
            lastNotificationTotal = total;
        }

        async function refreshNotificationCounts(playSound = true) {
            try {
                const response = await fetch(notificationCountsUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (!response.ok) return;
                applyNotificationCounts(await response.json(), playSound);
            } catch (error) {
                // Keep the UI quiet if polling fails during navigation or session expiry.
            }
        }

        refreshNotificationCounts(false);
        setInterval(function () {
            if (document.visibilityState === 'visible') {
                refreshNotificationCounts(true);
            }
        }, 30000);
        window.addEventListener('focus', function () {
            refreshNotificationCounts(true);
        });
    }

    // Alert close functionality
    document.querySelectorAll('[data-app-alert-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            const alert = button.closest('[data-app-alert]');
            if (!alert) return;
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(function () { alert.remove(); }, 180);
        });
    });

    const alertCards = document.querySelectorAll('[data-app-alert]');
    if (alertCards.length) {
        const hasError = Array.from(alertCards).some(function (alert) {
            return alert.classList.contains('alert-danger-modern') || alert.classList.contains('alert-danger') || alert.textContent.toLowerCase().includes('please check');
        });

        if (hasError) {
            playErrorSound();
            const alertText = Array.from(alertCards).map(function (alert) { return alert.textContent.toLowerCase(); }).join(' ');
            speakAppAction(alertText.includes('duplicate') || alertText.includes('duplicated') ? 'Duplicate' : 'Failed');
        } else {
            const alertText = Array.from(alertCards).map(function (alert) { return alert.textContent.toLowerCase(); }).join(' ');
            if (alertText.includes('check out')) {
                playAppActionSound('check out');
            } else if (alertText.includes('check in')) {
                playAppActionSound('check in');
            } else if (alertText.includes('invoice')) {
                playAppActionSound('invoice created');
            } else if (alertText.includes('manifest')) {
                playAppActionSound('manifest created');
            } else if (alertText.includes('account')) {
                playAppActionSound('account created');
            } else if (alertText.includes('notification')) {
                playAppActionSound('new notification');
            } else if (alertText.includes('sent')) {
                playAppActionSound('sent');
            } else if (alertText.includes('message')) {
                playAppActionSound('sent');
            } else {
                playAppActionSound('saved');
            }
        }
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-action-sound]');
        if (!trigger) return;
        playAppActionSound(trigger.getAttribute('data-action-sound'));
    });

    // Auto-dismiss alerts
    document.querySelectorAll('[data-auto-dismiss]').forEach(function (alert) {
        setTimeout(function () {
            if (!document.body.contains(alert)) return;
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(function () { alert.remove(); }, 180);
        }, 5200);
    });

    // Copy mailbox functionality (Customer only)
    const copyMailboxBtn = document.getElementById('copyMailboxBtn');
    if (copyMailboxBtn) {
        copyMailboxBtn.addEventListener('click', function () {
            const value = this.getAttribute('data-mailbox') || '';
            const original = this.innerHTML;
            navigator.clipboard.writeText(value).then(function () {
                copyMailboxBtn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function () { copyMailboxBtn.innerHTML = original; }, 1400);
            }).catch(function () {
                alert('Could not copy text');
            });
        });
    }

    // Keep action dropdowns usable inside horizontally scrollable tables.
    const isResponsiveTableDropdown = function (toggle) {
        return Boolean(toggle && toggle.matches('[data-bs-toggle="dropdown"]') && toggle.closest('.table-responsive'));
    };

    const responsiveTableDropdownMenu = function (toggle) {
        const dropdown = toggle.closest('.dropdown');
        return dropdown ? dropdown.querySelector('.dropdown-menu') : null;
    };

    const resetResponsiveTableDropdown = function (toggle) {
        const menu = responsiveTableDropdownMenu(toggle);
        if (!menu) return;

        menu.classList.remove('show');
        menu.removeAttribute('data-popper-placement');
        ['position', 'transform', 'right', 'bottom', 'zIndex', 'width', 'left', 'top'].forEach(function (prop) {
            menu.style[prop] = '';
        });

        toggle.setAttribute('aria-expanded', 'false');
        const dropdown = toggle.closest('.dropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    };

    const closeResponsiveTableDropdowns = function (exceptToggle) {
        document.querySelectorAll('.table-responsive [data-bs-toggle="dropdown"][aria-expanded="true"]').forEach(function (openToggle) {
            if (openToggle !== exceptToggle) {
                resetResponsiveTableDropdown(openToggle);
            }
        });
    };

    const positionResponsiveTableDropdown = function (toggle) {
        const menu = responsiveTableDropdownMenu(toggle);
        if (!menu || !menu.classList.contains('show')) return;

        menu.style.position = 'fixed';
        menu.style.transform = 'none';
        menu.style.right = 'auto';
        menu.style.bottom = 'auto';
        menu.style.zIndex = '3050';

        const toggleRect = toggle.getBoundingClientRect();
        const measuredWidth = menu.offsetWidth || 220;
        const measuredHeight = menu.offsetHeight || 180;
        const menuWidth = Math.min(measuredWidth, window.innerWidth - 16);
        const menuHeight = Math.min(measuredHeight, window.innerHeight - 16);
        const left = Math.max(8, Math.min(toggleRect.right - menuWidth, window.innerWidth - menuWidth - 8));
        const preferredTop = toggleRect.bottom + 6;
        const top = preferredTop + menuHeight > window.innerHeight - 8
            ? Math.max(8, toggleRect.top - menuHeight - 6)
            : preferredTop;

        menu.style.width = menuWidth + 'px';
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    };

    const openResponsiveTableDropdown = function (toggle) {
        const menu = responsiveTableDropdownMenu(toggle);
        const dropdown = toggle.closest('.dropdown');
        if (!menu || !dropdown) return;

        closeResponsiveTableDropdowns(toggle);
        dropdown.classList.add('show');
        menu.classList.add('show');
        menu.setAttribute('data-popper-placement', 'bottom-end');
        toggle.setAttribute('aria-expanded', 'true');

        requestAnimationFrame(function () {
            positionResponsiveTableDropdown(toggle);
        });
    };

    const suppressedResponsiveTableDropdownClicks = new WeakSet();

    const toggleResponsiveTableDropdown = function (toggle) {
        if (toggle.getAttribute('aria-expanded') === 'true') {
            resetResponsiveTableDropdown(toggle);
        } else {
            openResponsiveTableDropdown(toggle);
        }
    };

    document.addEventListener('pointerdown', function (event) {
        const toggle = event.target.closest('.table-responsive [data-bs-toggle="dropdown"]');
        if (!isResponsiveTableDropdown(toggle)) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        suppressedResponsiveTableDropdownClicks.add(toggle);
        toggleResponsiveTableDropdown(toggle);
    }, true);

    document.addEventListener('touchstart', function (event) {
        if (window.PointerEvent) return;
        const toggle = event.target.closest('.table-responsive [data-bs-toggle="dropdown"]');
        if (!isResponsiveTableDropdown(toggle)) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        suppressedResponsiveTableDropdownClicks.add(toggle);
        toggleResponsiveTableDropdown(toggle);
    }, { capture: true, passive: false });

    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.table-responsive [data-bs-toggle="dropdown"]');
        if (!isResponsiveTableDropdown(toggle)) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (suppressedResponsiveTableDropdownClicks.has(toggle)) {
            suppressedResponsiveTableDropdownClicks.delete(toggle);
            return;
        }

        toggleResponsiveTableDropdown(toggle);
    }, true);

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.table-responsive .dropdown')) {
            closeResponsiveTableDropdowns(null);
        }
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('.table-responsive .dropdown-menu .dropdown-item')) {
            closeResponsiveTableDropdowns(null);
        }
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('.table-responsive [data-bs-toggle="dropdown"][aria-expanded="true"]').forEach(positionResponsiveTableDropdown);
    });

    document.addEventListener('scroll', function () {
        document.querySelectorAll('.table-responsive [data-bs-toggle="dropdown"][aria-expanded="true"]').forEach(positionResponsiveTableDropdown);
    }, true);
    // Sidebar Expand/Collapse Functionality (Customer only)
    const sidebar = document.getElementById('mainSidebar');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    
    if (sidebar && collapseBtn && document.body.classList.contains('customer-portal')) {
        const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        if (isSidebarCollapsed) {
            sidebar.classList.add('collapsed');
        }
        
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
    }

    // Mobile sidebar toggle
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    if (sidebar && mobileToggle) {
        const syncMobileSidebarState = function () {
            const isOpen = sidebar.classList.contains('open') || sidebar.classList.contains('show');
            document.body.classList.toggle('sidebar-drawer-open', isOpen);
            mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            syncMobileSidebarState();
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && sidebar && mobileToggle) {
                if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target) && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    syncMobileSidebarState();
                }
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('open');
                syncMobileSidebarState();
            }
        });

        syncMobileSidebarState();
    }

    // Menu expand/collapse functionality (Customer only)
    if (document.body.classList.contains('customer-portal')) {
        const menuToggles = document.querySelectorAll('.customer-menu-toggle');
        const autoHideToggle = document.getElementById('autoHideToggle');
        let autoHideEnabled = localStorage.getItem('customerAutoHide') === 'true';
        
        function updateAutoHideIcon() {
            if (autoHideToggle) {
                autoHideToggle.innerHTML = autoHideEnabled ? '<i class="fas fa-toggle-on"></i>' : '<i class="fas fa-toggle-off"></i>';
            }
        }
        
        function closeAllMenus() {
            menuToggles.forEach(toggle => {
                const menuKey = toggle.getAttribute('data-menu');
                const submenu = document.querySelector(`.customer-subnav[data-submenu="${menuKey}"]`);
                if (submenu && !submenu.classList.contains('hidden')) {
                    submenu.classList.add('hidden');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        function collapseCustomerSidebar() {
            closeAllMenus();
            if (!sidebar) return;

            if (window.innerWidth <= 992) {
                sidebar.classList.remove('open', 'show');
                document.body.classList.remove('sidebar-drawer-open');
                if (mobileToggle) {
                    mobileToggle.setAttribute('aria-expanded', 'false');
                }
                return;
            }

            sidebar.classList.add('collapsed');
            localStorage.setItem('sidebarCollapsed', 'true');
        }

        function openMenu(button) {
            const menuKey = button.getAttribute('data-menu');
            const submenu = document.querySelector(`.customer-subnav[data-submenu="${menuKey}"]`);
            if (!submenu) return;
            submenu.classList.remove('hidden');
            button.setAttribute('aria-expanded', 'true');
        }
        
        function toggleMenu(button) {
            const menuKey = button.getAttribute('data-menu');
            const submenu = document.querySelector(`.customer-subnav[data-submenu="${menuKey}"]`);
            
            if (!submenu) return;
            
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            
            if (autoHideEnabled && !isExpanded) {
                closeAllMenus();
            }
            
            if (isExpanded) {
                submenu.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
            } else {
                openMenu(button);
            }
        }
        
        menuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu(this);
            });
            
            const menuKey = toggle.getAttribute('data-menu');
            const submenu = document.querySelector(`.customer-subnav[data-submenu="${menuKey}"]`);
            if (submenu && !submenu.classList.contains('hidden')) {
                toggle.setAttribute('aria-expanded', 'true');
            } else {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });

        const activeSubnavLink = document.querySelector('.customer-subnav .nav-link.active');
        if (activeSubnavLink && !autoHideEnabled) {
            const activeSubnav = activeSubnavLink.closest('.customer-subnav');
            const activeToggle = activeSubnav ? document.querySelector(`.customer-menu-toggle[data-menu="${activeSubnav.dataset.submenu}"]`) : null;
            if (activeToggle) {
                openMenu(activeToggle);
            }
        }

        if (autoHideEnabled) {
            collapseCustomerSidebar();
        }

        document.querySelectorAll('#mainSidebar a.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (!autoHideEnabled) return;

                collapseCustomerSidebar();
            });
        });
        
        if (autoHideToggle) {
            autoHideToggle.addEventListener('click', function() {
                autoHideEnabled = !autoHideEnabled;
                localStorage.setItem('customerAutoHide', autoHideEnabled);
                updateAutoHideIcon();
                if (autoHideEnabled) {
                    collapseCustomerSidebar();
                } else if (sidebar && window.innerWidth > 992) {
                    sidebar.classList.remove('collapsed');
                    localStorage.setItem('sidebarCollapsed', 'false');
                }
            });
        }
        
        updateAutoHideIcon();
    }

    // Lightweight searchable selects for long customer/mailbox dropdowns.
    document.querySelectorAll('[data-search-select]').forEach(function (input) {
        if (input.dataset.searchSelectReady === '1') return;
        input.dataset.searchSelectReady = '1';

        const key = input.dataset.searchSelect;
        const remoteUrl = input.dataset.remoteUrl || '';
        const select = key === 'customer_id'
            ? input.parentElement.querySelector('select[name="customer_id"]') || document.querySelector('select[name="customer_id"]')
            : input.parentElement.querySelector('[data-' + key + ']') || document.querySelector('[data-' + key + ']');

        if (!select) return;

        const normalize = function (value) {
            return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
        };

        const optionSearchText = function (option) {
            return normalize(option.dataset.searchText || option.textContent || option.innerText || '');
        };

        const applyFilter = function () {
            const terms = normalize(input.value).split(' ').filter(Boolean);
            let firstVisible = null;

            Array.from(select.options).forEach(function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const haystack = optionSearchText(option);
                const visible = terms.every(function (term) { return haystack.includes(term); });
                option.hidden = !visible;
                if (visible && !firstVisible) firstVisible = option;
            });

            select.dataset.firstVisibleValue = firstVisible ? firstVisible.value : '';
        };

        let remoteTimer = null;
        let remoteController = null;
        const setPlaceholder = function (text) {
            select.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = text;
            select.appendChild(option);
            select.dataset.firstVisibleValue = '';
        };
        const remoteSearch = function () {
            if (!remoteUrl) {
                applyFilter();
                return;
            }

            const query = input.value.trim();
            if (query.length < 2) {
                setPlaceholder('Type at least 2 characters');
                return;
            }

            if (remoteController) remoteController.abort();
            remoteController = new AbortController();
            setPlaceholder('Searching...');

            fetch(remoteUrl + '?q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: remoteController.signal
            })
                .then(function (response) { return response.ok ? response.json() : []; })
                .then(function (items) {
                    select.innerHTML = '';
                    if (!Array.isArray(items) || !items.length) {
                        setPlaceholder('No matching customers');
                        return;
                    }

                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = 'Select customer';
                    select.appendChild(placeholder);

                    items.forEach(function (item) {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.label;
                        option.dataset.searchText = item.label;
                        select.appendChild(option);
                    });

                    select.dataset.firstVisibleValue = items[0].id;
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    setPlaceholder('Search failed');
                });
        };

        input.addEventListener('input', function () {
            if (!remoteUrl) {
                applyFilter();
                return;
            }
            window.clearTimeout(remoteTimer);
            remoteTimer = window.setTimeout(remoteSearch, 220);
        });
        input.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            const firstVisibleValue = select.dataset.firstVisibleValue;
            if (!firstVisibleValue) return;
            event.preventDefault();
            select.value = firstVisibleValue;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        select.addEventListener('change', function () {
            const selected = select.options[select.selectedIndex];
            if (selected && selected.value) {
                input.value = selected.textContent.trim();
                applyFilter();
            }
        });

        applyFilter();
    });
    // Confirmation modal functionality
    const decodeConfirmMessage = function (value) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = value || 'Are you sure you want to continue?';
        return textarea.value;
    };

    const confirmBackdrop = document.querySelector('[data-confirm-backdrop]');
    if (confirmBackdrop) {
        const confirmTitle = confirmBackdrop.querySelector('[data-confirm-title]');
        const confirmMessage = confirmBackdrop.querySelector('[data-confirm-message]');
        const confirmOk = confirmBackdrop.querySelector('[data-confirm-ok]');
        const confirmCancel = confirmBackdrop.querySelector('[data-confirm-cancel]');
        const confirmVerification = confirmBackdrop.querySelector('[data-confirm-verification]');
        const confirmPhraseInput = confirmBackdrop.querySelector('[data-confirm-phrase-input]');
        const confirmCodeInput = confirmBackdrop.querySelector('[data-confirm-code-input]');
        const confirmRequiredPhrase = confirmBackdrop.querySelector('[data-confirm-required-phrase]');
        const confirmUsePhrase = confirmBackdrop.querySelector('[data-confirm-use-phrase]');
        let pendingForm = null;

        const showConfirm = function (message, form) {
            pendingForm = form;
            confirmTitle.textContent = form.dataset.confirmTitle || 'Confirm action';
            confirmMessage.textContent = message || 'Are you sure you want to continue?';
            const destructive = String(new FormData(form).get('_method') || '').toUpperCase() === 'DELETE'
                || (form.action || '').match(/void|clear|destroy|delete|mass-delete/i);
            if (confirmVerification) {
                confirmVerification.hidden = !destructive;
            }
            if (confirmPhraseInput) confirmPhraseInput.value = '';
            confirmBackdrop.hidden = false;
            (destructive && confirmPhraseInput ? confirmPhraseInput : confirmOk).focus();
        };

        const closeConfirm = function () {
            confirmBackdrop.hidden = true;
            pendingForm = null;
        };

        document.querySelectorAll('form[onsubmit*="confirm("]').forEach(function (form) {
            const handler = form.getAttribute('onsubmit') || '';
            const match = handler.match(/confirm\((['"])(.*?)\1\)/);
            form.dataset.confirm = decodeConfirmMessage(match ? match[2] : 'Are you sure you want to continue?');
            form.removeAttribute('onsubmit');
        });

        document.addEventListener('submit', function (event) {
            const form = event.target;
            const destructive = String(new FormData(form).get('_method') || '').toUpperCase() === 'DELETE'
                || (form.action || '').match(/void|clear|destroy|delete|mass-delete/i);
            if (!form.matches('form[data-confirm]') && !destructive) return;
            if (!form.dataset.confirm && destructive) {
                form.dataset.confirm = 'This action is permanent. Continue?';
            }
            if (form.dataset.confirmed === '1') return;
            event.preventDefault();
            showConfirm(form.dataset.confirm, form);
        }, true);

        confirmOk.addEventListener('click', function () {
            if (!pendingForm) return;
            if (confirmVerification && !confirmVerification.hidden) {
                pendingForm.querySelectorAll('[data-generated-destructive-field]').forEach(function (field) { field.remove(); });
                const phrase = document.createElement('input');
                phrase.type = 'hidden';
                phrase.name = 'confirm_phrase';
                phrase.value = confirmPhraseInput ? confirmPhraseInput.value : '';
                phrase.setAttribute('data-generated-destructive-field', '1');
                const code = document.createElement('input');
                code.type = 'hidden';
                code.name = 'security_code';
                code.value = confirmCodeInput ? confirmCodeInput.value : '';
                code.setAttribute('data-generated-destructive-field', '1');
                pendingForm.appendChild(phrase);
                pendingForm.appendChild(code);
            }
            pendingForm.dataset.confirmed = '1';
            pendingForm.submit();
        });

        if (confirmUsePhrase && confirmPhraseInput) {
            confirmUsePhrase.addEventListener('click', function () {
                confirmPhraseInput.value = confirmRequiredPhrase
                    ? confirmRequiredPhrase.textContent.trim()
                    : 'I understand this action is permanent';
                confirmPhraseInput.focus();
            });
        }

        confirmCancel.addEventListener('click', closeConfirm);
        confirmBackdrop.addEventListener('click', function (event) {
            if (event.target === confirmBackdrop) closeConfirm();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !confirmBackdrop.hidden) closeConfirm();
        });
    }

    // DataTable initialization
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('table.table').each(function () {
            const table = jQuery(this);
            const element = table.get(0);
            const hasHeadings = table.find('thead th').length > 0;
            const insideForm = table.closest('form').length > 0;
            const skipTable = table.is('[data-no-datatable], .no-datatable, .receipt-table') || table.closest('[data-no-datatable]').length > 0;
            const hasLaravelPagination = table.closest('.panel').find('nav[aria-label="Pagination Navigation"], .pagination').length > 0;

            if (!hasHeadings || insideForm || skipTable || hasLaravelPagination || jQuery.fn.DataTable.isDataTable(element)) {
                return;
            }

            const headerCount = table.find('thead th').length;
            const hasSpanCells = table.find('tbody td[colspan], tbody th[colspan], tbody td[rowspan], tbody th[rowspan]').length > 0;
            let hasInvalidRows = false;
            table.find('tbody tr').each(function () {
                const cellCount = jQuery(this).children('td, th').length;
                if (cellCount > 0 && cellCount !== headerCount) {
                    hasInvalidRows = true;
                }
            });

            if (hasSpanCells || hasInvalidRows) {
                table.attr('data-no-datatable', 'true');
                return;
            }

            const lastHeader = table.find('thead th').last().text().trim().toLowerCase();
            const columnDefs = [{ targets: 'no-sort', orderable: false }];
            if (lastHeader === '' || lastHeader === 'actions' || lastHeader === 'action') {
                columnDefs.push({ targets: -1, orderable: false, searchable: false });
            }

            table.DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [],
                autoWidth: false,
                language: {
                    search: '',
                    searchPlaceholder: 'Search table...',
                    lengthMenu: 'Show _MENU_',
                    info: 'Showing _START_ to _END_ of _TOTAL_',
                    emptyTable: 'No records found',
                    zeroRecords: 'No matching records found'
                },
                columnDefs: columnDefs,
                drawCallback: function () {
                    document.querySelectorAll('form[onsubmit*="confirm("]').forEach(function (form) {
                        const handler = form.getAttribute('onsubmit') || '';
                        const match = handler.match(/confirm\((['"])(.*?)\1\)/);
                        form.dataset.confirm = decodeConfirmMessage(match ? match[2] : 'Are you sure you want to continue?');
                        form.removeAttribute('onsubmit');
                    });
                }
            });
        });
    }

    document.querySelectorAll('.courier-menu-title').forEach(link => {
        link.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });
});
</script>
</body>
</html>





