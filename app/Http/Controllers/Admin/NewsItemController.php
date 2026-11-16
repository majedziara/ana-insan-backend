<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Admin\SaveNewsItemRequest;
use App\Http\Requests\WebsiteIndexRequest;
use App\Http\Resources\Admin\NewsItemResource;
use App\Http\Resources\PaginatedResource;
use App\Models\NewsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NewsItemController extends ApiController
{
    public function index(WebsiteIndexRequest $request): JsonResponse
    {
        return $this->success(new PaginatedResource(NewsItem::orderByDesc('published_at')->orderByDesc('id')->paginate($request->validated('per_page', 15)), NewsItemResource::class));
    }

    public function store(SaveNewsItemRequest $request): JsonResponse
    {
        return $this->success(new NewsItemResource($this->save(new NewsItem, $request->validated())), __('content.created'), 201);
    }

    public function show(NewsItem $newsItem): JsonResponse
    {
        return $this->success(new NewsItemResource($newsItem));
    }

    public function update(SaveNewsItemRequest $request, NewsItem $newsItem): JsonResponse
    {
        return $this->success(new NewsItemResource($this->save($newsItem, $request->validated())));
    }

    public function destroy(NewsItem $newsItem): JsonResponse
    {
        $newsItem->delete();

        return $this->success(null, __('content.deleted'));
    }

    /** @param array{text?: string, text_en?: ?string, duration_days?: int, is_active?: bool} $data */
    private function save(NewsItem $newsItem, array $data): NewsItem
    {
        return DB::transaction(function () use ($newsItem, $data): NewsItem {
            $item = $newsItem->exists ? NewsItem::whereKey($newsItem->id)->lockForUpdate()->firstOrFail() : $newsItem;
            $item->fill($data);
            if (isset($data['duration_days'])) {
                $item->published_at = now();
                $item->expires_at = now()->addDays($data['duration_days']);
            }
            $item->save();

            return $item;
        });
    }
}
