<?php

declare(strict_types=1);

namespace Tests\Unit\Hsecm;

use App\Services\Hsecm\HsecmTasklistEvidenceUpload;
use PHPUnit\Framework\TestCase;

class HsecmTasklistEvidenceUploadTest extends TestCase
{
    public function test_parses_php_ini_shorthand(): void
    {
        $this->assertSame(2 * 1024 * 1024, HsecmTasklistEvidenceUpload::parseIniSize('2M'));
        $this->assertSame(8 * 1024 * 1024, HsecmTasklistEvidenceUpload::parseIniSize('8M'));
        $this->assertSame(12 * 1024 * 1024, HsecmTasklistEvidenceUpload::parseIniSize('12M'));
        $this->assertSame(0, HsecmTasklistEvidenceUpload::parseIniSize('-1'));
        $this->assertSame(0, HsecmTasklistEvidenceUpload::parseIniSize(''));
    }

    public function test_formats_megabytes_without_trailing_zero(): void
    {
        $this->assertSame('2', HsecmTasklistEvidenceUpload::formatMegabytes(2 * 1024 * 1024));
        $this->assertSame('10', HsecmTasklistEvidenceUpload::formatMegabytes(10 * 1024 * 1024));
        $this->assertSame('1.5', HsecmTasklistEvidenceUpload::formatMegabytes((int) (1.5 * 1024 * 1024)));
    }

    public function test_app_max_is_10_mb(): void
    {
        $this->assertSame(10240, HsecmTasklistEvidenceUpload::appMaxKilobytes());
        $this->assertSame(10240 * 1024, HsecmTasklistEvidenceUpload::appMaxBytes());
    }
}
