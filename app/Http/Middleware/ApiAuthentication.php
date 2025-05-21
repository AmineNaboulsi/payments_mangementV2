<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Check if already authenticated via session
            if (Auth::check()) {
                return $next($request);
            }
            
            // Try to find user by API token
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'message' => 'Unauthenticated - No token provided',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            $user = User::where('api_token', $token)->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated - Invalid token',
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Log in the user
            Auth::login($user);

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Auth middleware error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Authentication error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
