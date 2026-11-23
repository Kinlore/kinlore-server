<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * openapi.yaml is written by hand. This keeps its paths equal to the server's routes.
 */
class OpenApiRoutesTest extends TestCase
{
    public function test_openapi_lists_exactly_the_routes_of_the_server(): void
    {
        $documented = [];
        $path = null;
        foreach (file(base_path('openapi.yaml')) as $line) {
            if (preg_match('#^  (/\S*):\s*$#', $line, $m)) {
                $path = $m[1];
            } elseif (preg_match('#^\S#', $line)) {
                $path = null;
            } elseif ($path !== null && preg_match('#^    (get|post|put|patch|delete):#', $line, $m)) {
                $documented[] = strtoupper($m[1]).' '.$this->normalize($path);
            }
        }

        $served = [];
        foreach (Route::getRoutes() as $route) {
            $uri = '/'.ltrim($route->uri(), '/');
            if (str_starts_with($uri, '/api')) {
                $uri = substr($uri, 4) ?: '/';
            } elseif (! in_array($uri, ['/', '/setup'], true) && ! str_starts_with($uri, '/invite')) {
                continue; // framework routes such as /up and /storage
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $served[] = $method.' '.$this->normalize($uri);
            }
        }

        // PUT and PATCH of apiResource are one documented operation each; compare as sets.
        sort($documented);
        sort($served);
        $this->assertSame([], array_values(array_diff($served, $documented)), 'routes missing in openapi.yaml');
        $this->assertSame([], array_values(array_diff($documented, $served)), 'openapi.yaml paths with no route');
    }

    private function normalize(string $path): string
    {
        return preg_replace('#\{[^}]+\}#', '{}', $path);
    }
}
