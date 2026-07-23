<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Pasca Shift — Monitoring & Intervensi</title>
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
    body { margin: 0; padding: 0; width: 100% !important; background-color: #f1f5f4; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
    .scroll-box { max-height: 220px; overflow-y: auto; overflow-x: auto; border: 1px solid #d7e7e4; border-radius: 10px; background: #ffffff; }
    .detail-table th { background: #fef3c7; color: #92400e; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; padding: 8px 10px; text-align: left; border-bottom: 1px solid #fde68a; white-space: nowrap; }
    .detail-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 12px; color: #334155; vertical-align: top; }
    .detail-table tr:last-child td { border-bottom: none; }
    .btn { display: inline-block; background: #b45309; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 22px; border-radius: 10px; }
  </style>
</head>
<body>
@php
  $siteLabel = ($scope['site'] ?? '') !== '' ? $scope['site'] : 'Semua Site';
  $companyLabel = ($scope['perusahaan'] ?? '') !== '' ? $scope['perusahaan'] : 'Semua Perusahaan';
  $nama = $recipient['nama'] ?? 'Bapak/Ibu';
  $role = trim((string) ($recipient['role'] ?? '')) !== '' ? $recipient['role'] : 'PENANGGUNG JAWAB OPERASIONAL';
  $exposure = $emailNarrative['exposure'] ?? [];
  $gaps = $emailNarrative['gaps'] ?? [];
  $slotLabel = $batchSlotLabel !== '' ? $batchSlotLabel : 'slot akhir shift';
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f4;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border:1px solid #fde68a;border-radius:16px;overflow:hidden;">
        <tr>
          <td style="background:linear-gradient(135deg,#b45309 0%,#92400e 100%);padding:22px 28px;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.85);font-weight:700;">Pasca Shift — Monitoring &amp; Intervensi</div>
            <div style="margin-top:6px;font-size:13px;color:rgba(255,255,255,.9);">{{ $siteLabel }} · {{ $companyLabel }} · Batch {{ $slotLabel }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            <p style="margin:0 0 10px;font-size:15px;color:#0f172a;">Yth. <strong>{{ $nama }}</strong></p>
            <p style="margin:0 0 18px;font-size:13px;line-height:1.65;color:#475569;">
              {{ $role }} · Shift telah berakhir. Berikut item yang <strong>masih open</strong> dibanding tengah shift
              dan wajib ditindaklanjuti melalui tasklist bersama evidence.
            </p>

            @if(count($exposure) > 0)
              <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#334155;">Exposure shift (informasi):</p>
              @foreach($exposure as $i => $section)
                @include('emails.partials.hsecm-section-detail', ['number' => $i + 1, 'section' => $section])
              @endforeach
            @endif

            <p style="margin:22px 0 12px;font-size:13px;line-height:1.6;color:#334155;">
              Gap yang masih open — lakukan aksi per item di tasklist:
            </p>
            @foreach($gaps as $i => $section)
              @include('emails.partials.hsecm-section-detail', ['number' => $i + 1, 'section' => $section])
            @endforeach

            <p style="margin:22px 0 10px;font-size:13px;line-height:1.65;color:#334155;">
              Buka tasklist (shared link untuk scope site &amp; perusahaan Anda), pilih item, isi catatan perbaikan, dan upload evidence:
            </p>
            <p style="margin:0 0 18px;">
              <a href="{{ $dashboardUrl }}" target="_blank" rel="noopener" style="color:#b45309;font-weight:700;word-break:break-all;">{{ $dashboardUrl }}</a>
            </p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;">
              <tr>
                <td align="center">
                  <a href="{{ $dashboardUrl }}" class="btn" target="_blank" rel="noopener">Buka Tasklist &amp; Submit Evidence</a>
                </td>
              </tr>
            </table>
            <p style="margin:0;font-size:13px;line-height:1.65;color:#334155;">
              Setelah submit, status item menjadi <strong>submitted</strong> dan menunggu ACC HSE.
              Reminder otomatis akan dikirim jika tasklist belum closed.
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
