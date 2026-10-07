<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Logistics Package Labels</title>
    <style>
        @page {
            size: 4in 6in;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background: #e5e7eb;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 12px;
            @if($singleLabel ?? false)
            display: flex;
            justify-content: center;
            align-items: center;
            @endif
            min-height: 100vh;
        }
        .toolbar {
            padding: 12px;
            text-align: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #f3f4f6;
            z-index: 100;
        }
        .toolbar button {
            background: #111827;
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            padding: 9px 14px;
            font-weight: 600;
            margin: 0 4px;
        }
        .label {
            background: #fff;
            border: 2px solid #111;
            box-shadow: 8px 0 0 #6b7280;
            display: flex;
            flex-direction: column;
            height: 6in;
            margin: {{ ($singleLabel ?? false) ? '60px auto 0 auto' : '60px auto 16px auto' }};
            padding: 0.19in 0.16in 0.15in;
            width: 4in;
            position: relative;
            overflow: hidden;
            page-break-after: {{ ($singleLabel ?? false) ? 'avoid' : 'always' }};
        }
        .label:last-of-type {
            page-break-after: auto;
        }
        .header {
            align-items: flex-start;
            display: grid;
            gap: 0.26in;
            grid-template-columns: 0.9in 1fr;
            margin-bottom: 0.02in;
        }
        .logo-box {
            align-items: center;
            background: #07182d;
            color: #fff;
            display: flex;
            font-size: 30px;
            font-weight: 900;
            height: 0.78in;
            justify-content: center;
            overflow: hidden;
            width: 0.78in;
        }
        .logo-box img {
            background: #fff;
            height: 100%;
            object-fit: contain;
            padding: 5px;
            width: 100%;
        }
        .sender-name {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.2;
            margin-top: 0.01in;
        }
        .sender-address {
            font-size: 11px;
            line-height: 1.2;
            margin-top: 0.02in;
        }
        .rule {
            border-top: 4px solid #000;
            margin: 0.055in 0 0.09in;
        }
        .row {
            display: grid;
            gap: 0.2in;
            grid-template-columns: 1.35fr 0.65fr;
            margin-bottom: 0.05in;
        }
        .block {
            min-width: 0;
        }
        .label-text {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.2px;
        }
        .value-large {
            font-size: 22px;
            font-weight: 900;
            line-height: 1.1;
            word-wrap: break-word;
        }
        .value {
            font-size: 15px;
            font-weight: normal;
            line-height: 1.2;
            word-wrap: break-word;
        }
        .value-medium {
            font-size: 17px;
            font-weight: 900;
            line-height: 1.2;
            word-wrap: break-word;
        }
        .right {
            text-align: left;
        }
        .ship-details {
            padding-bottom: 0.02in;
        }
        .detail-row {
            display: grid;
            gap: 0.2in;
            grid-template-columns: 1.45fr 0.55fr;
            margin-bottom: 0.1in;
        }
        .contents {
            min-height: 0.52in;
        }
        .created-row {
            align-items: baseline;
            display: grid;
            gap: 0.22in;
            grid-template-columns: auto 1fr;
        }
        .created-row .label-text {
            font-size: 17px;
        }
        .barcode-panel {
            flex-shrink: 0;
            margin-top: auto;
            padding-top: 0.08in;
            text-align: center;
        }
        .generator {
            font-size: 31px;
            font-weight: 900;
            letter-spacing: 1.1px;
            line-height: 1;
            margin-bottom: 0.08in;
            font-family: 'Courier New', monospace;
        }
        .barcode {
            height: 0.74in;
            margin: 0 auto;
            width: 2.25in;
        }
        .barcode-svg,
        .barcode svg {
            display: block;
            height: 100%;
            overflow: visible;
            width: 100%;
        }
        .barcode-value {
            font-family: "Courier New", monospace;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 5px;
            word-wrap: anywhere;
        }
        .footer-credit {
            color: #111;
            flex-shrink: 0;
            font-size: 7px;
            font-weight: 700;
            line-height: 1;
            margin-top: 0.04in;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .extra-ref {
            display: flex;
            flex-shrink: 0;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            padding-top: 0.02in;
            border-top: 1px dashed #aaa;
            font-family: monospace;
        }
        .empty-state {
            margin: 72px auto 0;
            max-width: 520px;
            padding: 20px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-align: center;
        }
        @media print {
            html,
            body {
                background: #fff;
                display: block;
                padding: 0;
                margin: 0;
                min-height: 0;
                overflow: {{ ($singleLabel ?? false) ? 'hidden' : 'visible' }};
                width: 4in;
                @if($singleLabel ?? false)
                height: 6in;
                @endif
            }
            .toolbar,
            .empty-state {
                display: none;
            }
            .label {
                border-width: 2px;
                box-shadow: none;
                margin: 0;
                height: 5.98in;
                padding: 0.19in 0.16in 0.15in;
                page-break-after: {{ ($singleLabel ?? false) ? 'avoid' : 'always' }};
                page-break-inside: avoid;
                break-after: {{ ($singleLabel ?? false) ? 'avoid' : 'page' }};
                break-inside: avoid;
                width: 4in;
            }
            .header {
                gap: 0.18in;
                grid-template-columns: 0.75in 1fr;
            }
            .logo-box {
                height: 0.68in;
                width: 0.68in;
            }
            .sender-name {
                font-size: 16px;
            }
            .sender-address {
                font-size: 10px;
                line-height: 1.15;
            }
            .rule {
                margin: 0.04in 0 0.06in;
            }
            .row,
            .detail-row {
                gap: 0.14in;
                margin-bottom: 0.05in;
            }
            .detail-row {
                margin-bottom: 0.07in;
            }
            .label-text {
                font-size: 14px;
            }
            .value-large {
                font-size: 19px;
            }
            .value-medium {
                font-size: 15px;
            }
            .value {
                font-size: 13px;
            }
            .contents {
                min-height: 0;
            }
            .created-row .label-text {
                font-size: 14px;
            }
            .barcode-panel {
                padding-top: 0.04in;
            }
            .generator {
                font-size: 31px;
                margin-bottom: 0.08in;
            }
            .barcode {
                height: 0.74in;
                width: 2.25in;
            }
            .barcode-value {
                font-size: 11px;
                margin-top: 2px;
            }
            .extra-ref {
                font-size: 10px;
            }
            .footer-credit {
                font-size: 7px;
                margin-top: 0.015in;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print Label</button>
        <button type="button" onclick="window.print()">Download PDF</button>
    </div>

    @forelse($packages as $package)
        @php
            $courierPackage = $package->courierPackage;
            $customer = optional($courierPackage)->customer;
            $tenant = $package->tenant ?: optional($courierPackage)->tenant;
            $tenantId = (int) optional($tenant)->id;
            $tenantName = optional($tenant)->company_name ?: 'System Admin Logistics';
            $tenantLogo = optional($tenant)->logo;
            $tenantLogoUrl = $tenantLogo
                ? (\Illuminate\Support\Str::startsWith($tenantLogo, ['http://', 'https://']) ? $tenantLogo : asset(ltrim($tenantLogo, '/')))
                : null;
            $tenantAddress = collect([
                optional($tenant)->address,
                trim(collect([optional($tenant)->city, optional($tenant)->parish])->filter()->implode(', ')),
            ])->filter()->implode('<br>');

            $configuredPrefix = $tenantId
                ? (\App\Models\TenantSetting::getValue($tenantId, 'hawb_prefix')
                    ?: \App\Models\TenantSetting::getValue($tenantId, 'courier_code')
                    ?: \App\Models\TenantSetting::getValue($tenantId, 'mailbox_prefix'))
                : null;
            $configuredDisplayPrefix = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configuredPrefix)));
            $courierCodePrefix = $configuredDisplayPrefix;

            if ($courierCodePrefix === '') {
                $courierCodePrefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $tenantName), 0, 4)) ?: 'COU';
            }
            if ($configuredDisplayPrefix === '') {
                $configuredDisplayPrefix = $courierCodePrefix;
            }
            if (preg_match('/^OL-?(.+)$/i', $configuredDisplayPrefix, $prefixMatch)) {
                $configuredDisplayPrefix = 'OL-'.$prefixMatch[1];
            } else {
                $configuredDisplayPrefix = 'OL-'.$configuredDisplayPrefix;
            }

            $rawTrackingNumber = strtoupper(trim((string) (optional($courierPackage)->tracking_number ?: $package->tracking_number)));
            $consignee = optional(optional($customer)->user)->full_name ?: optional(optional($customer)->user)->email ?: 'Unassigned Customer';
            $createdAt = optional(optional($courierPackage)->date_received)->format('M j, Y')
                ?: optional($package->checked_in_at)->format('M j, Y')
                ?: optional($package->created_at)->format('M j, Y')
                ?: now()->format('M j, Y');

            $rawMailbox = strtoupper(trim((string) (optional($courierPackage)->mailbox ?: optional($customer)->mailbox)));
            if ($rawMailbox === '') {
                $accountDisplay = 'N/A';
            } else {
                $mailboxWithoutOl = preg_replace('/^OL-?/i', '', $rawMailbox);
                if (preg_match('/^\d+$/', $mailboxWithoutOl)) {
                    $accountDisplay = $configuredDisplayPrefix.str_pad($mailboxWithoutOl, max(4, strlen($mailboxWithoutOl)), '0', STR_PAD_LEFT);
                } else {
                    $accountDisplay = 'OL-'.$mailboxWithoutOl;
                }
            }

            $shipperName = $tenantName;
            $weight = (float) (optional($courierPackage)->weight ?? $package->weight ?? 0);
            $weightUnit = strtolower((string) (optional($courierPackage)->weight_unit ?? $package->weight_unit ?? 'lb'));
            $weightLbs = $weightUnit === 'kg' ? number_format($weight * 2.20462262, 1) : number_format($weight, 1);
            $volumeDisplay = $weightUnit === 'cbm'
                ? number_format($weight * 35.3147, 2).' cu.ft / '.number_format($weight, 3).' CBM'
                : number_format($weight, 2).' cu.ft / '.number_format($weight / 35.3147, 3).' CBM';
            $trackingOther = optional($courierPackage)->supplier_tracking ?: $package->tracking_number ?: optional($courierPackage)->tracking_number;
            $destination = optional(optional($courierPackage)->outlet)->name ?: optional($package->manifest)->manifest_number ?: 'N/A';
            $shipmentMethod = strtolower((string) (optional($courierPackage)->freight_type ?: optional($package->shipment)->shipment_type ?: 'air'));
            $shipmentMethod = str_contains($shipmentMethod, 'sea') ? 'Sea' : 'Air';
            $shipmentRegion = strtolower((string) (optional($package->shipment)->region ?: optional($courierPackage)->region ?: 'USA'));
            $regionPrefix = in_array($shipmentRegion, ['cn', 'china'], true) ? 'CN' : 'US';
            $freightType = $regionPrefix.' '.$shipmentMethod;
            $packageContents = $package->description ?: optional($courierPackage)->description ?: $consignee;
            $createdDate = $createdAt;

            $trackingPrefix = null;
            if (preg_match('/^(.+?)(\d+)$/', $rawTrackingNumber, $trackingParts)) {
                $trackingPrefix = strtoupper(trim($trackingParts[1], " -\t\n\r\0\x0B"));
                $referenceNumber = $trackingParts[2];
            } else {
                $referenceNumber = str_pad((string) $package->id, 6, '0', STR_PAD_LEFT);
            }
            if ($trackingPrefix) {
                $displayPrefix = preg_match('/^OL-?/i', $trackingPrefix)
                    ? preg_replace('/^OL-?/i', 'OL-', $trackingPrefix)
                    : 'OL-'.preg_replace('/[^A-Z0-9-]/i', '', $trackingPrefix);
            } else {
                $displayPrefix = $configuredDisplayPrefix;
            }
            $olReference = $displayPrefix.$referenceNumber;
            $hawbValue = preg_replace('/[^\x20-\x7E]/', '', $olReference) ?: $courierCodePrefix.'000001';
            $barcodeValue = $hawbValue;
            $barcodeSvg = \App\Support\Code128Barcode::svg($barcodeValue);
            $displayGenerator = $hawbValue;
        @endphp

        <main class="label">
            <header class="header">
                <div class="logo-box">
                    @if($tenantLogoUrl)
                        <img src="{{ $tenantLogoUrl }}" alt="{{ $tenantName }} logo">
                    @else
                        {{ strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $tenantName), 0, 1)) ?: 'C' }}
                    @endif
                </div>
                <div>
                    <div class="sender-name">{{ $tenantName }}</div>
                    <div class="sender-address">{!! $tenantAddress ?: '&nbsp;' !!}</div>
                </div>
            </header>

            <div class="rule"></div>

            <div class="row">
                <div class="block">
                    <div class="label-text">Consignee:</div>
                    <div class="value-large">{{ $consignee }}</div>
                </div>
                <div class="block right">
                    <div class="label-text">Account:</div>
                    <div class="value">{{ $accountDisplay }}</div>
                </div>
            </div>

            <div class="rule"></div>

            <section class="ship-details">
                <div class="detail-row">
                    <div class="block">
                        <div class="label-text">Shipper:</div>
                        <div class="value">{{ $shipperName }}</div>
                    </div>
                    <div class="block right">
                        <div class="label-text">{{ in_array($weightUnit, ['cuft', 'cbm'], true) ? 'Volume:' : 'Weight:' }}</div>
                        <div class="value">{{ in_array($weightUnit, ['cuft', 'cbm'], true) ? $volumeDisplay : $weightLbs.' LBS' }}</div>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="block">
                        <div class="label-text">Tracking No - Other</div>
                        <div class="value-medium">{{ $trackingOther ?: '-' }}</div>
                    </div>
                    <div></div>
                </div>

                <div class="detail-row">
                    <div class="block">
                        <div class="label-text">Destination:</div>
                        <div class="value-medium">{{ $destination }}</div>
                    </div>
                    <div class="block right">
                        <div class="label-text">Type:</div>
                        <div class="value-medium">{{ $freightType }}</div>
                    </div>
                </div>
            </section>

            <div class="rule"></div>

            <section class="contents">
                <div class="label-text">Package Contents:</div>
                <div class="value">{{ $packageContents }}</div>

                <div class="created-row">
                    <div class="label-text">Created:</div>
                    <div class="value">{{ $createdDate }}</div>
                </div>
            </section>

            <div class="rule"></div>

            <section class="barcode-panel">
                <div class="generator">{{ $displayGenerator }}</div>
                <div class="barcode">{!! $barcodeSvg !!}</div>
                <div class="barcode-value">{{ $barcodeValue }}</div>
            </section>

            <div class="extra-ref">
                <span>{{ $referenceNumber }}</span>
                <span>{{ $olReference }}</span>
            </div>

            <footer class="footer-credit">powered by freitora.com</footer>
        </main>
    @empty
        <div class="empty-state">
            <h1>No packages to label</h1>
            <p>No logistics packages matched the current filters.</p>
        </div>
    @endforelse

    @if($packages->isNotEmpty())
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>


