<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Knowledge\IndexKnowledgeRequest;
use App\Http\Requests\Api\V1\Knowledge\StoreKnowledgeRequest;
use App\Http\Requests\Api\V1\Knowledge\UpdateKnowledgeRequest;
use App\Http\Resources\V1\KnowledgeArticleResource;
use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Services\KnowledgeService;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class KnowledgeArticleController extends Controller
{
    public function __construct(private readonly KnowledgeService $knowledge) {}

    public function index(IndexKnowledgeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $manage = $user->hasPermission('knowledge.manage');

        return ApiResponse::paginated(
            KnowledgeArticleResource::collection(
                $this->knowledge->paginate($user, $request->filters(), $manage)
            ),
        );
    }

    public function show(IndexKnowledgeRequest $request, KnowledgeArticle $knowledge_article): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $manage = $user->hasPermission('knowledge.manage');

        return ApiResponse::success(
            (new KnowledgeArticleResource($this->knowledge->show($user, $knowledge_article, $manage)))->resolve(),
        );
    }

    public function store(StoreKnowledgeRequest $request): JsonResponse
    {
        $article = $this->knowledge->create($request->user(), $request->payload());

        return ApiResponse::created(
            (new KnowledgeArticleResource($article))->resolve(),
            'The article has been created.',
        );
    }

    public function update(UpdateKnowledgeRequest $request, KnowledgeArticle $knowledge_article): JsonResponse
    {
        if (! $request->user()?->hasPermission('knowledge.manage')) {
            throw new AuthorizationException;
        }

        $updated = $this->knowledge->update($request->user(), $knowledge_article, $request->payload());

        return ApiResponse::success(
            (new KnowledgeArticleResource($updated))->resolve(),
            'The article has been updated.',
        );
    }
}
