<?php

declare(strict_types=1);

namespace App\Support\EmergencyResponse;

use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders a Blade view to a downloadable PDF via Browsershot (the only
 * PDF/screenshot generator already proven to work on this server —
 * see app/Console/Commands/SendDashboardScreenshot.php).
 */
class PrintableExporter
{
    /**
     * @throws \RuntimeException when Browsershot/Chrome is unavailable on this machine
     */
    public function streamPdf(string $view, array $data, string $filename): StreamedResponse
    {
        if (! class_exists(Browsershot::class)) {
            throw new \RuntimeException('Package spatie/browsershot belum terpasang.');
        }

        $html = view($view, $data)->render();
        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'er_pdf_'.uniqid().'.pdf';

        try {
            Browsershot::html($html)
                ->timeout(60)
                ->showBackground()
                ->waitUntilNetworkIdle()
                ->save($tempFile);

            if (! is_file($tempFile) || filesize($tempFile) === 0) {
                throw new \RuntimeException('File PDF tidak terbentuk.');
            }

            $bytes = file_get_contents($tempFile);
        } catch (\Throwable $e) {
            Log::error('PrintableExporter gagal membuat PDF: '.$e->getMessage());
            throw new \RuntimeException('Gagal membuat PDF: '.$e->getMessage(), previous: $e);
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        return response()->streamDownload(
            fn () => print $bytes,
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
