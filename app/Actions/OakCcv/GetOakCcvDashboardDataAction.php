<?php

declare(strict_types=1);

namespace App\Actions\OakCcv;

use App\Services\OakCcv\OakCcvDashboardPayloadService;

/**
 * Merakit data dashboard OAK CCV (Observasi Area Kritis).
 */
final class GetOakCcvDashboardDataAction
{
    public function __construct(
        private readonly OakCcvDashboardPayloadService $payload,
    ) {}

    /**
     * @param array{site?: string, week?: string, group?: string, entity?: string} $filters
     * @return array<string, mixed>
     */
    public function __invoke(array $filters): array
    {
        return $this->payload->build($filters);
    }
}
