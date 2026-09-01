<?php

declare(strict_types=1);

namespace App\Actions\Isc;

/**
 * @deprecated Gunakan IscSyncActiveViolationsAction. Tetap ada agar command scheduler tidak berubah.
 */
final class IscDetectHazardEntryAction
{
    public const RULE_CODE = IscSyncActiveViolationsAction::RULE_CODE;

    public function __construct(
        private readonly IscSyncActiveViolationsAction $sync,
    ) {}

    /**
     * @return array{created:int,updated?:int,closed:int,skipped:bool,message:?string}
     */
    public function execute(bool $demo = false): array
    {
        return $this->sync->execute($demo);
    }
}
