<?php

namespace App\Providers;

use App\Models\Comment;
use App\Models\Episode;
use App\Models\ListModel;
use App\Models\Review;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Morphs con alias cortos: nunca serializar FQCNs en la base
        Relation::enforceMorphMap([
            'user' => User::class,
            'title' => Title::class,
            'season' => Season::class,
            'episode' => Episode::class,
            'review' => Review::class,
            'comment' => Comment::class,
            'list' => ListModel::class,
        ]);

        // Límite de jobs contra TMDB (su tope real es ~40 req/s)
        RateLimiter::for('tmdb', function (object $job) {
            return Limit::perSecond(30);
        });
    }
}
