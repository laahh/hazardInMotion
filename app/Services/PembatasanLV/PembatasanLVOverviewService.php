<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use App\Models\PembatasanLvInputasi;
use App\Models\PembatasanOrangInputasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PembatasanLVOverviewService
{
    private const SITE_CR_CACHE_TTL_SECONDS = 60;

    private const LIVE_LIST_LIMIT = 100;

    private const HISTORY_LIST_LIMIT = 100;

    /** @var list<string> */
    public const LV_LIVE_COLUMNS = [
        'id', 'nama_driver', 'no_lambung', 'checkin_at', 'lokasi', 'detail_lokasi',
    ];

    /** @var list<string> */
    public const LV_HISTORY_COLUMNS = [
        'id', 'no_lambung', 'nama_driver', 'control_room', 'lokasi', 'detail_lokasi',
        'checkin_at', 'checkout_at', 'shift',
    ];

    /** @var list<string> */
    public const ORANG_LIVE_COLUMNS = [
        'id', 'sid', 'nama', 'nama_perusahaan', 'checkin_at', 'lokasi', 'detail_lokasi',
    ];

    /** @var list<string> */
    public const ORANG_HISTORY_COLUMNS = [
        'id', 'sid', 'nama', 'nama_perusahaan', 'dept', 'control_room', 'lokasi',
        'detail_lokasi', 'checkin_at', 'checkout_at', 'shift',
    ];

    public function __construct(
        private readonly PembatasanLVControlRoomContextService $controlRoomContext,
    ) {}

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     * @return Collection<int, string>
     */
    public function supervisedRooms(?User $user, array $filters): Collection
    {
        $rooms = $this->controlRoomContext->controlRoomsForUser($user);

        $site = trim((string) ($filters['site'] ?? ''));
        if ($site !== '') {
            $siteRooms = $this->controlRoomsForSite($user, $site);
            if ($siteRooms->isNotEmpty()) {
                $rooms = $this->intersectRooms($rooms, $siteRooms);
            }
        }

        $filterRoom = trim((string) ($filters['control_room'] ?? ''));
        if ($filterRoom !== '') {
            $rooms = $rooms
                ->filter(fn (string $room) => strcasecmp($room, $filterRoom) === 0)
                ->values();
        }

        return $rooms;
    }

    /**
     * @return Collection<int, string>
     */
    public function siteOptions(?User $user): Collection
    {
        return collect($this->siteControlRoomCatalog($user)['sites']);
    }

    /**
     * @return Collection<int, string>
     */
    public function controlRoomOptions(?User $user, string $site): Collection
    {
        $userRooms = $this->controlRoomContext->controlRoomsForUser($user);
        $site = trim($site);
        if ($site === '') {
            return $userRooms->values();
        }

        $siteRooms = $this->controlRoomsForSite($user, $site);

        return $siteRooms->isNotEmpty()
            ? $this->intersectRooms($userRooms, $siteRooms)
            : $userRooms->values();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function baseQuery(?User $user, array $filters): Builder
    {
        return $this->scopedLvQuery($user, $filters)->orderByDesc('checkin_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function lvMasukAktifQuery(?User $user, array $filters): Builder
    {
        return $this->baseQuery($user, $filters)->whereNull('checkout_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function lvKeluarQuery(?User $user, array $filters): Builder
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->baseQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function lvAllListQuery(?User $user, array $filters): Builder
    {
        $query = $this->baseQuery($user, $filters);
        $this->applyCheckinDayFilter($query, $filters);

        return $query;
    }

    public function userCanManageRecord(?User $user, PembatasanLvInputasi $record): bool
    {
        return $this->controlRoomContext
            ->controlRoomsForUser($user)
            ->contains(fn (string $room) => strcasecmp($room, (string) $record->control_room) === 0);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangBaseQuery(?User $user, array $filters): Builder
    {
        return $this->scopedOrangQuery($user, $filters)->orderByDesc('checkin_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangMasukAktifQuery(?User $user, array $filters): Builder
    {
        return $this->orangBaseQuery($user, $filters)->whereNull('checkout_at');
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangKeluarQuery(?User $user, array $filters): Builder
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->orangBaseQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function orangAllListQuery(?User $user, array $filters): Builder
    {
        $query = $this->orangBaseQuery($user, $filters);
        $this->applyCheckinDayFilter($query, $filters);

        return $query;
    }

    public function userCanManageOrangRecord(?User $user, PembatasanOrangInputasi $record): bool
    {
        return $this->controlRoomContext
            ->controlRoomsForUser($user)
            ->contains(fn (string $room) => strcasecmp($room, (string) $record->control_room) === 0);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     * @return array{
     *     supervisedRooms: Collection<int, string>,
     *     sites: Collection<int, string>,
     *     controlRooms: Collection<int, string>,
     *     lvMasukAktif: int,
     *     lvKeluar: int,
     *     lvMasukAktifList: Collection<int, PembatasanLvInputasi>,
     *     lvAllList: Collection<int, PembatasanLvInputasi>,
     *     orangMasukAktif: int,
     *     orangKeluar: int,
     *     orangMasukAktifList: Collection<int, PembatasanOrangInputasi>,
     *     orangAllList: Collection<int, PembatasanOrangInputasi>
     * }
     */
    public function dashboardPayload(?User $user, array $filters): array
    {
        $supervisedRooms = $this->supervisedRooms($user, $filters);

        return [
            'supervisedRooms' => $supervisedRooms,
            'sites' => $this->siteOptions($user),
            'controlRooms' => $this->controlRoomOptions($user, (string) ($filters['site'] ?? '')),
            'lvMasukAktif' => $this->countLvMasukAktif($user, $filters),
            'lvKeluar' => $this->countLvKeluar($user, $filters),
            'lvMasukAktifList' => $this->lvMasukAktifQuery($user, $filters)
                ->select(self::LV_LIVE_COLUMNS)
                ->limit(self::LIVE_LIST_LIMIT)
                ->get(),
            'lvAllList' => $this->lvAllListQuery($user, $filters)
                ->select(self::LV_HISTORY_COLUMNS)
                ->limit(self::HISTORY_LIST_LIMIT)
                ->get(),
            'orangMasukAktif' => $this->countOrangMasukAktif($user, $filters),
            'orangKeluar' => $this->countOrangKeluar($user, $filters),
            'orangMasukAktifList' => $this->orangMasukAktifQuery($user, $filters)
                ->select(self::ORANG_LIVE_COLUMNS)
                ->limit(self::LIVE_LIST_LIMIT)
                ->get(),
            'orangAllList' => $this->orangAllListQuery($user, $filters)
                ->select(self::ORANG_HISTORY_COLUMNS)
                ->limit(self::HISTORY_LIST_LIMIT)
                ->get(),
        ];
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countLvMasukAktif(?User $user, array $filters): int
    {
        return $this->scopedLvQuery($user, $filters)->whereNull('checkout_at')->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countLvKeluar(?User $user, array $filters): int
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->scopedLvQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end)
            ->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countOrangMasukAktif(?User $user, array $filters): int
    {
        return $this->scopedOrangQuery($user, $filters)->whereNull('checkout_at')->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    public function countOrangKeluar(?User $user, array $filters): int
    {
        [$start, $end] = $this->dayBounds($filters);

        return $this->scopedOrangQuery($user, $filters)
            ->whereNotNull('checkout_at')
            ->where('checkout_at', '>=', $start)
            ->where('checkout_at', '<', $end)
            ->count();
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dayBounds(array $filters): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Makassar');
        $tanggal = trim((string) ($filters['tanggal'] ?? ''));

        try {
            $start = Carbon::parse($tanggal !== '' ? $tanggal : 'now', $timezone)->startOfDay();
        } catch (\Throwable) {
            $start = Carbon::now($timezone)->startOfDay();
        }

        return [$start, $start->copy()->addDay()];
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function scopedLvQuery(?User $user, array $filters): Builder
    {
        return $this->applyRoomScope(PembatasanLvInputasi::query(), $user, $filters, false);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function scopedOrangQuery(?User $user, array $filters): Builder
    {
        return $this->applyRoomScope(PembatasanOrangInputasi::query(), $user, $filters, true);
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function applyRoomScope(Builder $query, ?User $user, array $filters, bool $isOrang): Builder
    {
        $rooms = $this->supervisedRooms($user, $filters);
        if ($rooms->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('control_room', $rooms->all());

        if ($isOrang) {
            $site = trim((string) ($filters['site'] ?? ''));
            if ($site !== '') {
                $query->where('site', $site);
            }
        }

        return $query;
    }

    /**
     * @param  array{site?: string, tanggal?: string, control_room?: string}  $filters
     */
    private function applyCheckinDayFilter(Builder $query, array $filters): void
    {
        $tanggal = trim((string) ($filters['tanggal'] ?? ''));
        if ($tanggal === '') {
            return;
        }

        [$start, $end] = $this->dayBounds($filters);
        $query->where('checkin_at', '>=', $start)->where('checkin_at', '<', $end);
    }

    /**
     * @return Collection<int, string>
     */
    private function controlRoomsForSite(?User $user, string $site): Collection
    {
        $catalog = $this->siteControlRoomCatalog($user);

        foreach ($catalog['rooms_by_site'] as $catalogSite => $rooms) {
            if (strcasecmp((string) $catalogSite, $site) === 0) {
                return collect($rooms)->values();
            }
        }

        return collect();
    }

    /**
     * @param  Collection<int, string>  $left
     * @param  Collection<int, string>  $right
     * @return Collection<int, string>
     */
    private function intersectRooms(Collection $left, Collection $right): Collection
    {
        $rightKeys = $right->map(fn (string $room) => mb_strtolower($room))->all();

        return $left
            ->filter(fn (string $room) => in_array(mb_strtolower($room), $rightKeys, true))
            ->values();
    }

    /**
     * Site ↔ CR dari inputasi orang (tabel kecil, ter-index control_room).
     * Tidak memakai cctv_data_bmo2 yang full-scan.
     *
     * @return array{sites: list<string>, rooms_by_site: array<string, list<string>>}
     */
    private function siteControlRoomCatalog(?User $user): array
    {
        $empty = ['sites' => [], 'rooms_by_site' => []];
        if ($user === null) {
            return $empty;
        }

        $userRooms = $this->controlRoomContext->controlRoomsForUser($user);
        if ($userRooms->isEmpty()) {
            return $empty;
        }

        $cacheKey = 'pembatasan_lv:orang_site_cr_v1:'.$user->id;

        /** @var array{sites: list<string>, rooms_by_site: array<string, list<string>>} $catalog */
        $catalog = Cache::remember($cacheKey, self::SITE_CR_CACHE_TTL_SECONDS, function () use ($userRooms): array {
            $rows = PembatasanOrangInputasi::query()
                ->select(['site', 'control_room'])
                ->whereIn('control_room', $userRooms->all())
                ->whereNotNull('site')
                ->where('site', '!=', '')
                ->groupBy('site', 'control_room')
                ->get();

            $sites = [];
            $roomsBySite = [];

            foreach ($rows as $row) {
                $site = trim((string) $row->site);
                $room = trim((string) $row->control_room);
                if ($site === '' || $room === '') {
                    continue;
                }
                $sites[$site] = true;
                $roomsBySite[$site][$room] = true;
            }

            $sitesList = array_keys($sites);
            natcasesort($sitesList);

            $roomsBySiteList = [];
            foreach ($roomsBySite as $siteName => $rooms) {
                $roomList = array_keys($rooms);
                natcasesort($roomList);
                $roomsBySiteList[$siteName] = array_values($roomList);
            }

            return [
                'sites' => array_values($sitesList),
                'rooms_by_site' => $roomsBySiteList,
            ];
        });

        return $catalog;
    }
}
