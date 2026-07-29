<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\ListModel;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => 'review',
            'commentable_id' => $review->id,
            'body' => $data['body'],
        ]);

        return back();
    }

    public function storeForList(Request $request, ListModel $list): RedirectResponse
    {
        Gate::authorize('view', $list);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => 'list',
            'commentable_id' => $list->id,
            'body' => $data['body'],
        ]);

        return back();
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back();
    }
}
