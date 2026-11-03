<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** A test that forgets to fake an outside service fails with a clear message instead of calling the real one. */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
