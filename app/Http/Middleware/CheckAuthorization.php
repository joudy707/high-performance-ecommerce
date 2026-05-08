<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckAuthorization
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Ensure the user is logged in
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['error', 'You must be logged in.'],401);
            }
        } catch (JWTException $e) {
            // Token is expired
            return response()->json(['error' => 'Token is invalid or expired'], 401);
        }
        return $next($request);
    }
}
