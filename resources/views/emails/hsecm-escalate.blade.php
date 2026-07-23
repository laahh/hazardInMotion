<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Escalate Tasklist</title>
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
    body { margin: 0; padding: 0; width: 100% !important; background-color: #fef2f2; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
    .scroll-box { max-height: 220px; overflow-y: auto; overflow-x: auto; border: 1px solid #fecaca; border-radius: 10px; background: #ffffff; }
    .detail-table th { background: #fee2e2; color: #991b1b; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; padding: 8px 10px; text-align: left; border-bottom: 1px solid #fecaca; white-space: nowrap; }
    .detail-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 12px; color: #334155; vertical-align: top; }
    .btn { display: inline-block; background: #b91c1c; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 22px; border-radius: 10px; }
  </style>
</head>
<body>
@php
  $siteLabel = ($scope['site'] ?? '') !== '' ? $scope['site'] : 'Semua Site';
  $companyLabel = ($scope['perusahaan'] ?? '') !== '' ? $scope['perusahaan'] : 'Semua Perusahaan';
  $nama = $recipient['nama'] ?? 'Bapak/Ibu';
  $role = trim((string) ($recipient['role'] ?? '')) !== '' ? $recipient['role'] : 'PENANGGUNG JAWAB OPERASIONAL';
  $exposure = collect($emailNarrative['exposure'] ?? [])
      ->filter(static fn (array $s): bool => (bool) ($s['available'] ?? true))
      ->values()
      ->all();
  $gaps = collect($emailNarrative['gaps'] ?? [])
      ->filter(static fn (array $s): bool => (bool) ($s['available'] ?? true))
      ->values()
      ->all();
  $counts = $emailNarrative['escalate_counts'] ?? [];
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border:1px solid #fecaca;border-radius:16px;overflow:hidden;">
        <tr>
          <td style="background:linear-gradient(135deg,#b91c1c 0%,#7f1d1d 100%);padding:22px 28px;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.85);font-weight:700;">Escalate #{{ $escalateCount }} — Tasklist belum closed</div>
            <div style="margin-top:6px;font-size:13px;color:rgba(255,255,255,.9);">{{ $siteLabel }} · {{ $companyLabel }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            <p style="margin:0 0 10px;font-size:15px;color:#0f172a;">Yth. <strong>{{ $nama }}</strong></p>
            <p style="margin:0 0 18px;font-size:13px;line-height:1.65;color:#475569;">
              {{ $role }} · Tasklist Monitoring &amp; Intervensi untuk scope Anda <strong>belum closed</strong>.
              Mohon segera selesaikan item yang masih open / ditolak, atau pastikan submit sudah di-ACC HSE.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;background:#fef2f2;border-radius:10px;">
              <tr>
                <td style="padding:12px 14px;font-size:12px;color:#7f1d1d;">
                  Open: <strong>{{ (int) ($counts['open'] ?? 0) }}</strong> ·
                  Submitted: <strong>{{ (int) ($counts['submitted'] ?? 0) }}</strong> ·
                  Rejected: <strong>{{ (int) ($counts['rejected'] ?? 0) }}</strong> ·
                  Approved: <strong>{{ (int) ($counts['approved'] ?? 0) }}</strong>
                </td>
              </tr>
            </table>

            @foreach($gaps as $i => $section)
              @include('emails.partials.hsecm-section-detail', ['number' => $i + 1, 'section' => $section])
            @endforeach

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;">
              <tr>
                <td align="center">
                  <a href="{{ $dashboardUrl }}" class="btn" target="_blank" rel="noopener">Buka Tasklist</a>
                </td>
              </tr>
            </table>
            <p style="margin:0;font-size:12px;color:#64748b;word-break:break-all;">{{ $dashboardUrl }}</p>
            <p style="margin:20px 0 0;font-size:11px;color:#94a3b8;text-align:center;">Email digenerate otomatis pada {{ $generatedAt }}.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
