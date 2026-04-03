<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginThrottle
{
    public function __construct(protected RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && (str_contains($request->path(), 'login'))) {
            $key = 'login:' . $request->ip();
            
            if ($this->limiter->tooManyAttempts($key, 5)) {
                $seconds = $this->limiter->availableIn($key);
                if ($request->expectsJson()) {
                    return response()->json(['message' => "Too many login attempts. Try again in {$seconds} seconds."], 429);
                }
                return back()->withErrors(['throttle' => "Too many login attempts. Please wait {$seconds} seconds."]);
            }
            
            $this->limiter->hit($key, 300); // 5 min window
        }
        
        return $next($request);
    }
}
