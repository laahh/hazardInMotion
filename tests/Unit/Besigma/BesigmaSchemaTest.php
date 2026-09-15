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

    public function test_flag_predicates_are_text_safe(): void
    {
        $this->assertStringContainsString("is_deleted::text", BesigmaSchema::flagIsFalse('is_deleted'));
        $this->assertStringContainsString("'0'", BesigmaSchema::flagIsFalse('u.is_deleted'));
        $this->assertStringContainsString("is_active::text", BesigmaSchema::flagIsTrue('is_active'));
        $this->assertStringContainsString("'1'", BesigmaSchema::flagIsTrue('b.is_active'));
    }
}
