<?php

namespace App\Exceptions;

use App\Exceptions\OhsDashboard\OhsDashboardException;
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

        $this->renderable(function (ValidationException $e, Request $request) {
            if (! $this->isOhsDashboardApi($request)) {
                return null;
            }

            $message = collect($e->errors())->flatten()->first() ?: $e->getMessage();

            return response()->json(['error' => $message], 422);
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
}
