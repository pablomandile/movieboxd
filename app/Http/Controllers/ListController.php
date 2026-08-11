<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\ListItem;
use App\Models\ListModel;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Services\Tmdb\Dto\TitleCard;
use App\Support\PageMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ListController extends Controller
{
    public function index(): Response
    {
        $lists = ListModel::query()
            ->where('is_public', true)
            ->with(['user', 'items' => fn ($q) => $q->with('title')->limit(5)])
            ->withCount('items')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return Inertia::render('lists/Index', [
            'lists' => $lists->through(fn (ListModel $list) => $this->summaryProps($list)),
        ]);
    }

    public function show(Request $request, string $username, ListModel $list): Response
    {
        abort_unless($list->user->username === $username, 404);

        // Antes de autorizar: la policy consulta los colaboradores en listas privadas
        $list->load('collaborators');

        Gate::authorize('view', $list);

        $items = $list->items()->with('title')->get();

        $viewer = $request->user();
        $watchedIds = $viewer
            ? WatchedTitle::where('user_id', $viewer->id)
                ->whereIn('title_id', $items->pluck('title_id'))
                ->pluck('title_id')
            : collect();

        return Inertia::render('lists/Show', [
            'list' => $this->detailProps($list, $viewer?->id),
            'meta' => PageMeta::make(
                $list->name,
                $list->description,
                $items->first()?->title->poster_path,
                'article'
            ),
            'items' => $items->map(fn (ListItem $item) => [
                'id' => $item->id,
                'position' => $item->position,
                'note' => $item->note,
                'watched' => $watchedIds->contains($item->title_id),
                'card' => TitleCard::fromModel($item->title),
            ]),
            'comments' => $list->comments()->with('user')->orderBy('created_at')->get()
                ->each(fn (Comment $comment) => $comment->setRelation('commentable', $list))
                ->map(fn (Comment $comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'createdAt' => $comment->created_at->toIso8601String(),
                    'user' => $comment->user->only('name', 'username', 'avatar_path'),
                    'canDelete' => $viewer?->can('delete', $comment) ?? false,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('lists/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $list = ListModel::create($data + [
            'user_id' => $request->user()->id,
            'slug' => $this->makeSlug($request->user()->id, $data['name']),
        ]);

        return redirect()->route('lists.show', ['username' => $request->user()->username, 'list' => $list->id]);
    }

    public function edit(Request $request, ListModel $list): Response
    {
        // Los colaboradores entran a gestionar títulos; el formulario de la lista
        // se les oculta y el PUT sigue exigiendo ser dueño.
        Gate::authorize('updateItems', $list);

        $list->load('collaborators');

        return Inertia::render('lists/Edit', [
            'list' => $this->detailProps($list, $request->user()->id),
            'items' => $list->items()->with('title')->get()->map(fn (ListItem $item) => [
                'id' => $item->id,
                'position' => $item->position,
                'note' => $item->note,
                'card' => TitleCard::fromModel($item->title),
            ]),
        ]);
    }

    public function update(Request $request, ListModel $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $list->update($this->validated($request));

        return back();
    }

    public function destroy(Request $request, ListModel $list): RedirectResponse
    {
        Gate::authorize('delete', $list);

        $list->delete();

        return redirect()->route('lists.index');
    }

    public function toggleLike(Request $request, ListModel $list): RedirectResponse
    {
        Gate::authorize('view', $list);

        $keys = [
            'user_id' => $request->user()->id,
            'likeable_type' => 'list',
            'likeable_id' => $list->id,
        ];

        $existing = Like::where($keys)->first();

        $existing === null ? Like::create($keys) : $existing->delete();

        return back();
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_ranked' => ['boolean'],
            'is_public' => ['boolean'],
        ]);
    }

    protected function makeSlug(int $userId, string $name): string
    {
        $base = Str::slug($name) ?: 'lista';
        $slug = $base;
        $suffix = 2;

        while (ListModel::where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function summaryProps(ListModel $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'slug' => $list->slug,
            'description' => $list->description ? str($list->description)->limit(140)->toString() : null,
            'isRanked' => $list->is_ranked,
            'itemsCount' => $list->items_count ?? $list->items()->count(),
            'likesCount' => $list->likes_count,
            'user' => $list->user->only('name', 'username', 'avatar_path'),
            'posters' => $list->items->map(fn (ListItem $item) => $item->title->poster_path)->filter()->values(),
            'url' => route('lists.show', ['username' => $list->user->username, 'list' => $list->id]),
        ];
    }

    protected function detailProps(ListModel $list, ?int $viewerId): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'slug' => $list->slug,
            'description' => $list->description,
            'isRanked' => $list->is_ranked,
            'isPublic' => $list->is_public,
            'likesCount' => $list->likes_count,
            'commentsCount' => $list->comments_count,
            'user' => $list->user->only('name', 'username', 'avatar_path'),
            'isOwn' => $viewerId !== null && $list->user_id === $viewerId,
            'likedByViewer' => $viewerId !== null && $list->likes()->where('user_id', $viewerId)->exists(),
            'url' => route('lists.show', ['username' => $list->user->username, 'list' => $list->id]),
            'editUrl' => route('lists.edit', $list->id),
            // Colaboración: el token no viaja nunca; el enlace se entrega bajo
            // demanda por flash al tocar "Invitar".
            'collaborators' => $list->collaborators->map(fn (User $collaborator) => [
                'id' => $collaborator->id,
                'name' => $collaborator->name,
                'username' => $collaborator->username,
                'avatar_path' => $collaborator->avatar_path,
            ])->values(),
            'canEditItems' => $viewerId !== null
                && ($list->user_id === $viewerId || $list->collaborators->contains('id', $viewerId)),
        ];
    }
}
