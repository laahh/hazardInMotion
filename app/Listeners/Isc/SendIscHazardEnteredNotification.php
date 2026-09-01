<?php

declare(strict_types=1);

namespace App\Listeners\Isc;

use App\Events\Isc\IscHazardEntered;
use App\Services\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendIscHazardEnteredNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(IscHazardEntered $event): void
    {
        $row = $event->event;
        $text = sprintf(
            "ISC Unsafe\n%s (%s)\nZona: %s\nBahaya: %s\nMasuk: %s",
            $row->name,
            $row->sid ?: $row->person_key,
            $row->iupk_site ?: '-',
            $row->hazard_name ?: '-',
            optional($row->entered_at)->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?: '-',
        );

        $chatId = config('services.telegram.chat_id');
        $token = config('services.telegram.bot_token');
        if (empty($chatId) || empty($token)) {
            Log::info('ISC hazard entered (in-app only)', [
                'event_id' => $row->id,
                'person_key' => $row->person_key,
            ]);

            return;
        }

        try {
            TelegramBotService::makeFromConfig()->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
