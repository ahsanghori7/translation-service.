<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocsController extends Controller
{
    public function ui(): View
    {
        return view('docs.swagger');
    }

    /**
     * Serves the spec with the requesting host listed first under `servers`,
     * so Swagger UI calls the same origin the docs were opened from
     * (Laragon .test host, sub-folder installs, artisan serve, Docker).
     */
    public function spec(Request $request): Response
    {
        $yaml = (string) file_get_contents(base_path('docs/openapi.yaml'));
        $current = $request->getSchemeAndHttpHost().$request->getBasePath();

        $yaml = (string) preg_replace(
            '/^servers:\R/m',
            "servers:\n  - url: '".str_replace("'", "''", $current)."'\n    description: Current host\n",
            $yaml,
            1,
        );

        return response($yaml, 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
