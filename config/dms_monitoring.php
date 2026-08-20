<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cakupan data GPS (dms_vehicle_statuses FDW)
    |--------------------------------------------------------------------------
    |
    | FDW dms_vehicle_statuses sering hanya berisi batch historis (mis. Feb 2026).
    | Window dengan start >= tanggal ini TIDAK akan query FDW GPS sama sekali —
    | langsung pakai fallback dms_vehicle_status_alerts (jauh lebih cepat).
    |
    | Format: Y-m-d (exclusive end-of-coverage + 1 hari).
    |
    */
    'gps_statuses_through' => env('DMS_GPS_STATUSES_THROUGH', '2026-02-28'),

];
