<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');

        if (! empty($secret) && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            Log::warning('Telegram webhook rejected due to invalid secret token');

            return response()->json(['status' => 'forbidden'], 403);
        }

        $payload = $request->all();

        if (empty($payload['update_id'])) {
            Log::warning('Telegram webhook received payload without update_id');

            return response()->json(['status' => 'ignored'], 202);
        }

        $message = Arr::get($payload, 'message', []);
        $chat = Arr::get($message, 'chat', []);

        TelegramMessage::updateOrCreate(
            ['update_id' => $payload['update_id']],
            [
                'message_id' => Arr::get($message, 'message_id'),
                'chat_id' => Arr::get($chat, 'id'),
                'chat_type' => Arr::get($chat, 'type'),
                'username' => Arr::get($message, 'from.username'),
                'first_name' => Arr::get($message, 'from.first_name'),
                'last_name' => Arr::get($message, 'from.last_name'),
                'text' => Arr::get($message, 'text'),
                'raw_payload' => $payload,
                'message_date' => isset($message['date']) ? now()->setTimestamp($message['date']) : null,
            ]
        );

        return response()->json(['status' => 'ok']);
    }
}


