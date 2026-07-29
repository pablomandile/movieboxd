<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Title;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TitleLikeController extends Controller
{
    public function toggle(Request $request, Title $title): RedirectResponse
    {
        $keys = [
            'user_id' => $request->user()->id,
            'likeable_type' => 'title',
            'likeable_id' => $title->id,
        ];

        $existing = Like::where($keys)->first();

        $existing === null ? Like::create($keys) : $existing->delete();

        return back();
    }
}
