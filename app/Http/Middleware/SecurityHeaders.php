<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline browser-side protections to every response (pages and API).
 *
 * The Content-Security-Policy uses a per-request nonce for scripts instead of
 * 'unsafe-inline', so an injected <script> can't run. Vite's @vite tags pick the
 * nonce up automatically; the one hand-written inline script (spa.blade.php)
 * reads it via Vite::cspNonce().
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->remove('X-Powered-By');
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // While the Vite dev server is running (public/hot exists), scripts come from
        // another origin, so only send the CSP for built assets.
        if (! file_exists(public_path('hot'))) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                // Inline styles are needed by the React dashboard (style attributes + injected <style>).
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data: https:",
                "connect-src 'self'",
                // Google Maps embed on the landing page.
                'frame-src https://www.google.com https://maps.google.com',
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
