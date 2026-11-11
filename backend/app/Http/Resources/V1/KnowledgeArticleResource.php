<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\KnowledgeArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KnowledgeArticle */
class KnowledgeArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $this->body,
            'topic' => $this->topic,
            'is_published' => $this->is_published,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'full_name' => $this->author?->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
