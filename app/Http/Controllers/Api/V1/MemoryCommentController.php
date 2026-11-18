<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Shared\ViewerContextData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommentRequest;
use App\Http\Requests\Api\V1\ToggleCommentLikeRequest;
use App\Http\Requests\Api\V1\UpdateCommentRequest;
use App\Http\Resources\Api\V1\MemoryCommentResource;
use App\Services\CommentService;
use App\Traits\Api\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemoryCommentController extends Controller
{
    use ApiResponser;

    public function __construct(
        private readonly CommentService $service,
    ) {}

    public function index(string $externalId): JsonResponse
    {
        $comments = $this->service->listComments($externalId);
        return $this->sendResponse(
            MemoryCommentResource::collection($comments),
            'Comentarios obtenidos.',
            200
        );
    }

    public function store(string $externalId, StoreCommentRequest $request): JsonResponse
    {
        $comment = $this->service->addComment($request->toDto($externalId));

        return $this->sendResponse(
            new MemoryCommentResource($comment),
            'Comentario creado exitosamente.',
            Response::HTTP_CREATED
        );
    }

    public function update(string $externalId, string $commentExternalId, UpdateCommentRequest $request): JsonResponse
    {
        $comment = $this->service->updateComment(
            $request->toDto($externalId, $commentExternalId),
            ViewerContextData::fromRequest($request)
        );

        return $this->sendResponse(
            new MemoryCommentResource($comment),
            'Comentario actualizado exitosamente.',
            200
        );
    }

    public function destroy(string $externalId, string $commentExternalId, Request $request): JsonResponse
    {
        $result = $this->service->deleteComment(
            $externalId,
            $commentExternalId,
            ViewerContextData::fromRequest($request)
        );

        return $this->sendResponse($result, 'Comentario eliminado.', 200);
    }

    public function toggleLike(string $externalId, string $commentExternalId, ToggleCommentLikeRequest $request): JsonResponse
    {
        $result = $this->service->toggleLike($request->toDto($externalId, $commentExternalId));
        return $this->sendResponse($result, 'Like de comentario actualizado.', 200);
    }
}
