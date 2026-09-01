<?php

declare(strict_types=1);

namespace App\Services\Isc;

use Illuminate\Support\Facades\Schema;
use Throwable;

final class IscSchema
{
    public static function eventsReady(): bool
    {
        try {
            return Schema::hasTable('isc_boundary_events');
        } catch (Throwable) {
            return false;
        }
    }

    public static function violationSyncReady(): bool
    {
        try {
            return Schema::hasTable('isc_boundary_events')
                && Schema::hasColumn('isc_boundary_events', 'besigma_violation_id')
                && Schema::hasColumn('isc_boundary_events', 'entity')
                && Schema::hasColumn('isc_boundary_events', 'hazard_kind');
        } catch (Throwable) {
            return false;
        }
    }

    public static function rulesReady(): bool
    {
        try {
            return Schema::hasTable('isc_detection_rules');
        } catch (Throwable) {
            return false;
        }
    }
}
