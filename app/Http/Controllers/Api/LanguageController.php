<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\LanguageRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLanguageRequest;
use App\Http\Requests\UpdateLanguageRequest;
use App\Http\Resources\LanguageResource;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LanguageController extends Controller
{
    public function __construct(private readonly LanguageRepository $languages)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return LanguageResource::collection($this->languages->all());
    }

    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $language = $this->languages->create(
            $request->string('code')->toString(),
            $request->string('name')->toString(),
            $request->exists('is_active') ? $request->boolean('is_active') : true,
        );

        return $this->createdResponse(new LanguageResource($language));
    }

    public function show(Language $language): LanguageResource
    {
        return new LanguageResource($language);
    }

    public function update(UpdateLanguageRequest $request, Language $language): LanguageResource
    {
        $language = $this->languages->update(
            $language,
            $request->safe()->only(['name', 'is_active']),
        );

        return new LanguageResource($language);
    }
}
