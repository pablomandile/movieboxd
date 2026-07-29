<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Episode;
use App\Models\Like;
use App\Models\Review;
use App\Models\Season;
use App\Models\Title;
use App\Support\PageMeta;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function show(Request $request, Review $review): Response
    {
        $review->load(['user', 'reviewable', 'diaryEntry']);

        // El commentable de todos los comentarios es esta misma review:
        // se setea la relación para que la policy no dispare una query por comentario
        $comments = $review->comments()->with('user')->orderBy('created_at')->get()
            ->each(fn (Comment $comment) => $comment->setRelation('commentable', $review));

        $subject = self::subjectProps($review->reviewable);

        return Inertia::render('Reviews/Show', [
            'review' => self::reviewProps($review, $request->user()?->id),
            'subject' => $subject,
            'meta' => PageMeta::make(
                $subject ? "{$review->user->name} — {$subject['name']}" : $review->user->name,
                $review->contains_spoilers ? null : $review->body,
                $subject['posterPath'] ?? null,
                'article'
            ),
            'comments' => $comments->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'createdAt' => $comment->created_at->toIso8601String(),
                'user' => $comment->user->only('name', 'username', 'avatar_path'),
                'canDelete' => $request->user()?->can('delete', $comment) ?? false,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reviewable_type' => ['required', Rule::in(['title', 'season', 'episode'])],
            'reviewable_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:20000'],
            'contains_spoilers' => ['boolean'],
        ]);

        $class = Relation::getMorphedModel($data['reviewable_type']);
        $reviewable = $class::findOrFail($data['reviewable_id']);

        Review::create([
            'user_id' => $request->user()->id,
            'reviewable_type' => $data['reviewable_type'],
            'reviewable_id' => $reviewable->id,
            'body' => $data['body'],
            'contains_spoilers' => $request->boolean('contains_spoilers'),
        ]);

        return back();
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        Gate::authorize('update', $review);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'contains_spoilers' => ['boolean'],
        ]);

        $review->update([
            'body' => $data['body'],
            'contains_spoilers' => $request->boolean('contains_spoilers'),
        ]);

        return back();
    }

    public function destroy(Review $review): RedirectResponse
    {
        Gate::authorize('delete', $review);

        $review->delete();

        return redirect()->to(self::subjectProps($review->reviewable)['url'] ?? route('home'));
    }

    public function toggleLike(Request $request, Review $review): RedirectResponse
    {
        $keys = [
            'user_id' => $request->user()->id,
            'likeable_type' => 'review',
            'likeable_id' => $review->id,
        ];

        $existing = Like::where($keys)->first();

        $existing === null ? Like::create($keys) : $existing->delete();

        return back();
    }

    /**
     * Reviews populares de un sujeto (ordenadas por likes) para páginas de título.
     */
    public static function popularFor($reviewable, ?int $viewerId, int $limit = 6): array
    {
        $reviews = $reviewable->reviews()
            ->with(['user', 'diaryEntry'])
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $likedIds = self::likedReviewIds($viewerId, $reviews->pluck('id'));

        return $reviews
            ->map(fn (Review $review) => self::reviewProps($review, $viewerId, $likedIds))
            ->all();
    }

    /**
     * Ids de reviews likeadas por el viewer, en una sola query (evita un
     * exists() por review al listar).
     */
    public static function likedReviewIds(?int $viewerId, $reviewIds): Collection
    {
        if ($viewerId === null) {
            return collect();
        }

        return Like::where('user_id', $viewerId)
            ->where('likeable_type', 'review')
            ->whereIn('likeable_id', $reviewIds)
            ->pluck('likeable_id');
    }

    /**
     * Forma compartida de una review para el frontend. Con $likedIds
     * precargados no dispara queries por review.
     */
    public static function reviewProps(Review $review, ?int $viewerId = null, ?Collection $likedIds = null): array
    {
        return [
            'id' => $review->id,
            'body' => $review->body,
            'containsSpoilers' => $review->contains_spoilers,
            'likesCount' => $review->likes_count,
            'commentsCount' => $review->comments_count,
            'createdAt' => $review->created_at->toIso8601String(),
            'rating' => $review->diaryEntry?->rating,
            'watchedOn' => $review->diaryEntry?->watched_on?->toDateString(),
            'user' => $review->user->only('name', 'username', 'avatar_path'),
            'isOwn' => $viewerId !== null && $review->user_id === $viewerId,
            'likedByViewer' => $viewerId !== null && (
                $likedIds !== null
                    ? $likedIds->contains($review->id)
                    : $review->likes()->where('user_id', $viewerId)->exists()
            ),
            'url' => route('reviews.show', $review),
        ];
    }

    public static function subjectProps($reviewable): ?array
    {
        return match (true) {
            $reviewable instanceof Title => [
                'name' => $reviewable->localized_title,
                'year' => $reviewable->year,
                'posterPath' => $reviewable->poster_path,
                'url' => route($reviewable->isMovie() ? 'films.show' : 'shows.show', $reviewable),
            ],
            $reviewable instanceof Season => [
                'name' => "{$reviewable->title->localized_title} – {$reviewable->localized_title}",
                'year' => $reviewable->air_date?->year,
                'posterPath' => $reviewable->poster_path ?? $reviewable->title->poster_path,
                'url' => route('seasons.show', ['title' => $reviewable->title->slug, 'seasonNumber' => $reviewable->season_number]),
            ],
            $reviewable instanceof Episode => [
                'name' => "{$reviewable->title->localized_title} {$reviewable->code}: {$reviewable->localized_title}",
                'year' => $reviewable->air_date?->year,
                'posterPath' => $reviewable->still_path ?? $reviewable->title->poster_path,
                'url' => route('episodes.show', [
                    'title' => $reviewable->title->slug,
                    'seasonNumber' => $reviewable->season_number,
                    'episodeNumber' => $reviewable->episode_number,
                ]),
            ],
            default => null,
        };
    }
}
