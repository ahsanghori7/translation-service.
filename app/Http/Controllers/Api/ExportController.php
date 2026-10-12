<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\CdnCachePolicy;
use App\Contracts\ExportsTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportTranslationsRequest;
use App\Support\EtagMatcher;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __invoke(
        ExportTranslationsRequest $request,
        string $locale,
        ExportsTranslations $exports,
        CdnCachePolicy $cdn,
        EtagMatcher $etags,
    ): Response {
        $structure = $request->structure();
        $scope = $exports->scope($locale, $request->tags(), $request->tagMode());
        $headers = $cdn->headers($scope, $structure);

        if ($etags->matches($request->headers->get('If-None-Match'), $scope->etag($structure))) {
            return response('', 304, $headers);
        }

        $json = $exports->render($scope, $structure)->encodedBody();
        $cdnUrl = $cdn->publicUrl($request);
        if ($cdnUrl !== null) {
            $json = $this->withCdnUrl($json, $cdnUrl);
        }

        return JsonResponse::fromJsonString($json, 200, $headers);
    }

    private function withCdnUrl(string $json, string $cdnUrl): string
    {
        $encodedUrl = json_encode($cdnUrl, JSON_THROW_ON_ERROR);

        return substr($json, 0, -1).',"cdn_url":'.$encodedUrl.'}';
    }
}
