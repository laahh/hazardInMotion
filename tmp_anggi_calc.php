<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$table = 'scr_hsecm_partisipasi_sap_l1_rfid';
$latest = DB::table($table)->max('batch_slot');
echo 'latest_slot=' . $latest . PHP_EOL;

$current = DB::table($table)
    ->where('pelapor_all_karyawan', 'like', '%ANGGI WIDIARSENA%')
    ->where('batch_slot', $latest)
    ->first();
if (!$current) {
    $current = DB::table($table)
        ->where('pelapor_all_karyawan', 'like', '%ANGGI WIDIARSENA%')
        ->orderByDesc('batch_slot')
        ->first();
}
if (!$current) { echo "NOT_FOUND\n"; exit; }

echo 'name=' . $current->pelapor_all_karyawan . PHP_EOL;
echo 'sid=' . $current->sid_pelapor_all_karyawan . PHP_EOL;
echo 'site=' . ($current->site_dedicated_pelapor_all_karyawan ?? '') . PHP_EOL;
echo 'date=' . ($current->date ?? '') . PHP_EOL;
echo 'gap_count_col=' . ($current->gap_count ?? '') . PHP_EOL;
echo 'business_key=' . ($current->business_key ?? '') . PHP_EOL;
echo 'batch_slot=' . $current->batch_slot . PHP_EOL;
echo 'SAP_per_SID=' . ($current->SAP_per_SID ?? '') . PHP_EOL;

$bk = trim((string) ($current->business_key ?? ''));
if ($bk === '') { echo "NO_BK\n"; exit; }

$slots = DB::table($table)
    ->where('batch_slot', '<=', $current->batch_slot)
    ->distinct()
    ->orderByDesc('batch_slot')
    ->limit(60)
    ->pluck('batch_slot')
    ->map(fn($s) => (string) $s)
    ->unique()
    ->values()
    ->all();
rsort($slots);

$presence = DB::table($table)
    ->select('batch_slot')
    ->where('business_key', $bk)
    ->whereBetween('batch_slot', [end($slots), $current->batch_slot])
    ->pluck('batch_slot')
    ->map(fn($s) => (string) $s)
    ->unique()
    ->flip()
    ->all();

$streak = 0;
$streakSlots = [];
foreach ($slots as $slot) {
    if (isset($presence[$slot])) {
        $streak++;
        $streakSlots[] = $slot;
    } else {
        break;
    }
}

$days = (int) ceil($streak / 4);
echo 'streak_slots=' . $streak . PHP_EOL;
echo 'display_days=ceil(' . $streak . '/4)=' . $days . 'x' . PHP_EOL;
echo 'first_slot_in_streak=' . (end($streakSlots) ?: '-') . PHP_EOL;
echo 'last_slot_in_streak=' . ($streakSlots[0] ?? '-') . PHP_EOL;
echo '---streak_slots---' . PHP_EOL;
foreach ($streakSlots as $s) echo $s . PHP_EOL;
