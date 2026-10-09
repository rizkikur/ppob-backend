<?php

namespace Tests\Feature\Docs;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_root_returns_docs_portal(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('PPOB Backend Engine');
    }

    public function test_docs_portal_loads_successfully(): void
    {
        $response = $this->get('/docs');

        $response->assertStatus(200);
        $response->assertSee('PPOB Backend Engine');
        $response->assertSee('Interactive Swagger UI');
        $response->assertSee('OpenAPI 3.1');
        $response->assertSee('swagger-ui-bundle.js', false);
    }

    public function test_openapi_yaml_spec_endpoint_returns_valid_spec(): void
    {
        $response = $this->get('/docs/openapi.yaml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/yaml; charset=utf-8');
        $response->assertSee('openapi: 3.1.0', false);
        $response->assertSee('title: PPOB Backend API', false);
        $response->assertSee('/home:', false);
        $response->assertSee('/products/operator-prefix:', false);
        $response->assertSee('/wallet/channels:', false);
    }

    public function test_openapi_yaml_download_parameter_triggers_attachment(): void
    {
        $response = $this->get('/docs/openapi.yaml?download=1');

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('ppob-backend-openapi.yaml', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_postman_collection_download_endpoint_works(): void
    {
        $response = $this->get('/docs/postman/collection');

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('PPOB_Backend.postman_collection.json', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_postman_environment_download_endpoint_works(): void
    {
        $response = $this->get('/docs/postman/environment');

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('PPOB_Local.postman_environment.json', (string) $response->headers->get('Content-Disposition'));
    }
}
