<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class RefreshToken extends \Tymon\JWTAuth\Middleware\BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {

            $newToken = $this->auth->setRequest($request)->parseToken()->refresh();
        } catch (TokenExpiredException $e) {
            return response()->json(
                ['message' => 'refresh_ttl_finished'],
                $e->getStatusCode(),
                [],
                JSON_PRETTY_PRINT
            );
        } catch (JWTException $e) {
            return response()->json(
                ['message' => 'invalid_token'],
                $e->getStatusCode(),
                [],
                JSON_PRETTY_PRINT
            );
        }

        return response()->json(
            ['token' => $newToken],
            201,
            [],
            JSON_PRETTY_PRINT
        );
    }
}
