<?php

namespace App\Services;

use App\Actions\Comment\CreateCommentAction;
use App\Actions\Comment\DeleteCommentAction;
use App\Actions\Comment\ListCommentsAction;
use App\Actions\Comment\ToggleCommentLikeAction;
use App\Actions\Comment\UpdateCommentTextAction;
use App\DTOs\Comment\CreateCommentData;
use App\DTOs\Comment\ToggleCommentLikeData;
use App\DTOs\Comment\UpdateCommentData;
use App\DTOs\Shared\ViewerContextData;
use App\Models\MemoryComment;
use Illuminate\Support\Collection;

class CommentService
{
  public function __construct(
    private readonly ListCommentsAction $listComments,
    private readonly CreateCommentAction $createComment,
    private readonly UpdateCommentTextAction $updateComment,
    private readonly DeleteCommentAction $deleteComment,
    private readonly ToggleCommentLikeAction $toggleLike,
  ) {}

  public function listComments(string $memoryExternalId): Collection
  {
    return $this->listComments->execute($memoryExternalId);
  }

  public function addComment(CreateCommentData $data): MemoryComment
  {
    return $this->createComment->execute($data);
  }

  public function updateComment(UpdateCommentData $data, ViewerContextData $viewer): MemoryComment
  {
    return $this->updateComment->execute($data, $viewer);
  }

  public function deleteComment(string $memoryExternalId, string $commentExternalId, ViewerContextData $viewer): array
  {
    return $this->deleteComment->execute($memoryExternalId, $commentExternalId, $viewer);
  }

  public function toggleLike(ToggleCommentLikeData $data): array
  {
    return $this->toggleLike->execute($data);
  }
}
