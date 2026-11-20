<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EpisodeWatch;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\ListItem;
use App\Models\ListModel;
use App\Models\Rating;
use App\Models\Review;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;

/**
 * Qué hace la gente en la app, contado por día en daily_metrics. Va en contadores
 * y no en las tablas propias porque en la demo esas se borran todas las noches.
 *
 * No cuentan los admins (es el uso propio), ni lo que corre por consola: seeders,
 * demo:reset, la importación de Letterboxd en la cola.
 */
class Usage
{
    /** Modelo creado => nombre de la acción (metric "actions.<nombre>"). */
    public const ACTIONS = [
        WatchedTitle::class => 'watched',
        Rating::class => 'ratings',
        Review::class => 'reviews',
        DiaryEntry::class => 'diary',
        WatchlistItem::class => 'watchlist',
        Like::class => 'likes',
        Favorite::class => 'favorites',
        ListModel::class => 'lists',
        ListItem::class => 'listItems',
        Comment::class => 'comments',
        EpisodeWatch::class => 'episodes',
    ];

    /** Acciones que no pasan por un create de Eloquent y se anotan a mano. */
    public const MANUAL_ACTIONS = ['follows'];

    public static function register(): void
    {
        foreach (self::ACTIONS as $model => $action) {
            $model::created(fn () => self::action($action));
        }

        Event::listen(Login::class, function (Login $event) {
            if (self::counts($event->user)) {
                Metrics::increment(Metrics::LOGINS);
            }
        });
    }

    public static function action(string $action, int $by = 1): void
    {
        if (self::counts()) {
            Metrics::increment(Metrics::ACTIONS_PREFIX.$action, $by);
        }
    }

    public static function counts(?object $user = null): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        $user ??= auth()->user();

        return ! ($user instanceof User && $user->isAdmin());
    }

    /** @return list<string> */
    public static function actionNames(): array
    {
        return [...array_values(self::ACTIONS), ...self::MANUAL_ACTIONS];
    }
}
