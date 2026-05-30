<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add security headers to every response.
     * Prevents clickjacking, MIME sniffing, XSS, and enforces HTTPS.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking — page cannot be embedded in iframe
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing — browser must respect Content-Type
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Enable XSS filtering in older browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information leaked to external sites
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser features (camera, microphone, geolocation, etc.)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

        // Force HTTPS for 1 year (only in production)
        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Content-Security-Policy for defense-in-depth (M-07)
        $response->headers->set('Content-Security-Policy', 
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://app.sandbox.midtrans.com https://app.midtrans.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "img-src 'self' data: https://images.unsplash.com https://api.dicebear.com blob:; " .
            "connect-src 'self' https://*.midtrans.com https://michu-backend-production.up.railway.app https://backend-michupethotel-production.up.railway.app https://*.vercel.app http://localhost:*"
        );

        // Remove server identification header
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
