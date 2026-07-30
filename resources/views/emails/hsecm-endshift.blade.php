<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Pertengahan Shift — Monitoring & Intervensi</title>
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
  $slotLabel = $batchSlotLabel !== '' ? $batchSlotLabel : 'slot pertengahan shift';
  $monitoringLink = trim((string) ($monitoringUrl ?? ''));
  $tasklistLink = trim((string) ($tasklistUrl ?? ''));
  if ($tasklistLink === '') {
      $tasklistLink = (string) ($dashboardUrl ?? '');
  }
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f4;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border:1px solid #fde68a;border-radius:16px;overflow:hidden;">
        <tr>
          <td style="background:linear-gradient(135deg,#b45309 0%,#92400e 100%);padding:22px 28px;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.85);font-weight:700;">Pertengahan Shift — Monitoring &amp; Intervensi</div>
            <div style="margin-top:6px;font-size:13px;color:rgba(255,255,255,.9);">{{ $siteLabel }} · {{ $companyLabel }} · Batch {{ $slotLabel }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            <p style="margin:0 0 10px;font-size:15px;color:#0f172a;">Yth. <strong>{{ $nama }}</strong></p>
            <p style="margin:0 0 18px;font-size:13px;line-height:1.65;color:#475569;">
              {{ $role }} · Berikut ringkasan highlight gap untuk scope site &amp; perusahaan Anda sebagai
              <strong>Monitoring &amp; Intervensi</strong> berdasarkan kondisi pada <strong>pertengahan shift</strong> yang sedang berjalan.
            </p>

            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#334155;">
              Berikut kami sampaikan exposure pada pertengahan shift saat ini:
            </p>
            @foreach($exposure as $i => $section)
              @include('emails.partials.hsecm-section-detail', ['number' => $i + 1, 'section' => $section])
            @endforeach

            <p style="margin:22px 0 12px;font-size:13px;line-height:1.6;color:#334155;">
              Berikut kami sampaikan gap yang menjadi concern agar segera ditindaklanjuti pada pertengahan shift (sebelum akhir shift):
            </p>
            @foreach($gaps as $i => $section)
              @include('emails.partials.hsecm-section-detail', ['number' => $i + 1, 'section' => $section])
            @endforeach

            @if($monitoringLink !== '')
            <p style="margin:22px 0 10px;font-size:13px;line-height:1.65;color:#334155;">
              Detail data secara overall dapat diakses pada <strong>Dashboard</strong> berikut:
            </p>
            <p style="margin:0 0 14px;">
              <a href="{{ $monitoringLink }}" target="_blank" rel="noopener" style="color:#b45309;font-weight:700;word-break:break-all;">{{ $monitoringLink }}</a>
            </p>
            @endif

            @if($tasklistLink !== '')
            <p style="margin:16px 0 10px;font-size:13px;line-height:1.65;color:#334155;">
              Silakan buka <strong>Tasklist</strong> untuk scope
              <strong>{{ $siteLabel }} · {{ $companyLabel }}</strong>
              di bawah untuk menindaklanjuti gap (upload evidence &amp; submit perbaikan):
            </p>
            <p style="margin:0 0 18px;">
              <a href="{{ $tasklistLink }}" target="_blank" rel="noopener" style="color:#b45309;font-weight:700;word-break:break-all;">{{ $tasklistLink }}</a>
            </p>
            @endif

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;">
              @if($monitoringLink !== '')
              <tr>
                <td align="center" style="padding-bottom:10px;">
                  <a href="{{ $monitoringLink }}" class="btn-secondary" target="_blank" rel="noopener">Buka Dashboard</a>
                </td>
              </tr>
              @endif
              @if($tasklistLink !== '')
              <tr>
                <td align="center">
                  <a href="{{ $tasklistLink }}" class="btn" target="_blank" rel="noopener">{{ $ctaLabel ?? ('Buka Tasklist — '.$siteLabel.' · '.$companyLabel) }}</a>
                </td>
              </tr>
              @endif
            </table>
            <p style="margin:0;font-size:13px;line-height:1.65;color:#334155;">
              Mohon setiap point dari gap yang muncul di atas dapat dikontrol dan ditindaklanjuti melalui tasklist
              agar tidak terjadi perulangan terhadap gap yang sama hingga akhir shift maupun shift berikutnya.
            </p>
            <p style="margin:20px 0 0;font-size:11px;color:#94a3b8;text-align:center;">Email digenerate otomatis pada {{ $generatedAt }}.</p>
          </td>
        </tr>
        <tr>
          <td style="background:#0f172a;color:#94a3b8;padding:16px 28px;font-size:11px;line-height:1.6;">
            <strong style="color:#e2e8f0;">Daily Monitoring Dashboard</strong><br/>
            Dikirim otomatis dari sistem monitoring HSE.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
