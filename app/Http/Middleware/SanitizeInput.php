<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Strip potential XSS payloads from all string inputs.
     * Acts as defense-in-depth alongside React's JSX escaping.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                // Strip HTML tags to prevent stored XSS
                $value = strip_tags($value);
                // Remove null bytes (can bypass filters)
                $value = str_replace(chr(0), '', $value);
            }
        });
        $request->merge($input);

        return $next($request);
    }
}
