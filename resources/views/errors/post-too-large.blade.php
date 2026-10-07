<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload too large</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#f6f8fb; color:#172033; font-family:Arial, Helvetica, sans-serif; }
        .panel { width:min(620px, calc(100vw - 32px)); background:#fff; border:1px solid #dfe6f0; border-radius:8px; padding:28px; box-shadow:0 18px 48px rgba(23,32,51,.08); }
        .code { color:#b91c1c; font-weight:800; font-size:13px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:10px; }
        h1 { margin:0 0 12px; font-size:28px; line-height:1.2; }
        p { margin:0 0 14px; color:#4b5870; line-height:1.6; }
        .limits { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin:18px 0; }
        .limits div { display:flex; justify-content:space-between; gap:16px; padding:5px 0; color:#334155; }
        .limits strong { color:#0f172a; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:20px; }
        a, button { border:0; border-radius:7px; padding:10px 14px; font-weight:800; text-decoration:none; cursor:pointer; }
        a { background:#135fd2; color:#fff; }
        button { background:#e2e8f0; color:#0f172a; }
    </style>
</head>
<body>
    <main class="panel">
        <div class="code">Request too large</div>
        <h1>Shipment intake files are too large</h1>
        <p>{{ $message }}</p>
        <p>Upload fewer documents at once, compress large scans/photos, or increase the server upload limits.</p>
        <div class="limits">
            <div><span>Total form request limit</span><strong>{{ $postMaxSize }}</strong></div>
            <div><span>Single file upload limit</span><strong>{{ $uploadMaxSize }}</strong></div>
        </div>
        <div class="actions">
            <button type="button" onclick="history.back()">Go Back</button>
            <a href="{{ $previousUrl }}">Return to previous page</a>
        </div>
    </main>
</body>
</html>
