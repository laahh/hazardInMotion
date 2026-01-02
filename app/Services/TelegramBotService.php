<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    private Client $client;

    public function __construct(private readonly string $botToken)
    {
        $this->client = new Client([
            'base_uri' => sprintf('https://api.telegram.org/bot%s/', $botToken),
            'timeout' => 10,
        ]);
    }

    public static function makeFromConfig(): self
    {
        $token = config('services.telegram.bot_token');

        if (empty($token)) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }

        return new self($token);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function getUpdates(array $payload = []): array
    {
        return $this->request('getUpdates', $payload);
    }

    /**
     * Send message to Telegram
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendMessage(array $payload = []): array
    {
        return $this->request('sendMessage', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, array $payload = []): array
    {
        try {
            $response = $this->client->post($method, ['json' => $payload]);

            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('Telegram API call failed', [
                'method' => $method,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}


