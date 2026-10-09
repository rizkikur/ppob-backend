<?php

namespace App\Domain\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocsController extends Controller
{
    /**
     * Display the interactive API Documentation portal.
     */
    public function index(Request $request)
    {
        return view('docs.index', [
            'swaggerSpecUrl' => url('/docs/openapi.yaml'),
            'yamlDownloadUrl' => url('/docs/openapi.yaml?download=1'),
            'postmanCollectionUrl' => url('/docs/postman/collection'),
            'postmanEnvironmentUrl' => url('/docs/postman/environment'),
            'appVersion' => 'v0.12.0',
        ]);
    }

    /**
     * Display the v2 Claude-styled documentation portal.
     */
    public function v2(Request $request)
    {
        return view('docs.v2', [
            'swaggerSpecUrl' => url('/docs/openapi.yaml'),
            'yamlDownloadUrl' => url('/docs/openapi.yaml?download=1'),
            'postmanCollectionUrl' => url('/docs/postman/collection'),
            'postmanEnvironmentUrl' => url('/docs/postman/environment'),
            'appVersion' => 'v0.12.0',
        ]);
    }

    /**
     * Serve the raw OpenAPI YAML file or prompt download.
     */
    public function spec(Request $request)
    {
        $path = base_path('docs/openapi.yaml');
        if (! file_exists($path)) {
            $path = public_path('docs/openapi.yaml');
        }

        if (! file_exists($path)) {
            abort(404, 'OpenAPI specification file not found.');
        }

        if ($request->query('download') === '1') {
            return response()->download($path, 'ppob-backend-openapi.yaml', [
                'Content-Type' => 'text/yaml; charset=utf-8',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/yaml; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Download Postman Collection JSON.
     */
    public function postmanCollection(): BinaryFileResponse
    {
        $path = base_path('docs/PPOB_Backend.postman_collection.json');
        if (! file_exists($path)) {
            $path = public_path('docs/PPOB_Backend.postman_collection.json');
        }

        if (! file_exists($path)) {
            abort(404, 'Postman collection file not found.');
        }

        return response()->download($path, 'PPOB_Backend.postman_collection.json', [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Download Postman Environment JSON.
     */
    public function postmanEnvironment(): BinaryFileResponse
    {
        $path = base_path('docs/PPOB_Local.postman_environment.json');
        if (! file_exists($path)) {
            $path = public_path('docs/PPOB_Local.postman_environment.json');
        }

        if (! file_exists($path)) {
            abort(404, 'Postman environment file not found.');
        }

        return response()->download($path, 'PPOB_Local.postman_environment.json', [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
