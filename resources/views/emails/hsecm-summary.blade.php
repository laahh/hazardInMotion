<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>HSECM Monitoring Summary</title>
  <!--[if mso]>
  <style type="text/css">
    table { border-collapse: collapse; }
    .kpi-value { font-size: 22px !important; }
  </style>
  <![endif]-->
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    body { margin: 0; padding: 0; width: 100% !important; background-color: #ecf3f2; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
    .wrapper { width: 100%; background-color: #ecf3f2; }
    .container { max-width: 640px; margin: 0 auto; }
    .pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
    .pill-teal { background: rgba(255,255,255,0.18); color: #ffffff; }
    .kpi-card { background: #ffffff; border: 1px solid #d7e7e4; border-radius: 12px; padding: 14px 12px; }
    .kpi-label { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #64748b; margin: 0 0 6px; }
    .kpi-value { font-size: 22px; font-weight: 800; color: #0f766e; margin: 0; line-height: 1.2; }
    .kpi-hint { font-size: 11px; color: #94a3b8; margin: 6px 0 0; }
    .tone-success .kpi-value { color: #047857; }
    .tone-warning .kpi-value { color: #b45309; }
    .tone-danger .kpi-value { color: #b91c1c; }
    .data-table th { background: #f0fdfa; color: #0f766e; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; padding: 10px 12px; text-align: left; border-bottom: 1px solid #ccfbf1; }
    .data-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
    .data-table tr:last-child td { border-bottom: none; }
    .btn { display: inline-block; background: #0f766e; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px 22px; border-radius: 10px; }
    @media only screen and (max-width: 620px) {
      .kpi-col { display: block !important; width: 100% !important; }
      .kpi-spacer { display: none !important; height: 0 !important; }
    }
  </style>
</head>
<body>
@php
  $siteLabel = ($scope['site'] ?? '') !== '' ? $scope['site'] : 'Semua Site';
  $companyLabel = ($scope['perusahaan'] ?? '') !== '' ? $scope['perusahaan'] : 'Semua Perusahaan';
  $periodParts = array_filter([
      ($scope['week'] ?? '') !== '' ? 'Week '.$scope['week'] : null,
      ($scope['year'] ?? '') !== '' ? 'Year '.$scope['year'] : null,
  ]);
  $periodLabel = $periodParts !== [] ? implode(' · ', $periodParts) : 'Semua periode';
  $nama = $recipient['nama'] ?? 'Bapak/Ibu';
  $role = $recipient['role'] ?? '-';
@endphp

<table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td align="center" style="padding: 28px 14px;">
      <table role="presentation" class="container" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;">

        {{-- Header --}}
        <tr>
          <td style="background: linear-gradient(135deg, #0f766e 0%, #115e59 55%, #134e4a 100%); border-radius: 18px 18px 0 0; padding: 28px 28px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>
                  <span class="pill pill-teal">HSECM MONITORING</span>
                  <h1 style="margin: 14px 0 6px; font-size: 24px; line-height: 1.25; color: #ffffff; font-weight: 800;">
                    Weekly Performance Summary
                  </h1>
                  <p style="margin: 0; color: rgba(255,255,255,0.82); font-size: 13px;">
                    {{ $siteLabel }} &nbsp;·&nbsp; {{ $companyLabel }} &nbsp;·&nbsp; {{ $periodLabel }}
                  </p>
                </td>
                <td align="right" valign="top" style="width: 84px;">
                  <div style="width:72px;height:72px;border-radius:16px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);text-align:center;line-height:72px;color:#ffffff;font-weight:800;font-size:18px;">
                    HSE
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Body --}}
        <tr>
          <td style="background:#ffffff; padding: 28px; border-left:1px solid #d7e7e4; border-right:1px solid #d7e7e4;">
            <p style="margin:0 0 6px; font-size:15px; color:#0f172a;">
              Yth. <strong>{{ $nama }}</strong>
            </p>
            <p style="margin:0 0 18px; font-size:13px; color:#64748b;">
              {{ $role }} · Berikut ringkasan indikator HSECM untuk scope site &amp; perusahaan Anda.
            </p>

            {{-- Scope bar --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px; background:#f0fdfa; border:1px solid #ccfbf1; border-radius:12px;">
              <tr>
                <td style="padding:14px 16px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td width="33%" style="vertical-align:top;">
                        <div style="font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#0f766e;">Site</div>
                        <div style="font-size:14px;font-weight:700;color:#134e4a;margin-top:4px;">{{ $siteLabel }}</div>
                      </td>
                      <td width="34%" style="vertical-align:top;">
                        <div style="font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#0f766e;">Perusahaan</div>
                        <div style="font-size:14px;font-weight:700;color:#134e4a;margin-top:4px;">{{ $companyLabel }}</div>
                      </td>
                      <td width="33%" style="vertical-align:top;">
                        <div style="font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#0f766e;">Total Record</div>
                        <div style="font-size:14px;font-weight:700;color:#134e4a;margin-top:4px;">{{ number_format($totalRecords) }}</div>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <p style="margin:0 0 12px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#0f766e;">
              Key Performance Indicators
            </p>

            {{-- KPI grid --}}
            @foreach(array_chunk($kpis, 2) as $chunk)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px;">
              <tr>
                @foreach($chunk as $i => $kpi)
                <td class="kpi-col" width="50%" valign="top" style="{{ $i === 1 ? 'padding-left:5px;' : 'padding-right:5px;' }}">
                  <div class="kpi-card tone-{{ $kpi['tone'] ?? 'primary' }}">
                    <p class="kpi-label">{{ $kpi['label'] }}</p>
                    <p class="kpi-value">{{ $kpi['value'] }}</p>
                    @if(!empty($kpi['hint']))
                    <p class="kpi-hint">{{ $kpi['hint'] }}</p>
                    @endif
                  </div>
                </td>
                @if(count($chunk) === 1)
                <td class="kpi-spacer" width="50%">&nbsp;</td>
                @endif
                @endforeach
              </tr>
            </table>
            @endforeach

            <p style="margin:22px 0 12px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#0f766e;">
              Dataset Snapshot
            </p>

            <table role="presentation" class="data-table" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
              <thead>
                <tr>
                  <th>Dataset</th>
                  <th style="text-align:right;">Records</th>
                </tr>
              </thead>
              <tbody>
                @foreach($datasets as $dataset)
                <tr>
                  <td>{{ $dataset['label'] }}</td>
                  <td style="text-align:right; font-weight:700; color:#0f172a;">{{ number_format((int) $dataset['count']) }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:26px;">
              <tr>
                <td align="center">
                  <a href="{{ $dashboardUrl }}" class="btn" target="_blank" rel="noopener">
                    Buka HSECM Dashboard
                  </a>
                </td>
              </tr>
            </table>

            <p style="margin:20px 0 0; font-size:12px; color:#94a3b8; text-align:center; line-height:1.6;">
              Email ini digenerate otomatis pada {{ $generatedAt }}.<br/>
              Mohon ditindaklanjuti area yang masih berstatus overdue / blindspot / coverage rendah.
            </p>
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#0f172a; color:#94a3b8; border-radius:0 0 18px 18px; padding:18px 28px; font-size:11px; line-height:1.6; border-left:1px solid #0f172a; border-right:1px solid #0f172a;">
            <strong style="color:#e2e8f0;">HSECM Monitoring Dashboard</strong><br/>
            Dikirim otomatis dari sistem monitoring HSE. Jangan balas email ini bila tidak relevan.
            <div style="margin-top:8px; color:#64748b;">&copy; {{ date('Y') }} PT. Berau Coal — HSE Automation</div>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
