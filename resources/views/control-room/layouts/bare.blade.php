<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'Absensi') — Control Room</title>
    <link rel="icon" type="image/png" href="{{ asset('wowdash-admin/assets/images/favicon.png') }}" sizes="16x16">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <style>
        :root {
            --ocr-brand: #3952bc;
            --ocr-brand-dark: #2b45af;
            --ocr-brand-soft: rgba(57, 82, 188, 0.08);
            --ocr-ok: #059669;
            --ocr-danger: #dc2626;
            --ocr-ink: #1e293b;
            --ocr-muted: #64748b;
            --ocr-line: #e2e8f0;
            --ocr-surface: #ffffff;
        }
        * { box-sizing: border-box; }
        body.ocr-gf-page {
            margin: 0;
            min-height: 100dvh;
            font-family: Inter, system-ui, sans-serif;
            color: var(--ocr-ink);
            background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 45%, #f1f5f9 100%);
            -webkit-font-smoothing: antialiased;
        }
        .ocr-gf-page .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .ocr-wrap { max-width: 680px; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
        .ocr-hero {
            border-radius: 20px;
            overflow: hidden;
            background: var(--ocr-surface);
            box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 16px 40px -12px rgba(57,82,188,.18);
            margin-bottom: 1rem;
        }
        .ocr-hero-top { height: 6px; background: linear-gradient(90deg, var(--ocr-brand), #72479e); }
        .ocr-hero-body { padding: 1.75rem 1.5rem 1.5rem; }
        .ocr-badge {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
            color: var(--ocr-brand); background: var(--ocr-brand-soft); border-radius: 999px; padding: .35rem .75rem;
        }
        .ocr-hero h1 { margin: .85rem 0 0; font-size: clamp(1.5rem, 4vw, 1.85rem); line-height: 1.2; font-weight: 800; }
        .ocr-lead { margin: .65rem 0 0; color: var(--ocr-muted); font-size: .95rem; line-height: 1.55; }
        .ocr-pill {
            display: inline-flex; align-items: center; gap: .35rem; margin-top: .75rem;
            padding: .4rem .75rem; border-radius: 999px; background: #f1f5f9; font-size: .78rem; font-weight: 600; color: #475569;
        }
        .ocr-steps { display: flex; gap: .5rem; margin: 1.25rem 0 1rem; }
        .ocr-step {
            flex: 1; height: 4px; border-radius: 999px; background: #e2e8f0;
        }
        .ocr-step.is-active, .ocr-step.is-done { background: var(--ocr-brand); }
        .ocr-card {
            background: var(--ocr-surface);
            border: 1px solid var(--ocr-line);
            border-radius: 18px;
            padding: 1.35rem 1.25rem;
            box-shadow: 0 8px 24px -16px rgba(15,23,42,.12);
            margin-bottom: .85rem;
        }
        .ocr-card-title {
            display: flex; align-items: center; gap: .5rem;
            font-size: .95rem; font-weight: 700; margin: 0 0 1rem;
        }
        .ocr-num {
            width: 1.65rem; height: 1.65rem; border-radius: 999px; flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--ocr-brand-soft); color: var(--ocr-brand); font-size: .75rem; font-weight: 800;
        }
        .ocr-gf-page label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: .4rem; color: #334155; }
        .ocr-req { color: var(--ocr-danger); }
        .ocr-hint { font-size: .78rem; color: var(--ocr-muted); margin-top: .35rem; line-height: 1.45; }
        .ocr-field { margin-bottom: 1rem; }
        .ocr-input {
            width: 100%; border: 1.5px solid var(--ocr-line); border-radius: 12px;
            background: #fafbfc; padding: .85rem 1rem; font: inherit; font-size: 16px; min-height: 48px;
        }
        .ocr-input:focus {
            outline: none; border-color: rgba(57,82,188,.45);
            box-shadow: 0 0 0 4px rgba(57,82,188,.12); background: #fff;
        }
        .ocr-input-mono { font-family: ui-monospace, Consolas, monospace; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .ocr-row { display: flex; gap: .65rem; align-items: stretch; }
        .ocr-row .ocr-input { flex: 1; min-width: 0; }
        .ocr-btn {
            border: 0; border-radius: 12px; cursor: pointer; font: inherit; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
        }
        .ocr-btn:disabled { opacity: .55; cursor: not-allowed; }
        .ocr-btn-primary {
            background: linear-gradient(135deg, var(--ocr-brand), var(--ocr-brand-dark));
            color: #fff; padding: .9rem 1.25rem; box-shadow: 0 10px 24px -8px rgba(57,82,188,.55);
        }
        .ocr-btn-secondary {
            background: #f1f5f9; color: #475569; padding: .85rem 1rem; border: 1px solid var(--ocr-line);
        }
        .ocr-btn-block { width: 100%; }
        .ocr-alert { border-radius: 12px; padding: .85rem 1rem; font-size: .88rem; line-height: 1.45; margin-bottom: 1rem; }
        .ocr-alert strong { display: block; }
        .ocr-alert p, .ocr-alert ul { margin: .5rem 0 0; padding: 0; }
        .ocr-alert ul { padding-left: 1.1rem; }
        .ocr-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .ocr-alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .ocr-alert-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .ocr-preview {
            display: none;
            border-radius: 14px; background: #f8fafc; border: 1px dashed #cbd5e1;
            padding: 1rem; margin-top: .75rem;
        }
        .ocr-preview.is-visible { display: block; }
        .ocr-preview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; font-size: .86rem; }
        .ocr-preview-grid span { display: block; color: var(--ocr-muted); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .ocr-preview-grid strong { display: block; margin-top: .2rem; font-weight: 600; color: var(--ocr-ink); }
        .ocr-dropzone {
            border: 2px dashed #cbd5e1; border-radius: 16px; background: #fafbfc;
            padding: 1.5rem 1rem; text-align: center; cursor: pointer;
        }
        .ocr-dropzone.is-dragover { border-color: var(--ocr-brand); background: var(--ocr-brand-soft); }
        .ocr-dropzone.has-file { border-style: solid; border-color: rgba(5,150,105,.35); background: #ecfdf5; }
        .ocr-dropzone.is-disabled { opacity: .55; cursor: not-allowed; pointer-events: none; }
        .ocr-dropzone-icon { font-size: 2rem !important; color: var(--ocr-brand); }
        .ocr-dropzone-title { margin: .5rem 0 0; font-weight: 700; font-size: .95rem; }
        .ocr-dropzone-sub { margin: .25rem 0 0; color: var(--ocr-muted); font-size: .82rem; }
        .ocr-file-name { margin-top: .65rem; font-size: .82rem; font-weight: 600; color: var(--ocr-ok); word-break: break-all; }
        .ocr-thumb { display: block; width: 100%; max-height: 220px; margin-top: .85rem; object-fit: cover; border-radius: 12px; border: 1px solid var(--ocr-line); }
        .ocr-thumb[hidden], .ocr-camera[hidden], .ocr-alert[hidden] { display: none !important; }
        .ocr-camera { margin-top: .85rem; padding: .75rem; border-radius: 14px; background: #0f172a; }
        .ocr-camera video { display: block; width: 100%; max-height: 280px; border-radius: 10px; background: #020617; }
        .ocr-camera-bar { display: flex; gap: .5rem; margin-top: .75rem; }
        .ocr-camera-bar .ocr-btn { flex: 1; }
        .ocr-camera-status { margin: .5rem 0 0; color: #cbd5e1; font-size: .78rem; }
        .ocr-btn-row { display: flex; gap: .65rem; margin-top: .85rem; }
        .ocr-btn-row .ocr-btn { flex: 1; min-height: 44px; }
        .ocr-footer { text-align: center; color: var(--ocr-muted); font-size: .78rem; margin-top: 1.25rem; line-height: 1.5; }
        .ocr-roster-group { margin-top: 1rem; }
        .ocr-roster-group h3 { margin: 0 0 .5rem; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--ocr-muted); }
        .ocr-roster-group ul { list-style: none; margin: 0; padding: 0; border-top: 1px solid var(--ocr-line); }
        .ocr-roster-group li { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; padding: .7rem 0; border-bottom: 1px solid var(--ocr-line); }
        .ocr-roster-name { font-size: .9rem; font-weight: 600; color: var(--ocr-ink); }
        .ocr-roster-sid { font-size: .75rem; font-weight: 700; letter-spacing: .04em; color: var(--ocr-muted); }
        .ocr-roster-site {
            display: inline-block; margin-left: .35rem; font-size: .68rem; font-weight: 700;
            letter-spacing: .04em; color: var(--ocr-brand); background: var(--ocr-brand-soft);
            border-radius: 999px; padding: .1rem .45rem;
        }
        .ocr-replace-list { display: flex; flex-direction: column; gap: .5rem; }
        .ocr-replace-option {
            display: flex; align-items: flex-start; gap: .65rem;
            border: 1.5px solid var(--ocr-line); border-radius: 12px; padding: .75rem .85rem;
            cursor: pointer; background: #fafbfc;
        }
        .ocr-replace-option:has(input:checked) {
            border-color: rgba(57,82,188,.45); background: var(--ocr-brand-soft);
        }
        .ocr-replace-option input { margin-top: .2rem; width: 1.1rem; height: 1.1rem; }
        .ocr-replace-option strong { display: block; font-size: .9rem; }
        .ocr-replace-option small { display: block; margin-top: .15rem; color: var(--ocr-muted); font-size: .75rem; }
        #btn-pengganti.is-active {
            background: var(--ocr-brand-soft); color: var(--ocr-brand); border-color: rgba(57,82,188,.35);
        }
        .ocr-sr {
            position: absolute !important; width: 1px !important; height: 1px !important;
            padding: 0 !important; margin: -1px !important; overflow: hidden !important;
            clip: rect(0,0,0,0) !important; white-space: nowrap !important; border: 0 !important;
        }
        @media (max-width: 520px) {
            .ocr-preview-grid { grid-template-columns: 1fr; }
            .ocr-row { flex-direction: column; }
        }
    </style>
    @stack('styles')
</head>
<body class="ocr-gf-page">
    @yield('content')
    @stack('scripts')
</body>
</html>
