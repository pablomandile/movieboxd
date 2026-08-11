<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Favorite;
use App\Models\ListModel;
use App\Models\Review;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use App\Services\ProfileStatsService;
use App\Services\Tmdb\Dto\TitleCard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public const TABS = ['diary', 'reviews', 'watchlist', 'lists', 'likes', 'network', 'stats'];

    public function show(Request $request, User $user): Response
    {
        $titles = $user->watchedTitles()
            ->orderByDesc('watched_titles.created_at')
            ->paginate(48);

        return Inertia::render('profile/Films', [
            'profile' => $this->profileProps($request, $user),
            'titles' => $titles->through(fn (Title $title) => TitleCard::fromModel($title)),
        ]);
    }

    public function tab(Request $request, User $user, string $tab): Response
    {
        abort_unless(in_array($tab, self::TABS, true), 404);

        return $this->{$tab}($request, $user);
    }

    protected function diary(Request $request, User $user): Response
    {
        $year = $request->integer('year') ?: null;

        $entries = $user->diaryEntries()
            ->inDiary()
            ->when($year, fn ($query) => $query->whereYear('watched_on', $year))
            ->with(['review', 'loggable' => fn ($morphTo) => $morphTo->morphWith([
                Season::class => ['title'],
                Episode::class => ['title'],
            ])])
            ->orderByDesc('watched_on')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('profile/Diary', [
            'profile' => $this->profileProps($request, $user),
            'entries' => $entries->through(fn ($entry) => DiaryEntryController::entryProps($entry)),
            'year' => $year,
            // SUBSTR en vez de YEAR(): portable entre MySQL y el SQLite de los tests
            'years' => $user->diaryEntries()
                ->inDiary()
                ->selectRaw('SUBSTR(watched_on, 1, 4) as year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->map(fn ($value) => (int) $value),
        ]);
    }

    protected function stats(Request $request, User $user): Response
    {
        return Inertia::render('profile/Stats', [
            'profile' => $this->profileProps($request, $user),
            'stats' => Inertia::defer(fn () => app(ProfileStatsService::class)->for($user)),
        ]);
    }

    protected function reviews(Request $request, User $user): Response
    {
        $reviews = $user->reviews()
            ->with(['user', 'diaryEntry', 'reviewable' => fn ($morphTo) => $morphTo->morphWith([
                Season::class => ['title'],
                Episode::class => ['title'],
            ])])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Likes del viewer en una sola query para toda la página
        $likedIds = ReviewController::likedReviewIds($request->user()?->id, $reviews->getCollection()->pluck('id'));

        return Inertia::render('profile/Reviews', [
            'profile' => $this->profileProps($request, $user),
            'reviews' => $reviews->through(fn (Review $review) => ReviewController::reviewProps($review, $request->user()?->id, $likedIds) + [
                'subject' => ReviewController::subjectProps($review->reviewable),
            ]),
        ]);
    }

    protected function watchlist(Request $request, User $user): Response
    {
        $titles = $user->watchlist()
            ->orderByDesc('watchlist_items.created_at')
            ->paginate(48);

        return Inertia::render('profile/Watchlist', [
            'profile' => $this->profileProps($request, $user),
            'titles' => $titles->through(fn (Title $title) => TitleCard::fromModel($title)),
        ]);
    }

    protected function lists(Request $request, User $user): Response
    {
        $lists = ListModel::query()
            ->where('user_id', $user->id)
            ->when($request->user()?->id !== $user->id, fn ($q) => $q->where('is_public', true))
            ->with(['user', 'items' => fn ($q) => $q->with('title')->limit(5)])
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return Inertia::render('profile/Lists', [
            'profile' => $this->profileProps($request, $user),
            'lists' => $lists->through(fn ($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'isRanked' => $list->is_ranked,
                'isPublic' => $list->is_public,
                'itemsCount' => $list->items_count,
                'likesCount' => $list->likes_count,
                'posters' => $list->items->map(fn ($item) => $item->title->poster_path)->filter()->values(),
                'url' => route('lists.show', ['username' => $user->username, 'list' => $list->id]),
            ]),
        ]);
    }

    protected function likes(Request $request, User $user): Response
    {
        $titles = Title::query()
            ->join('likes', function ($join) use ($user) {
                $join->on('likes.likeable_id', '=', 'titles.id')
                    ->where('likes.likeable_type', 'title')
                    ->where('likes.user_id', $user->id);
            })
            ->orderByDesc('likes.created_at')
            ->select('titles.*')
            ->paginate(48);

        return Inertia::render('profile/Likes', [
            'profile' => $this->profileProps($request, $user),
            'titles' => $titles->through(fn (Title $title) => TitleCard::fromModel($title)),
        ]);
    }

    protected function network(Request $request, User $user): Response
    {
        $mapUser = fn (User $member) => $member->only('name', 'username', 'avatar_path');

        return Inertia::render('profile/Network', [
            'profile' => $this->profileProps($request, $user),
            'followers' => $user->followers()->latest('follows.created_at')->limit(100)->get()->map($mapUser),
            'following' => $user->following()->latest('follows.created_at')->limit(100)->get()->map($mapUser),
        ]);
    }

    protected function profileProps(Request $request, User $user): array
    {
        $viewer = $request->user();

        return [
            'name' => $user->name,
            'username' => $user->username,
            'avatarPath' => $user->avatar_path,
            'bio' => $user->bio,
            'counts' => [
                'watched' => $user->watchedTitles()->count(),
                'thisYear' => $user->diaryEntries()->whereYear('watched_on', now()->year)->count(),
                'followers' => $user->followers()->count(),
                'following' => $user->following()->count(),
            ],
            'favorites' => $user->favorites()->with('title')->get()
                ->map(fn (Favorite $favorite) => TitleCard::fromModel($favorite->title)),
            'isOwn' => $viewer?->id === $user->id,
            'isFollowing' => $viewer !== null && $viewer->id !== $user->id && $viewer->isFollowing($user),
        ];
    }
}
