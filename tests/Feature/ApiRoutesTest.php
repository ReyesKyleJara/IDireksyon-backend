<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiRoutesTest extends TestCase
{
    /**
     * @dataProvider apiCollectionRoutesProvider
     */
    public function test_api_collection_routes_return_json(string $endpoint): void
    {
        $response = $this->getJson($endpoint);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function apiCollectionRoutesProvider(): array
    {
        return [
            'users' => ['/api/users'],
            'government_ids' => ['/api/government-ids'],
            'requirements' => ['/api/requirements'],
            'government_offices' => ['/api/government-offices'],
        ];
    }
}
