<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BehindCloudflareTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_use_https_when_the_app_is_reached_through_a_cloudflare_tunnel(): void
    {
        // cloudflared reaches the app over plain http on this computer and passes on how the visitor connected.
        $html = $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'procure.example.com', 'X-Forwarded-For' => '203.0.113.9'])->assertOk()->getContent();

        $this->assertStringContainsString('https://procure.example.com/css/app.css', $html);
        $this->assertStringNotContainsString('http://procure.example.com', $html);
    }

    public function test_the_visitors_address_is_the_one_cloudflare_reports(): void
    {
        $this->get('/login', ['X-Forwarded-For' => '203.0.113.9'])->assertOk();

        $this->assertSame('203.0.113.9', request()->ip());
    }

    public function test_plain_http_still_works_for_local_use(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('http://localhost/css/app.css', $html);
    }
}
