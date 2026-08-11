<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Tasklist Submitted</title>
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
    body { margin: 0; padding: 0; width: 100% !important; background-color: #f0fdfa; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
    .btn { display: inline-block; background: #0f766e; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 22px; border-radius: 10px; }
  </style>
</head>
<body>
@php
  $siteLabel = ($scope['site'] ?? '') !== '' ? $scope['site'] : 'Semua Site';
  $companyLabel = ($scope['perusahaan'] ?? '') !== '' ? $scope['perusahaan'] : 'Semua Perusahaan';
  $batchLabel = trim((string) ($scope['batch_slot'] ?? ''));
  $nama = $recipient['nama'] ?? 'Bapak/Ibu';
  $role = trim((string) ($recipient['role'] ?? '')) !== '' ? $recipient['role'] : 'SOD / Penanggung Jawab';
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdfa;">
  <tr>
    <td align="center" style="padding:24px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #99f6e4;border-radius:16px;overflow:hidden;">
        <tr>
          <td style="background:linear-gradient(135deg,#0f766e 0%,#115e59 100%);padding:22px 28px;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.85);font-weight:700;">Tasklist Submitted</div>
            <div style="margin-top:6px;font-size:13px;color:rgba(255,255,255,.9);">{{ $siteLabel }} · {{ $companyLabel }}</div>
          </td>
        </tr>
        <tr>
          <td style="padding:28px;">
            <p style="margin:0 0 10px;font-size:15px;color:#0f172a;">Yth. <strong>{{ $nama }}</strong></p>
            <p style="margin:0 0 18px;font-size:13px;line-height:1.65;color:#475569;">
              {{ $role }} · Komitmen perbaikan pada tasklist Monitoring &amp; Intervensi telah <strong>berhasil di-submit</strong>
              dan menunggu ACC dari HSE.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px;background:#f0fdfa;border-radius:10px;border:1px solid #99f6e4;">
              <tr>
                <td style="padding:14px 16px;font-size:12px;color:#115e59;line-height:1.7;">
                  @if($batchLabel !== '')
                    Batch: <strong>{{ $batchLabel }}</strong><br/>
                  @endif
                  Pengirim: <strong>{{ $submittedByName !== '' ? $submittedByName : '—' }}</strong><br/>
                  Jumlah item di-submit: <strong>{{ number_format($itemCount) }}</strong>
                </td>
              </tr>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0;">
              <tr>
                <td align="center">
                  <a href="{{ $tasklistUrl }}" class="btn" target="_blank" rel="noopener">Buka Tasklist</a>
                </td>
              </tr>
            </table>
            <p style="margin:0;font-size:12px;color:#64748b;word-break:break-all;">{{ $tasklistUrl }}</p>
            <p style="margin:20px 0 0;font-size:11px;color:#94a3b8;text-align:center;">Email digenerate otomatis pada {{ $generatedAt }}.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
