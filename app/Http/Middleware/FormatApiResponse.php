<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormatApiResponse
{
    
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && $request->is('api/*')) {
            $data = $response->getData(true);
            
            $formattedData = [
                'success' => $response->isSuccessful(),
                'status_code' => $response->getStatusCode(),
            ];

            if ($response->isSuccessful()) {
                $formattedData['data'] = $data;
            } else {
                $formattedData['error'] = $data;
            }

            $response->setData($formattedData);
        }

        return $response;
    }
}
