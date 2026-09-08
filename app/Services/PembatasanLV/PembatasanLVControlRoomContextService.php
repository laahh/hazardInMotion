<?php

declare(strict_types=1);

namespace App\Services\PembatasanLV;

use App\Models\CctvControlRoomPengawas;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PembatasanLVControlRoomContextService
{
    private const CACHE_TTL_SECONDS = 60;

    /** @var array<int, Collection<int, string>> */
    private array $requestMemo = [];

    public function controlRoomsForUser(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        $userId = (int) $user->id;
        if (isset($this->requestMemo[$userId])) {
            return $this->requestMemo[$userId];
        }

        $cacheKey = 'pembatasan_lv:cr_user_v1:'.$userId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $rooms = collect($cached)->values();
            $this->requestMemo[$userId] = $rooms;

            return $rooms;
        }

        $email = mb_strtolower(trim((string) $user->email));
        $name = mb_strtolower(trim((string) $user->name));

        $rooms = CctvControlRoomPengawas::query()
            ->select(['control_room'])
            ->where(function ($query) use ($email, $name): void {
                if ($email !== '') {
                    $query->whereRaw('LOWER(TRIM(email_pengawas)) = ?', [$email]);
                }
                if ($name !== '') {
                    $query->orWhereRaw('LOWER(TRIM(nama_pengawas)) = ?', [$name]);
                }
            })
            ->orderBy('control_room')
            ->pluck('control_room')
            ->map(fn ($room) => trim((string) $room))
            ->filter()
            ->unique()
            ->values();

        Cache::put($cacheKey, $rooms->all(), self::CACHE_TTL_SECONDS);
        $this->requestMemo[$userId] = $rooms;

        return $rooms;
    }

    public function primaryControlRoom(?User $user): ?string
    {
        return $this->controlRoomsForUser($user)->first();
    }
}
