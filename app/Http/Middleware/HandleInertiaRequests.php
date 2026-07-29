<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'locale' => fn () => app()->getLocale(),
            'features' => fn () => [
                'comments' => Setting::enabled('features.comments'),
                'lists' => Setting::enabled('features.lists'),
                'reviews' => Setting::enabled('features.reviews'),
                'registration' => Setting::enabled('features.registration'),
            ],
            'auth' => [
                'user' => fn () => $request->user()?->only(
                    'id', 'name', 'username', 'email', 'avatar_path', 'role', 'locale', 'email_verified_at'
                ),
            ],
        ]);
    }
}
