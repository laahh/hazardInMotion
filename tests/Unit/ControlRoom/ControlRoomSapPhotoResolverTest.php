<?php

declare(strict_types=1);

namespace Tests\Unit\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapPhotoResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ControlRoomSapPhotoResolverTest extends TestCase
{
    public function test_memisahkan_foto_temuan_dan_penyelesaian_dari_halaman_photocar(): void
    {
        $html = <<<'HTML'
            <h4>Foto Temuan</h4>
            <img alt="No Photo" src="https://hseautomation.beraucoal.co.id/beats2/file/16744165">
            <a href="https://hseautomation.beraucoal.co.id/beats2/file/16744165">Unduh</a>
            <h4>Foto Penyelesaian</h4>
            <img alt="No Photo" src="https://hseautomation.beraucoal.co.id/beats2/file/16748631">
            <a href="https://hseautomation.beraucoal.co.id/beats2/file/16748631">Unduh</a>
            HTML;

        $this->assertSame(
            [
                'foto_temuan' => 'https://hseautomation.beraucoal.co.id/beats2/file/16744165',
                'foto_penyelesaian' => 'https://hseautomation.beraucoal.co.id/beats2/file/16748631',
            ],
            (new ControlRoomSapPhotoResolver())->parseHtml($html),
        );
    }

    public function test_url_relatif_beats2_dijadikan_absolut(): void
    {
        $html = <<<'HTML'
            Foto Temuan
            <a href="/beats2/file/16744165">Unduh</a>
            Foto Penyelesaian
            <a href="/beats2/file/16748631">Unduh</a>
            HTML;

        $this->assertSame(
            [
                'foto_temuan' => 'https://hseautomation.beraucoal.co.id/beats2/file/16744165',
                'foto_penyelesaian' => 'https://hseautomation.beraucoal.co.id/beats2/file/16748631',
            ],
            (new ControlRoomSapPhotoResolver())->parseHtml($html),
        );
    }

    public function test_from_photo_car_id_memakai_halaman_photocar(): void
    {
        Http::fake([
            'hseautomation.beraucoal.co.id/report/photoCar/9374205' => Http::response(
                '<h4>Foto Temuan</h4><img src="https://hseautomation.beraucoal.co.id/beats2/file/16744165" alt="No Photo">'
                .'<h4>Foto Penyelesaian</h4><img src="https://hseautomation.beraucoal.co.id/beats2/file/16748631" alt="No Photo">',
                200,
            ),
        ]);

        $this->assertSame(
            [
                'foto_temuan' => 'https://hseautomation.beraucoal.co.id/beats2/file/16744165',
                'foto_penyelesaian' => 'https://hseautomation.beraucoal.co.id/beats2/file/16748631',
            ],
            (new ControlRoomSapPhotoResolver())->fromPhotoCarId(9374205),
        );
    }
}
