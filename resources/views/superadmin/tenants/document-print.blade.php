<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->company_name }} Registration Document</title>
    <style>
        html, body { margin: 0; height: 100%; font-family: Arial, sans-serif; background: #f8fafc; }
        iframe { width: 100%; height: 100vh; border: 0; background: #fff; }
        .fallback { padding: 18px; }
    </style>
</head>
<body>
    <iframe src="{{ route('superadmin.tenants.onboarding-documents.view', [$tenant, $field, $index]) }}"></iframe>
    <noscript>
        <div class="fallback">
            <a href="{{ route('superadmin.tenants.onboarding-documents.view', [$tenant, $field, $index]) }}">Open document</a>
        </div>
    </noscript>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 350);
        });
    </script>
</body>
</html>
