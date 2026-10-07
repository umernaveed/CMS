<style>
    .receipt-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .receipt-sheet {
        max-width: 1180px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid #dbe3ee;
        border-radius: 16px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
        color: #0f172a;
        overflow: hidden;
        font-size: 14px;
    }
    .receipt-inner { padding: 0; }
    .receipt-header {
        display: grid;
        grid-template-columns: .9fr .9fr 1.45fr;
        background: #f7f9fc;
        border-bottom: 1px solid #dbe3ee;
        padding: 28px;
    }
    .receipt-header-column {
        min-width: 0;
        padding: 0 22px;
        border-right: 1px solid #dbe3ee;
    }
    .receipt-header-column:first-child { padding-left: 0; }
    .receipt-header-column:last-child {
        padding-right: 0;
        border-right: 0;
    }
    .receipt-brand {
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }
    .receipt-logo {
        width: 54px;
        height: 54px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        background: #2563eb;
        color: #fff;
        font-size: 18px;
        font-weight: 900;
        overflow: hidden;
        flex: 0 0 auto;
    }
    .receipt-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #fff;
        padding: 5px;
    }
    .receipt-company h2 {
        margin: 0 0 10px;
        color: #0f172a;
        font-size: 26px;
        line-height: 1.05;
        font-weight: 900;
    }
    .receipt-kicker {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .receipt-muted { color: #52647c; }
    .receipt-small { font-size: 13px; line-height: 1.45; }
    .receipt-billed-name {
        font-size: 16px;
        font-weight: 900;
        margin-bottom: 4px;
    }
    .receipt-invoice-meta {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 180px;
        gap: 18px;
        align-items: start;
    }
    .receipt-meta-line,
    .receipt-summary-line {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding: 4px 0;
    }
    .receipt-meta-line span,
    .receipt-summary-line span { color: #52647c; }
    .receipt-meta-line strong,
    .receipt-summary-line strong {
        color: #0f172a;
        text-align: right;
    }
    .receipt-barcode {
        width: 180px;
        margin-left: auto;
    }
    .receipt-barcode svg { display: block; width: 100%; }
    .receipt-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 4px 12px;
        border-radius: 999px;
        border: 1px solid currentColor;
        font-size: 12px;
        font-weight: 900;
        text-transform: capitalize;
        margin-top: 8px;
        float: right;
    }
    .receipt-status.paid { background: #ecfdf5; color: #15803d; }
    .receipt-status.due { background: #fffbeb; color: #b45309; }
    .receipt-status.cancelled { background: #fef2f2; color: #b91c1c; }
    .receipt-body { padding: 28px; }
    .receipt-section { margin-top: 24px; }
    .receipt-section:first-child { margin-top: 0; }
    .receipt-section-title {
        margin: 0 0 10px;
        padding-bottom: 8px;
        border-bottom: 1px solid #dbe3ee;
        color: #0f172a;
        font-size: 17px;
        font-weight: 900;
    }
    .receipt-table-wrap { overflow-x: auto; }
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }
    .receipt-table th {
        background: #f3f6fa;
        color: #334155;
        border-top: 1px solid #dbe3ee;
        border-bottom: 1px solid #dbe3ee;
        font-size: 12px;
        font-weight: 800;
        padding: 9px 10px;
        white-space: nowrap;
    }
    .receipt-table td {
        border-bottom: 1px solid #e6ebf2;
        padding: 10px;
        vertical-align: top;
    }
    .receipt-table .receipt-description {
        min-width: 150px;
        color: #334155;
    }
    .receipt-charge-breakdown {
        color: #64748b;
        font-size: 12px;
        margin-top: 2px;
    }
    .receipt-empty {
        text-align: center;
        color: #64748b;
        padding: 24px !important;
    }
    .receipt-bottom-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(320px, 1fr);
        gap: 26px;
        margin-top: 22px;
        align-items: stretch;
    }
    .receipt-note-block,
    .receipt-summary {
        border: 1px solid #dbe3ee;
        border-radius: 12px;
        background: #f8fafc;
        padding: 16px;
    }
    .receipt-note-block + .receipt-note-block { margin-top: 12px; }
    .receipt-note-block h3,
    .receipt-summary-title {
        color: #0f172a;
        font-size: 16px;
        font-weight: 900;
        margin: 0 0 8px;
    }
    .receipt-note-block p {
        color: #1e293b;
        line-height: 1.35;
        margin: 0;
        white-space: pre-line;
    }
    .receipt-summary { background: #f8fafc; }
    .receipt-grand {
        background: #2864e8;
        border-radius: 10px;
        color: #fff;
        margin-top: 10px;
        padding: 12px 14px;
        font-size: 16px;
        font-weight: 900;
    }
    .receipt-grand span,
    .receipt-grand strong { color: #fff; }
    .receipt-footer {
        padding: 16px 28px;
        background: #f7f9fc;
        border-top: 1px solid #dbe3ee;
        text-align: center;
        color: #64748b;
        font-size: 12px;
    }
    .text-end { text-align: right; }
    @media (max-width: 900px) {
        .receipt-header { grid-template-columns: 1fr; gap: 20px; }
        .receipt-header-column {
            border-right: 0;
            border-bottom: 1px solid #dbe3ee;
            padding: 0 0 18px;
        }
        .receipt-header-column:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .receipt-bottom-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
        .receipt-header,
        .receipt-body { padding: 18px; }
        .receipt-invoice-meta { grid-template-columns: 1fr; }
        .receipt-barcode { margin-left: 0; }
    }
    @media print {
        @page { size: A4; margin: 8mm; }
        .sidebar, .topbar, .sidebar-mobile-toggle, .receipt-actions, .alert, .app-alert-stack { display: none !important; }
        .content-wrap { margin-left: 0 !important; }
        .page-pad { padding: 0 !important; }
        html, body {
            background: #fff !important;
            width: auto;
            min-height: auto;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            font-size: 9px;
            line-height: 1.25;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .receipt-sheet {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            max-width: none;
            width: 100%;
            overflow: visible;
        }
        .receipt-header { padding: 14px; grid-template-columns: .85fr .8fr 1.35fr; }
        .receipt-header-column { padding: 0 10px; }
        .receipt-company h2 { font-size: 17px; }
        .receipt-logo { width: 40px; height: 40px; }
        .receipt-kicker { font-size: 8px; margin-bottom: 5px; }
        .receipt-small { font-size: 8.5px; }
        .receipt-invoice-meta { grid-template-columns: 1fr 125px; gap: 8px; }
        .receipt-barcode { width: 125px; }
        .receipt-status { min-height: 20px; padding: 2px 8px; font-size: 8px; }
        .receipt-body { padding: 14px; }
        .receipt-section { margin-top: 12px; page-break-inside: avoid; }
        .receipt-section-title { font-size: 11px; margin-bottom: 5px; padding-bottom: 4px; }
        .receipt-table { table-layout: fixed; }
        .receipt-table thead { display: table-header-group; }
        .receipt-table tr { page-break-inside: avoid; }
        .receipt-table th,
        .receipt-table td { padding: 4px 5px; font-size: 8px; word-break: break-word; }
        .receipt-charge-breakdown { font-size: 7px; }
        .receipt-bottom-grid {
            grid-template-columns: minmax(0, 1.4fr) 250px;
            gap: 12px;
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .receipt-note-block,
        .receipt-summary { border-radius: 5px; padding: 8px; }
        .receipt-note-block h3,
        .receipt-summary-title { font-size: 10px; margin-bottom: 4px; }
        .receipt-grand { border-radius: 4px; padding: 7px; font-size: 10px; }
        .receipt-footer { padding: 8px; font-size: 8px; }
    }
</style>
