<?php

declare(strict_types=1);

namespace App\Events\Isc;

use App\Models\Isc\IscBoundaryEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class IscHazardEntered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly IscBoundaryEvent $event,
    ) {}
}
