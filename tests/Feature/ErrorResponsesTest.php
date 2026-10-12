<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\ApiTestCase;

class ErrorResponsesTest extends ApiTestCase
{
    public function test_missing_records_return_a_friendly_404_without_class_names(): void
    {
        $headers = $this->bearer($this->token());

        $this->getJson('/api/translations/999', $headers)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Translation 999 was not found.']);

        $this->getJson('/api/languages/xx', $headers)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Language xx was not found.']);

        $this->deleteJson('/api/translations/999', [], $headers)
            ->assertNotFound()
            ->assertJsonPath('message', 'Translation 999 was not found.');
    }

    public function test_unknown_endpoints_and_wrong_methods_do_not_leak_route_details(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'The requested endpoint does not exist.']);

        $response = $this->patchJson('/api/export/en')
            ->assertStatus(405)
            ->assertExactJson(['message' => 'The PATCH method is not allowed here. Allowed: GET, HEAD.']);
        $this->assertSame('GET, HEAD', $response->headers->get('Allow'));
    }

    public function test_web_routes_keep_the_default_handling(): void
    {
        $this->get('/does-not-exist')->assertNotFound();
    }
}
