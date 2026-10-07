@php
    $tenant = $package->tenant;
    $tenantId = (int) $package->tenant_id;
    $tenantName = optional($tenant)->company_name ?: config('app.name', 'Courier');
    $tenantLogo = optional($tenant)->logo;
    $tenantLogoUrl = $tenantLogo
        ? (\Illuminate\Support\Str::startsWith($tenantLogo, ['http://', 'https://']) ? $tenantLogo : asset(ltrim($tenantLogo, '/')))
        : null;
    $tenantAddress = collect([
        optional($tenant)->address,
        trim(collect([optional($tenant)->city, optional($tenant)->parish])->filter()->implode(', ')),
    ])->filter()->implode('<br>');

    $configuredPrefix = \App\Models\TenantSetting::getValue($tenantId, 'hawb_prefix')
        ?: \App\Models\TenantSetting::getValue($tenantId, 'courier_code')
        ?: \App\Models\TenantSetting::getValue($tenantId, 'mailbox_prefix');
    $configuredDisplayPrefix = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configuredPrefix)));
    $courierCodePrefix = strtoupper(trim(preg_replace('/[^A-Z0-9-]/i', '', (string) $configuredPrefix)));

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

    $rawTrackingNumber = strtoupper(trim((string) $package->tracking_number));

    $consignee = optional(optional($package->customer)->user)->full_name ?: 'Unassigned Customer';
    $createdAt = optional($package->date_received)->format('M j, Y') ?: optional($package->created_at)->format('M j, Y') ?: now()->format('M j, Y');
    
    // Additional fields matching the exact reference image style
    $rawMailbox = strtoupper(trim((string) ($package->mailbox ?: optional($package->customer)->mailbox)));
    if ($rawMailbox === '') {
        $accountDisplay = 'N/A';
    } else {
        $mailboxWithoutOl = preg_replace('/^OL-?/i', '', $rawMailbox);
        if (preg_match('/^\d+$/', $mailboxWithoutOl)) {
            $accountDisplay = $configuredDisplayPrefix
                .str_pad($mailboxWithoutOl, max(4, strlen($mailboxWithoutOl)), '0', STR_PAD_LEFT);
        } else {
            $accountDisplay = 'OL-'.$mailboxWithoutOl;
        }
    }
    $shipperName = $tenantName;
    $isSea = strtolower((string) $package->freight_type) === 'sea';
    $weightDisplay = $isSea ? $package->seaVolumeDisplay() : number_format((float) ($package->weight ?? 0), 1).' LBS';
    $measureLabel = $isSea ? 'Volume:' : 'Weight:';
    $trackingOther = $package->supplier_tracking ?: $package->tracking_number;
    $destination = optional($package->outlet)->name ?: 'N/A';
    $freightType = $package->labelType();
    $packageContents = $package->description ?: $consignee;
    $createdDate = $createdAt;
    
    // Bottom reference comes from the tracking number suffix, not the global package id.
    $trackingPrefix = null;
    if (preg_match('/^(.+?)(\d+)$/', $rawTrackingNumber, $trackingParts)) {
        $trackingPrefix = strtoupper(trim($trackingParts[1], " -\t\n\r\0\x0B"));
        $referenceNumber = $trackingParts[2];
    } else {
        $referenceNumber = $package->reference_number ?? '000001';
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
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Package Label - {{ $package->tracking_number }}</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
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
            margin: 60px auto 0 auto;
            padding: 0.19in 0.16in 0.15in;
            width: 4in;
            position: relative;
            overflow: hidden;
        }
        /* Header section */
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
        /* Two column layout */
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
        /* Ship details rows */
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
            /* margin-top: 0.12in; */
        }
        .created-row .label-text {
            font-size: 17px;
        }
        /* Barcode area exactly like reference image */
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
        .barcode-svg {
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
        /* Extra reference row as seen in image: "000015" and "OL-ZX000015" */
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
        @media print {
            html,
            body {
                background: #fff;
                display: block;
                height: 6in;
                padding: 0;
                margin: 0;
                min-height: 0;
                overflow: hidden;
                width: 4in;
            }
            .toolbar {
                display: none;
            }
            .label {
                border-width: 2px;
                box-shadow: none;
                margin: 0;
                height: 5.98in;
                padding: 0.19in 0.16in 0.15in;
                page-break-after: avoid;
                page-break-inside: avoid;
                break-after: avoid;
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

    <main class="label">
        <!-- Header: logo + tenant info -->
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

        <!-- Consignee & Account Row (exactly like image) -->
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

        <!-- Shipper / Weight / Tracking / Destination / Type -->
        <section class="ship-details">
            <div class="detail-row">
                <div class="block">
                    <div class="label-text">Shipper:</div>
                    <div class="value">{{ $shipperName }}</div>
                </div>
                <div class="block right">
                    <div class="label-text">{{ $measureLabel }}</div>
                    <div class="value">{{ $weightDisplay }}</div>
                </div>
            </div>

            <div class="detail-row">
                <div class="block">
                    <div class="label-text">Tracking No - Other</div>
                    <div class="value-medium">{{ $trackingOther }}</div>
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

        <!-- Package Contents & Created date -->
        <section class="contents">
            <div class="label-text">Package Contents:</div>
            <div class="value">{{ $packageContents }}</div>

            <div class="created-row">
                <div class="label-text">Created:</div>
                <div class="value">{{ $createdDate }}</div>
            </div>
        </section>

        <div class="rule"></div>

        <!-- Barcode panel with generator and barcode visual -->
        <section class="barcode-panel">
            <div class="generator">{{ $displayGenerator }}</div>
            <div class="barcode">{!! $barcodeSvg !!}</div>
            <div class="barcode-value">{{ $barcodeValue }}</div>
        </section>

        <!-- Extra reference numbers exactly like attached image shows: 000015 and OL-ZX000015 style -->
        <div class="extra-ref">
            <span>{{ $referenceNumber }}</span>
            <span>{{ $olReference }}</span>
        </div>

        <footer class="footer-credit">powered by freitora.com</footer>
    </main>

    <script>
        document.title = 'label-{{ preg_replace('/[^A-Za-z0-9_-]+/', '-', $barcodeValue) }}';
    </script>
</body>
</html>
