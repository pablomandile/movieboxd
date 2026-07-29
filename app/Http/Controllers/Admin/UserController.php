<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->withCount(['reviews', 'diaryEntries'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/Users', [
            'search' => $search,
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->value,
                'isBanned' => $user->isBanned(),
                'bannedAt' => $user->banned_at?->toDateString(),
                'reviewsCount' => $user->reviews_count,
                'diaryCount' => $user->diary_entries_count,
                'createdAt' => $user->created_at->toDateString(),
                'profileUrl' => route('profiles.show', $user->username),
            ]),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        // Un admin no puede quitarse a sí mismo el rol (evita quedarse sin acceso)
        if ($user->id === $request->user()->id && $data['role'] !== Role::Admin->value) {
            return back()->withErrors(['role' => __('app.cannot_demote_self')]);
        }

        // role no es mass-assignable a propósito: solo se cambia desde acá
        $user->role = Role::from($data['role']);
        $user->save();

        return back();
    }

    public function toggleBan(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['ban' => __('app.cannot_ban_self')]);
        }

        if ($user->isBanned()) {
            $user->banned_at = null;
            $user->save();

            return back();
        }

        $user->banned_at = now();
        $user->save();

        // Matar sus sesiones activas: el ban surte efecto inmediato
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back();
    }
}
