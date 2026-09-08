<?php

declare(strict_types=1);

namespace Tests\Unit\PembatasanLV;

use App\Models\User;
use App\Services\PembatasanLV\PembatasanLVControlRoomContextService;
use App\Services\PembatasanLV\PembatasanLVOverviewService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class PembatasanLVOverviewServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_lv_keluar_uses_checkout_range_instead_of_date_function(): void
    {
        $service = $this->serviceWithRooms(['CR A']);
        $user = new User(['id' => 1]);

        $query = $service->lvKeluarQuery($user, ['tanggal' => '2026-09-08']);
        $sql = strtolower($query->toSql());

        $this->assertStringNotContainsString('date(', $sql);
        $this->assertStringContainsString('checkout_at', $sql);
        $this->assertContains('2026-09-08 00:00:00', $this->bindingStrings($query->getBindings()));
        $this->assertContains('2026-09-09 00:00:00', $this->bindingStrings($query->getBindings()));
    }

    public function test_history_query_filters_by_checkin_range_only(): void
    {
        $service = $this->serviceWithRooms(['CR A', 'CR B']);
        $user = new User(['id' => 1]);

        $query = $service->lvAllListQuery($user, ['tanggal' => '2026-09-08']);
        $sql = strtolower($query->toSql());

        $this->assertStringNotContainsString('date(', $sql);
        $this->assertStringContainsString('checkin_at', $sql);
        $this->assertStringNotContainsString('checkout_at', $sql);
    }

    public function test_control_room_filter_scopes_query(): void
    {
        $service = $this->serviceWithRooms(['CONTROL ROOM DERAWAN', 'Office BC SMO']);
        $user = new User(['id' => 1]);

        $query = $service->lvMasukAktifQuery($user, ['control_room' => 'Office BC SMO']);
        $bindings = $this->bindingStrings($query->getBindings());

        $this->assertContains('Office BC SMO', $bindings);
        $this->assertNotContains('CONTROL ROOM DERAWAN', $bindings);
    }

    public function test_unknown_control_room_filter_returns_empty_scope(): void
    {
        $service = $this->serviceWithRooms(['CONTROL ROOM DERAWAN']);
        $user = new User(['id' => 1]);

        $sql = strtolower($service->lvMasukAktifQuery($user, [
            'control_room' => 'CR TIDAK ADA',
        ])->toSql());

        $this->assertStringContainsString('1 = 0', $sql);
    }

    public function test_orang_site_filter_matches_site_column(): void
    {
        $user = $this->userWithId(1);
        Cache::put('pembatasan_lv:orang_site_cr_v1:1', [
            'sites' => ['SMO'],
            'rooms_by_site' => ['SMO' => ['Office BC SMO']],
        ], 60);

        $service = $this->serviceWithRooms(['Office BC SMO']);

        $query = $service->orangMasukAktifQuery($user, ['site' => 'SMO']);
        $sql = strtolower($query->toSql());
        $bindings = $this->bindingStrings($query->getBindings());

        $this->assertStringContainsString('site', $sql);
        $this->assertContains('SMO', $bindings);
        $this->assertContains('Office BC SMO', $bindings);
    }

    public function test_site_filter_narrows_lv_to_site_control_rooms(): void
    {
        $user = $this->userWithId(1);
        Cache::put('pembatasan_lv:orang_site_cr_v1:1', [
            'sites' => ['SMO', 'BMO'],
            'rooms_by_site' => [
                'SMO' => ['Office BC SMO'],
                'BMO' => ['CONTROL ROOM DERAWAN'],
            ],
        ], 60);

        $service = $this->serviceWithRooms(['Office BC SMO', 'CONTROL ROOM DERAWAN']);

        $query = $service->lvMasukAktifQuery($user, ['site' => 'SMO']);
        $bindings = $this->bindingStrings($query->getBindings());

        $this->assertContains('Office BC SMO', $bindings);
        $this->assertNotContains('CONTROL ROOM DERAWAN', $bindings);
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $user->id = $id;

        return $user;
    }

    /**
     * @param  list<string>  $rooms
     */
    private function serviceWithRooms(array $rooms): PembatasanLVOverviewService
    {
        $context = Mockery::mock(PembatasanLVControlRoomContextService::class);
        $context->shouldReceive('controlRoomsForUser')->andReturn(collect($rooms));

        return new PembatasanLVOverviewService($context);
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<string>
     */
    private function bindingStrings(array $bindings): array
    {
        return array_map(static function (mixed $value): string {
            if ($value instanceof Carbon) {
                return $value->format('Y-m-d H:i:s');
            }

            return (string) $value;
        }, $bindings);
    }
}
