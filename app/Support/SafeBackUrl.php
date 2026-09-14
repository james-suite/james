<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class SafeBackUrl
{
    public function resolve(
        ?string $previousUrl,
        string $fallback,
        string $currentUrl,
        string $applicationUrl,
    ): string {
        if (! $previousUrl || ! $this->isSafe($previousUrl, $currentUrl, $applicationUrl)) {
            return $fallback;
        }

        return $previousUrl;
    }

    private function isSafe(string $previousUrl, string $currentUrl, string $applicationUrl): bool
    {
        $previous = parse_url($previousUrl);
        $current = parse_url($currentUrl);
        $application = parse_url($applicationUrl);

        if (! is_array($previous) || ! is_array($current) || ! is_array($application)) {
            return false;
        }

        if (! $this->sameOrigin($previous, $application)) {
            return false;
        }

        if ($this->sameUrl($previous, $current)) {
            return false;
        }

        $path = $previous['path'] ?? '/';

        if ($path === '/' || Str::contains($path, ['/create', '/edit']) || Str::startsWith($path, ['/api/', '/_debugbar/'])) {
            return false;
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($previousUrl, 'GET'));
        } catch (HttpExceptionInterface) {
            return false;
        }

        $routeName = $route->getName();

        return $routeName === null || ! Str::endsWith($routeName, [
            '.chart-data',
            '.download',
            '.avatar',
            '.destroy-avatar',
            '.create',
            '.edit',
        ]);
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int|string}  $left
     * @param  array{scheme?: string, host?: string, port?: int|string}  $right
     */
    private function sameOrigin(array $left, array $right): bool
    {
        return ($left['scheme'] ?? null) === ($right['scheme'] ?? null)
            && ($left['host'] ?? null) === ($right['host'] ?? null)
            && ($left['port'] ?? null) === ($right['port'] ?? null);
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int|string, path?: string, query?: string}  $left
     * @param  array{scheme?: string, host?: string, port?: int|string, path?: string, query?: string}  $right
     */
    private function sameUrl(array $left, array $right): bool
    {
        return ($left['scheme'] ?? null) === ($right['scheme'] ?? null)
            && ($left['host'] ?? null) === ($right['host'] ?? null)
            && ($left['port'] ?? null) === ($right['port'] ?? null)
            && ($left['path'] ?? '/') === ($right['path'] ?? '/')
            && ($left['query'] ?? null) === ($right['query'] ?? null);
    }
}
