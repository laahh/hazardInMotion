<?php

namespace App\Exceptions;

use App\Exceptions\OhsDashboard\OhsDashboardException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (OhsDashboardException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status());
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if (! $this->isOhsDashboardApi($request)) {
                return null;
            }

            return response()->json(['error' => 'Silakan login.'], 401);
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if (! $this->isOhsDashboardApi($request)) {
                return null;
            }

            $message = collect($e->errors())->flatten()->first() ?: $e->getMessage();

            return response()->json(['error' => $message], 422);
        });

        $this->renderable(function (QueryException $e, Request $request) {
            if (! $this->isOhsDashboardApi($request)) {
                return null;
            }

            report($e);

            return response()->json([
                'error' => 'Database OHS Dashboard lambat atau belum siap. Sempitkan filter Team/Site, lalu coba lagi.',
            ], 503);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            if (! $this->isOhsDashboardApi($request)) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $status >= 500 ? 'Terjadi kesalahan pada server OHS Dashboard.' : $e->getMessage();

            if ($status >= 500) {
                report($e);
            }

            return response()->json(['error' => $message !== '' ? $message : 'Terjadi kesalahan.'], $status);
        });
    }

    private function isOhsDashboardApi(Request $request): bool
    {
        return $request->is('ohs-dashboard/api') || $request->is('ohs-dashboard/api/*');
    }

    /**
     * Simpan halaman tujuan asli. Jangan timpa dengan /login atau request AJAX,
     * supaya setelah login user kembali ke konteks yang ingin dibuka.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->shouldReturnJson($request, $exception) || $this->isOhsDashboardApi($request)) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        $this->rememberIntendedUrl($request);

        return redirect()->to($exception->redirectTo($request) ?? route('login'));
    }

    private function rememberIntendedUrl(Request $request): void
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');
        if ($path === '/login' || str_starts_with($path, '/login/') || $path === '/logout') {
            return;
        }

        if (str_contains($path, '/api/') || $request->is('build/*') || $request->is('livewire/*')) {
            return;
        }

        $existing = $request->session()->get('url.intended');
        if (is_string($existing) && $existing !== '' && ! $this->isLoginUrl($existing)) {
            return;
        }

        $request->session()->put('url.intended', $request->fullUrl());
    }

    private function isLoginUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return false;
        }

        return $path === '/login' || str_ends_with($path, '/login') || str_contains($path, '/login/');
    }
}
