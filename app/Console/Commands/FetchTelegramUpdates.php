<?php

namespace App\Console\Commands;

use App\Models\TelegramMessage;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class FetchTelegramUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:fetch-updates {--limit=100 : Maximum updates to fetch per request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch new updates from the configured Telegram bot and store them in the database.';

    public function handle(): int
    {
        $service = TelegramBotService::makeFromConfig();
        $offset = TelegramMessage::max('update_id');

        $payload = [
            'limit' => (int) $this->option('limit'),
        ];

        if ($offset) {
            $payload['offset'] = $offset + 1;
        }

        $response = $service->getUpdates($payload);

        if (! Arr::get($response, 'ok')) {
            $this->error('Telegram API responded with failure: '.json_encode($response));

            return self::FAILURE;
        }

        $updates = Arr::get($response, 'result', []);

        foreach ($updates as $update) {
            $message = Arr::get($update, 'message', []);
            $chat = Arr::get($message, 'chat', []);

            TelegramMessage::updateOrCreate(
                ['update_id' => Arr::get($update, 'update_id')],
                [
                    'message_id' => Arr::get($message, 'message_id'),
                    'chat_id' => Arr::get($chat, 'id'),
                    'chat_type' => Arr::get($chat, 'type'),
                    'username' => Arr::get($message, 'from.username'),
                    'first_name' => Arr::get($message, 'from.first_name'),
                    'last_name' => Arr::get($message, 'from.last_name'),
                    'text' => Arr::get($message, 'text'),
                    'raw_payload' => $update,
                    'message_date' => Arr::has($message, 'date')
                        ? now()->setTimestamp(Arr::get($message, 'date'))
                        : null,
                ]
            );
        }

        $this->info(sprintf('Stored %d updates.', count($updates)));

        return self::SUCCESS;
    }
}


