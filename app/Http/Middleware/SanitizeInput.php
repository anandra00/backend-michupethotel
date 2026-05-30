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
                // Remove null bytes (can bypass filters)
                $value = str_replace(chr(0), '', $value);
                
                // Advanced sanitization: Only remove dangerous HTML tags/attributes (script, iframe, style, event handlers)
                // to prevent XSS without corrupting innocent user input like "< 1 tahun" or "notes <like this>"
                $value = preg_replace('/<(script|iframe|style|embed|object|applet)[^>]*?>.*?<\/\1>/si', '', $value);
                $value = preg_replace('/(<[^>]+?)(?:\s+on[a-zA-Z]+\s*=\s*["\'].*?["\']|\s+href\s*=\s*["\']\s*javascript\s*:.*?["\'])([^>]*?>)/si', '$1$2', $value);
            }
        });
        $request->merge($input);

        return $next($request);
    }
}
