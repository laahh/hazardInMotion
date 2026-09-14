<?php

declare(strict_types=1);

namespace Tests\Unit\Besigma;

use App\Services\Besigma\BesigmaSchema;
use Tests\TestCase;

final class BesigmaSchemaTest extends TestCase
{
    public function test_qualify_prefixes_schema(): void
    {
        $this->assertSame('besigma_db.boundaries', BesigmaSchema::qualify('boundaries'));
        $this->assertSame('besigma_db.users', BesigmaSchema::qualify('users'));
    }

    public function test_table_supports_alias(): void
    {
        $this->assertSame('besigma_db.users as u', BesigmaSchema::table('users as u'));
        $this->assertSame('besigma_db.boundaries', BesigmaSchema::table('boundaries'));
    }

    public function test_qualify_keeps_existing_schema(): void
    {
        $this->assertSame('besigma_db.boundaries', BesigmaSchema::qualify('besigma_db.boundaries'));
    }
}
