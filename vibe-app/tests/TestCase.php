<?php

namespace Tests;

use App\Http\Middleware\RequireActionPin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** Set to true in a test that checks what happens when the reason and PIN are missing. */
    protected bool $withoutActionPin = false;

    /** A test that forgets to fake an outside service fails with a clear message instead of calling the real one. */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * An edit or delete of a record asks for a reason and the user's PIN (RequireActionPin). The tests send the factory user's PIN with such a request,
     * so each test only describes what it checks; a test that is about the PIN sets $withoutActionPin.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (! $this->withoutActionPin && ! in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) && ! isset($parameters['pin']) && $this->needsActionPin($method, $uri, $parameters)) {
            $parameters = (array) $parameters + ['reason' => 'Test', 'pin' => '123456'];
            if (is_string($content) && is_array($json = json_decode($content, true))) {
                $content = json_encode($json + ['reason' => 'Test', 'pin' => '123456']);
            }
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    private function needsActionPin(string $method, string $uri, mixed $parameters): bool
    {
        try {
            $route = app('router')->getRoutes()->match(Request::create($uri, strtoupper((string) (((array) $parameters)['_method'] ?? $method))));
        } catch (\Throwable) {
            return false;
        }

        return $route->getName() !== null && in_array($route->getName(), RequireActionPin::ROUTES, true);
    }
}
