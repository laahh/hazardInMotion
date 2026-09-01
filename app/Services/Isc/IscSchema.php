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

    public static function rulesReady(): bool
    {
        try {
            return Schema::hasTable('isc_detection_rules');
        } catch (Throwable) {
            return false;
        }
    }
}
