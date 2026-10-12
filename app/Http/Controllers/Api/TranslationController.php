<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ManagesTranslations;
use App\Contracts\SearchesTranslations;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchTranslationsRequest;
use App\Http\Requests\StoreTranslationRequest;
use App\Http\Requests\UpdateTranslationRequest;
use App\Http\Resources\TranslationResource;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TranslationController extends Controller
{
    public function __construct(
        private readonly ManagesTranslations $translations,
        private readonly SearchesTranslations $search,
    ) {
    }

    public function index(SearchTranslationsRequest $request): AnonymousResourceCollection
    {
        return TranslationResource::collection(
            $this->search->search($request->toQuery()),
        );
    }

    public function store(StoreTranslationRequest $request): JsonResponse
    {
        $translation = $this->translations->create($request->toData());

        return $this->createdResponse(new TranslationResource($translation));
    }

    public function show(Translation $translation): TranslationResource
    {
        $translation->load(['translationKey.tags', 'language']);

        return new TranslationResource($translation);
    }

    public function update(UpdateTranslationRequest $request, Translation $translation): TranslationResource
    {
        return new TranslationResource(
            $this->translations->update($translation, $request->toData()),
        );
    }

    public function destroy(Translation $translation): Response
    {
        $this->translations->delete($translation);

        return response()->noContent();
    }
}
