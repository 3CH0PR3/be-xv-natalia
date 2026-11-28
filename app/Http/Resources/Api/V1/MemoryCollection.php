<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MemoryCollection extends ResourceCollection
{
  public $collects = MemoryResource::class;

  public function toArray(Request $request): array
  {
    return [
      'data' => $this->collection,
    ];
  }

  public function with(Request $request): array
  {
    return [
      'success' => true,
      'code' => 200,
    ];
  }

  public function paginationInformation($request, array $paginated, array $default): array
  {
    // Cursor pagination — no hay total ni lastPage
    $nextCursor = $paginated['next_cursor'] ?? null;
    $hasMore = $paginated['next_page_url'] !== null;

    return [
      'meta' => [
        'perPage'    => $paginated['per_page'],
        'hasMore'    => $hasMore,
        'nextCursor' => $nextCursor,
        // Compatibilidad con clientes que leen total/lastPage (retornar null)
        'currentPage' => null,
        'lastPage'    => null,
        'total'       => null,
      ],
    ];
  }
}
