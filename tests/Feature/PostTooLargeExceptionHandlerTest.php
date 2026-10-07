<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Tests\TestCase;

class PostTooLargeExceptionHandlerTest extends TestCase
{
    public function test_it_renders_a_friendly_page_for_large_form_posts(): void
    {
        $request = Request::create('/superadmin/logistics/shipments', 'POST', [], [], [], [
            'HTTP_REFERER' => url('/superadmin/logistics/bulk-check-in'),
        ]);

        $response = app(ExceptionHandler::class)->render($request, new PostTooLargeException());

        $this->assertSame(413, $response->getStatusCode());
        $this->assertStringContainsString('Shipment intake files are too large', $response->getContent());
        $this->assertStringContainsString('Total form request limit', $response->getContent());
    }

    public function test_it_returns_json_for_large_api_posts(): void
    {
        $request = Request::create('/api/test', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = app(ExceptionHandler::class)->render($request, new PostTooLargeException());

        $this->assertSame(413, $response->getStatusCode());
        $this->assertStringContainsString('too large', $response->getContent());
        $this->assertStringContainsString('documents', $response->getContent());
    }
}
