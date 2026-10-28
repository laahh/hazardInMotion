<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    public function __construct()
    {
        // Proxy/WAF di depan aplikasi (pemutus TLS): TRUSTED_PROXIES=* atau
        // daftar IP dipisah koma, supaya X-Forwarded-Proto: https dipercaya.
        $env = trim((string) config('app.trusted_proxies', ''));
        if ($env !== '') {
            $this->proxies = $env === '*' ? '*' : array_map('trim', explode(',', $env));
        }
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
