<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    protected int $page;
    protected int $limit;
    protected int $total;
    protected bool $hasMore;

    public function __construct($resource, int $page = 1, int $limit = 20, int $total = 0, bool $hasMore = false)
    {
        parent::__construct($resource);
        $this->page = $page;
        $this->limit = $limit;
        $this->total = $total;
        $this->hasMore = $hasMore;
    }

    // envelope paginasi custom sesuai kontrak frontend
    public function toArray(Request $request): array
    {
        return [
            'items' => ProductResource::collection($this->collection),
            'total' => $this->total,
            'page' => $this->page,
            'limit' => $this->limit,
            'hasMore' => $this->hasMore,
        ];
    }
}
