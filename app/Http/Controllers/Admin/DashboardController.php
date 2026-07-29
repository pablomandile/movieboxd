<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Enums\TitleType;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\ListModel;
use App\Models\Report;
use App\Models\Review;
use App\Models\Title;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'bannedUsers' => User::whereNotNull('banned_at')->count(),
                'movies' => Title::where('type', TitleType::Movie)->count(),
                'shows' => Title::where('type', TitleType::Tv)->count(),
                'diaryEntries' => DiaryEntry::count(),
                'reviews' => Review::count(),
                'comments' => Comment::count(),
                'lists' => ListModel::count(),
                'pendingReports' => Report::where('status', ReportStatus::Pending)->count(),
            ],
            'recentUsers' => User::latest()->limit(8)->get()->map(fn (User $user) => [
                'username' => $user->username,
                'name' => $user->name,
                'createdAt' => $user->created_at->toDateString(),
                'isBanned' => $user->isBanned(),
            ]),
        ]);
    }
}
