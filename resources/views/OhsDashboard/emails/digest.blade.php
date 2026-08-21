<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; }
        .banner { background: #fef3c7; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
        .kpis td { padding: 8px 12px; background: #f5f8f5; }
        table.data { border-collapse: collapse; width: 100%; margin: 12px 0; font-size: 13px; }
        table.data th, table.data td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        table.data th { background: #27851f; color: #fff; }
        .btn { display: inline-block; background: #27851f; color: #fff !important; padding: 10px 16px; border-radius: 8px; text-decoration: none; }
    </style>
</head>
<body>
    @if($isTest)
        <div class="banner"><strong>TEST EMAIL</strong> — ini bukan digest produksi.</div>
    @endif
    <h2>OHS Portal Overview</h2>
    @php $k = $overview['kpis'] ?? []; $w = $overview['workforceEffectiveness'] ?? []; @endphp
    <table class="kpis">
        <tr>
            <td>Event This Week<br><strong>{{ $k['eventThisWeek'] ?? 0 }}</strong></td>
            <td>Upcoming Event<br><strong>{{ $k['upcomingEvent'] ?? 0 }}</strong></td>
            <td>Leave This Week<br><strong>{{ $k['leaveThisWeek'] ?? 0 }}</strong></td>
            <td>Upcoming Leave<br><strong>{{ $k['upcomingLeave'] ?? 0 }}</strong></td>
            <td>Project Active<br><strong>{{ $k['projectActive'] ?? 0 }}</strong></td>
            <td>Issue Active<br><strong>{{ $k['issueActive'] ?? 0 }}</strong></td>
            <td>Effective Working Days<br><strong>{{ $w['effectiveWorkingPercent'] ?? 0 }}%</strong></td>
        </tr>
    </table>

    <h3>Event Status</h3>
    @foreach(['thisWeek' => 'This Week', 'nextWeek' => 'Next Week', 'nextTwoWeek' => 'Next 2 Week', 'moreThanTwoWeeks' => 'More Than 2 Weeks Ahead'] as $key => $label)
        <h4>{{ $label }}</h4>
        <table class="data">
            <tr><th>Event</th><th>Date</th><th>PIC</th><th>Where</th></tr>
            @forelse(array_slice($overview['eventStatus'][$key] ?? [], 0, $tableLimit) as $event)
                <tr>
                    <td>{{ $event['EventName'] ?? '' }}</td>
                    <td>{{ $event['EventDate'] ?? '' }}</td>
                    <td>{{ $event['PICName'] ?? '' }}</td>
                    <td>{{ $event['Where'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada data.</td></tr>
            @endforelse
        </table>
    @endforeach

    @if($includeLeave)
        <h3>Leave This Week</h3>
        <table class="data">
            <tr><th>Nama</th><th>Tipe</th><th>Start</th><th>End</th><th>Backup</th></tr>
            @forelse(array_slice($overview['leaveStatus']['thisWeek'] ?? [], 0, $tableLimit) as $leave)
                <tr>
                    <td>{{ $leave['EmpName'] ?? '' }}</td>
                    <td>{{ $leave['LeaveType'] ?? '' }}</td>
                    <td>{{ $leave['StartDate'] ?? '' }}</td>
                    <td>{{ $leave['EndDate'] ?? '' }}</td>
                    <td>{{ $leave['BackupEmpName'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada data.</td></tr>
            @endforelse
        </table>
    @endif

    @if($includeTracker)
        <h3>Active Project & Issue</h3>
        <table class="data">
            <tr><th>Type</th><th>Nama</th><th>Status</th><th>Due</th><th>%</th></tr>
            @forelse(array_slice($overview['trackerHighlights'] ?? [], 0, $tableLimit) as $tracker)
                <tr>
                    <td>{{ $tracker['TrackerType'] ?? '' }}</td>
                    <td>{{ $tracker['ProjectIssueName'] ?? '' }}</td>
                    <td>{{ $tracker['EffectiveStatus'] ?? '' }}</td>
                    <td>{{ $tracker['DueDate'] ?? '' }}</td>
                    <td>{{ $tracker['CurrentPercentComplete'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Tidak ada data.</td></tr>
            @endforelse
        </table>
    @endif

    @if($includeLeaderboard)
        <h3>Working Days Effectiveness Top 10</h3>
        <table class="data">
            <tr><th>Nama</th><th>Team</th><th>Leave YTD</th><th>Effective %</th></tr>
            @forelse(array_slice($overview['leaderboard'] ?? [], 0, $leaderboardLimit) as $row)
                <tr>
                    <td>{{ $row['EmpName'] ?? '' }}</td>
                    <td>{{ $row['Team'] ?? '' }}</td>
                    <td>{{ $row['LeaveYTD'] ?? 0 }}</td>
                    <td>{{ $row['EffectiveWorkingPercent'] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada data.</td></tr>
            @endforelse
        </table>
    @endif

    <p><a class="btn" href="{{ $portalUrl }}">Open OHS Web Portal</a></p>
</body>
</html>
