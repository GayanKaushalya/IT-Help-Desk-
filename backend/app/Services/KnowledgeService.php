<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class KnowledgeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{search:?string, topic:?string, published:?bool, per_page:?int}  $filters
     * @return LengthAwarePaginator<int, KnowledgeArticle>
     */
    public function paginate(User $viewer, array $filters, bool $manage): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 50));

        $query = KnowledgeArticle::query()->with('author');

        if (! $manage) {
            $query->published();
        } elseif (($filters['published'] ?? null) === true) {
            $query->published();
        } elseif (($filters['published'] ?? null) === false) {
            $query->where('is_published', false);
        }

        if (filled($filters['search'] ?? null)) {
            $term = str_replace(['%', '_'], '', (string) $filters['search']);
            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('title', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('topic', 'like', $like);
                });
            }
        }

        if (filled($filters['topic'] ?? null)) {
            $query->where('topic', $filters['topic']);
        }

        return $query->orderByDesc('updated_at')->paginate($perPage);
    }

    public function show(User $viewer, KnowledgeArticle $article, bool $manage): KnowledgeArticle
    {
        if (! $manage && ! $article->is_published) {
            abort(404);
        }

        return $article->load('author');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): KnowledgeArticle
    {
        $article = KnowledgeArticle::query()->create([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug((string) $data['title']),
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'],
            'topic' => $data['topic'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? false),
            'author_id' => $actor->id,
        ]);

        $this->audit->log(
            event: 'knowledge.created',
            subject: $article,
            newValues: ['title' => $article->title, 'is_published' => $article->is_published],
            description: $actor->username.' created knowledge article '.$article->title,
        );

        return $article->load('author');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, KnowledgeArticle $article, array $data): KnowledgeArticle
    {
        $before = $article->only(['title', 'summary', 'topic', 'is_published']);

        $article->fill([
            'title' => $data['title'] ?? $article->title,
            'summary' => array_key_exists('summary', $data) ? $data['summary'] : $article->summary,
            'body' => $data['body'] ?? $article->body,
            'topic' => array_key_exists('topic', $data) ? $data['topic'] : $article->topic,
            'is_published' => array_key_exists('is_published', $data) ? (bool) $data['is_published'] : $article->is_published,
        ])->save();

        $this->audit->log(
            event: 'knowledge.updated',
            subject: $article,
            oldValues: $before,
            newValues: $article->only(['title', 'summary', 'topic', 'is_published']),
            description: $actor->username.' updated knowledge article '.$article->title,
        );

        return $article->load('author');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? $base : 'article';
        $candidate = $base;
        $suffix = 2;

        while (KnowledgeArticle::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
