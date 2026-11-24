<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RememberListingUrl
{
    public const SESSION_KEY = 'listing_urls';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->isMethod('GET')
            && ! $request->expectsJson()
            && $response->isSuccessful()
            && $request->hasSession()
        ) {
            $routeName = $request->route()?->getName();

            if ($routeName !== null && Str::endsWith($routeName, ['.index', '.history'])) {
                $urls = $request->session()->get(self::SESSION_KEY, []);
                $urls[$request->getPathInfo()] = $request->fullUrl();
                $request->session()->put(self::SESSION_KEY, $urls);
            }
        }

        return $response;
    }
}
