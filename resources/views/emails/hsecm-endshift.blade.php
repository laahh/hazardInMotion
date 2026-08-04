<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Akhir Shift — Tasklist Perbaikan</title>
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
    body { margin: 0; padding: 0; width: 100% !important; background-color: #f1f5f4; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
    .scroll-box { max-height: 220px; overflow-y: auto; overflow-x: auto; border: 1px solid #d7e7e4; border-radius: 10px; background: #ffffff; }
    .detail-table th { background: #fef3c7; color: #92400e; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; padding: 8px 10px; text-align: left; border-bottom: 1px solid #fde68a; white-space: nowrap; }
    .detail-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 12px; color: #334155; vertical-align: top; }
    .detail-table tr:last-child td { border-bottom: none; }
    .btn { display: inline-block; background: #b45309; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 22px; border-radius: 10px; }
    .btn-secondary { display: inline-block; background: #92400e; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 22px; border-radius: 10px; }
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
  $slotLabel = $batchSlotLabel !== '' ? $batchSlotLabel : 'slot akhir shift';
  $monitoringLink = trim((string) ($monitoringUrl ?? ''));
  $tasklistLink = trim((string) ($tasklistUrl ?? ''));
  // Hanya terima URL token tasklist — jangan pakai fallback Aksi PJO / dashboard.
  $hasTokenTasklist = $tasklistLink !== ''
      && str_contains($tasklistLink, '/hsecm/tasklist/')
      && ! str_contains($tasklistLink, '/hsecm/pjo-action')
      && ! str_contains($tasklistLink, '/hsecm/tasklist/open');
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f4;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border:1px solid #fde68a;border-radius:16px;overflow:hidden;">
        <tr>
          <td style="background:linear-gradient(135deg,#b45309 0%,#92400e 100%);padding:22px 28px;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.85);font-weight:700;">Akhir Shift — Tasklist Perbaikan</div>
            <div style="margin-top:6px;font-size:13px;color:rgba(255,255,255,.9);">{{ $siteLabel }} · {{ $companyLabel }} · Batch {{ $slotLabel }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            <p style="margin:0 0 10px;font-size:15px;color:#0f172a;">Yth. <strong>{{ $nama }}</strong></p>
            <p style="margin:0 0 18px;font-size:13px;line-height:1.65;color:#475569;">
              {{ $role }} · Shift telah <strong>berakhir</strong>. Berikut ringkasan gap untuk scope site &amp; perusahaan Anda
              yang <strong>wajib diperbaiki</strong> melalui Tasklist (upload evidence &amp; submit).
            </p>

            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#334155;">
              Berikut exposure pada kondisi <strong>akhir shift</strong>:
            </p>
            @foreach($exposure as $i => $section)
              @include('emails.partials.hsecm-section-detail', ['number' => $i + 1, 'section' => $section])
            @endforeach

            <p style="margin:22px 0 12px;font-size:13px;line-height:1.6;color:#334155;">
              Berikut gap yang menjadi concern dan <strong>harus ditindaklanjuti</strong> setelah akhir shift:
            </p>
            @foreach($gaps as $i => $section)
              @include('emails.partials.hsecm-section-detail', ['number' => $i + 1, 'section' => $section])
            @endforeach

            @if($hasTokenTasklist)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 18px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;">
              <tr>
                <td style="padding:16px 18px;">
                  <p style="margin:0 0 8px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#92400e;font-weight:700;">
                    Wajib — Buka Tasklist
                  </p>
                  <p style="margin:0 0 12px;font-size:13px;line-height:1.65;color:#334155;">
                    Klik tombol atau link di bawah. Anda akan diarahkan ke halaman
                    <strong>inputasi Tasklist</strong> untuk mengisi tindakan perbaikan,
                    upload evidence, lalu submit untuk scope
                    <strong>{{ $siteLabel }} · {{ $companyLabel }}</strong>.
                  </p>
                  <p style="margin:0 0 14px;text-align:center;">
                    <a href="{{ $tasklistLink }}" class="btn" target="_blank" rel="noopener">{{ $ctaLabel ?? ('Buka Tasklist — '.$siteLabel.' · '.$companyLabel) }}</a>
                  </p>
                  <p style="margin:0;">
                    <a href="{{ $tasklistLink }}" target="_blank" rel="noopener" style="color:#b45309;font-weight:700;word-break:break-all;font-size:12px;">{{ $tasklistLink }}</a>
                  </p>
                </td>
              </tr>
            </table>
            @endif

            @if($monitoringLink !== '')
            <p style="margin:0 0 10px;font-size:13px;line-height:1.65;color:#334155;">
              Detail data overall (opsional) dapat dilihat di Dashboard:
            </p>
            <p style="margin:0 0 14px;">
              <a href="{{ $monitoringLink }}" target="_blank" rel="noopener" style="color:#b45309;font-weight:700;word-break:break-all;">{{ $monitoringLink }}</a>
            </p>
            <p style="margin:0 0 18px;text-align:center;">
              <a href="{{ $monitoringLink }}" class="btn-secondary" target="_blank" rel="noopener">Buka Dashboard</a>
            </p>
            @endif

            <p style="margin:0;font-size:13px;line-height:1.65;color:#334155;">
              Mohon setiap point gap di atas dikontrol dan diselesaikan melalui Tasklist
              agar tidak berulang pada shift berikutnya.
            </p>
            <p style="margin:20px 0 0;font-size:11px;color:#94a3b8;text-align:center;">Email digenerate otomatis pada {{ $generatedAt }}.</p>
          </td>
        </tr>
        <tr>
          <td style="background:#0f172a;color:#94a3b8;padding:16px 28px;font-size:11px;line-height:1.6;">
            <strong style="color:#e2e8f0;">HSECM — Akhir Shift</strong><br/>
            Dikirim otomatis dari sistem monitoring HSE. Link Tasklist adalah pintu utama untuk inputasi perbaikan.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
