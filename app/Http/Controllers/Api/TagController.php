<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\TagRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function __construct(private readonly TagRepository $tags)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection($this->tags->all());
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $this->tags->create($request->string('name')->toString());

        return $this->createdResponse(new TagResource($tag));
    }
}
