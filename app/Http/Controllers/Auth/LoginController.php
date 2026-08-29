<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\SportEvaluation\SportEvaluationAccessService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Setelah login: kembali ke halaman yang ingin dibuka.
     * /maps hanya fallback jika user membuka /login langsung (tidak ada tujuan).
     */
    protected function authenticated(Request $request, mixed $user): ?RedirectResponse
    {
        if (app(SportEvaluationAccessService::class)->isMitraOnlyUser($user)) {
            $request->session()->forget('url.intended');

            return redirect()->route('evaluasi-well.mitra.index');
        }

        $intended = $request->session()->pull('url.intended');

        return redirect()->to($this->normalizePostLoginUrl($intended));
    }

    private function normalizePostLoginUrl(mixed $intended): string
    {
        $default = $this->redirectPath();

        if (! is_string($intended) || trim($intended) === '') {
            return $default;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $default;
        }

        $intendedHost = parse_url($intended, PHP_URL_HOST);
        if (is_string($intendedHost) && $intendedHost !== '' && $intendedHost !== request()->getHost()) {
            return $default;
        }

        if ($path === '/login' || str_ends_with($path, '/login')) {
            return $default;
        }

        if (str_contains($path, '/login/')) {
            $path = preg_replace('#/login/#', '/', $path, 1) ?? $path;
            $query = parse_url($intended, PHP_URL_QUERY);

            return $path.($query ? '?'.$query : '');
        }

        return $intended;
    }
}
