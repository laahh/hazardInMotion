<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Actions\Isc\IscRfidReconcileAction;
use Tests\TestCase;

final class IscRfidReconcileActionTest extends TestCase
{
    public function test_three_set_gaps(): void
    {
        $result = (new IscRfidReconcileAction())->execute(
            [
                ['sid' => 'A', 'name' => 'Ali'],
                ['sid' => 'B', 'name' => 'Budi'],
            ],
            [
                ['sid' => 'A', 'name' => 'Ali'],
            ],
            [
                ['sid' => 'a', 'name' => 'Ali RFID'],
                ['sid' => 'C', 'name' => 'Citra'],
            ],
        );

        $this->assertSame(2, $result['ever_count']);
        $this->assertSame(1, $result['current_count']);
        $this->assertSame(2, $result['rfid_count']);
        $this->assertSame(1, $result['gap_besigma_minus_rfid_count']);
        $this->assertSame(1, $result['gap_rfid_minus_besigma_count']);
        $this->assertSame(1, $result['both_count']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $result['ever']);
        $this->assertContains('B', array_column($result['gap_besigma_minus_rfid'], 'sid'));
        $this->assertContains('C', array_column($result['gap_rfid_minus_besigma'], 'sid'));
        $this->assertContains('A', array_column($result['both'], 'sid'));
        $this->assertContains('A', array_column($result['current_list'], 'sid'));
        $this->assertSame('Ali', $result['both'][0]['name']);
    }
}
