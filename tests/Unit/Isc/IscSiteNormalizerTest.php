<?php

declare(strict_types=1);

namespace Tests\Unit\Isc;

use App\Services\Isc\IscSiteNormalizer;
use Tests\TestCase;

final class IscSiteNormalizerTest extends TestCase
{
    public function test_maps_labels_to_site_codes(): void
    {
        $sites = new IscSiteNormalizer();

        $this->assertSame('BMO', $sites->codeFrom('BMO'));
        $this->assertSame('BMO', $sites->codeFrom('Site Binungan 2', 'BMO 2'));
        $this->assertSame('LMO', $sites->codeFrom('Gate LMO'));
        $this->assertSame('GMO', $sites->codeFrom('Site Gurimbang'));
        $this->assertSame('SMO', $sites->codeFrom('Sambarata'));
        $this->assertSame('PUNAN', $sites->codeFrom('Blok Punan'));
        $this->assertNull($sites->codeFrom(''));
    }
}
