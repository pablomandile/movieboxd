<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\Episode;
use App\Models\EpisodeWatch;
use App\Models\Favorite;
use App\Models\Like;
use App\Models\ListItem;
use App\Models\ListModel;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Title;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de la demo: el usuario compartido y cuatro amigos inventados, con diario,
 * reseñas, comentarios, listas, favoritas, watchlist y series a medio ver.
 *
 * - Usa SOLO títulos que ya están en el catálogo (por tmdb_id). Si alguno falta, se
 *   saltea: nunca se llama a TMDB desde acá.
 * - Semilla fija y fechas relativas a hoy: la demo siempre se ve al día.
 * - Sin factories ni fake(): en el server no está Faker (composer --no-dev).
 * - Todo pasa por los modelos, así los observers mantienen los contadores.
 */
class DemoSeeder extends Seeder
{
    // Películas que vio el usuario demo (TMDB ids).
    private const VISTAS = [
        238, 240, 278, 680, 155, 13, 550, 27205, 157336, 496243, 129, 105, 85, 11, 1891, 424, 769, 120, 122,
        389, 265195, 25376, 872585, 414906, 546554, 438631, 1124, 77, 807, 274, 497, 500, 629,
        329, 194, 62, 603, 28, 557, 118340, 446159, 244786, 670, 598, 940721, 4935, 792293,
    ];

    // Las que tiene pendientes (watchlist).
    private const PENDIENTES = [346, 18148, 11216, 26405, 49797, 792307, 693134, 1022789, 976573, 530385, 466272];

    // Las que más le gustan: puntaje alto fijo y candidatas a volver a ver.
    private const PREFERIDAS = [238 => 10, 496243 => 10, 129 => 10, 25376 => 10, 265195 => 9, 603 => 9, 155 => 9, 62 => 10, 680 => 9, 1891 => 9];

    private const RESENAS_DEMO = [
        496243 => 'La vi por segunda vez y me gustó todavía más. Cada plano está pensado: la escalera, la lluvia, el sótano. Bong Joon-ho arma una comedia negra que de golpe te deja sin aire.',
        25376 => 'Darín y Villamil enormes, y ese plano secuencia en la cancha de Huracán sigue siendo de lo mejor del cine argentino. El final te queda dando vueltas días.',
        129 => 'Volver a verla de grande es otra película. El tren sobre el agua es una de las escenas más lindas que existen.',
        872585 => 'Tres horas que pasan volando. La secuencia de la prueba Trinity en el cine fue una experiencia física. Le sobra un poco el tramo de la audiencia, igual.',
        265195 => 'Seis cuentos y ninguno flojo. «Bombita» es catarsis pura para cualquiera que haya tenido que pelear con una grúa.',
        603 => 'Envejeció mejor que casi cualquier película de acción de su época. La escena del lobby sigue siendo increíble.',
        940721 => 'No esperaba que una de Godzilla me emocionara tanto. Los personajes importan y el monstruo da miedo de verdad.',
        438631 => 'Visualmente apabullante. Villeneuve se toma su tiempo y se agradece. Ahora a ver la segunda.',
        155 => 'El Joker de Ledger sigue siendo la razón para volver a verla, pero toda la película funciona como un reloj.',
    ];

    private const AMIGOS = [
        'martina' => [
            'name' => 'Martina Sosa', 'username' => 'martisosa',
            'bio' => 'Fan del terror y de todo lo que dé miedo de verdad. Si tiene A24 en el póster, ya la quiero ver.',
            // tmdb => [puntaje, reseña|null]
            'vistas' => [
                530385 => [9, 'Terror a plena luz del día. Florence Pugh carga con la película y la última media hora es imposible de olvidar.'],
                49797 => [8, null], 26405 => [8, null],
                670 => [9, 'Brutal y triste a la vez. El plano secuencia del pasillo con el martillo ya es historia del cine.'],
                807 => [9, null], 274 => [10, null], 496243 => [9, null],
                446159 => [8, 'Un western brasileño rarísimo y muy político. Mejor no saber nada antes de verla.'],
                546554 => [7, null], 792307 => [8, null],
            ],
        ],
        'joaquin' => [
            'name' => 'Joaquín Ferreyra', 'username' => 'joaco_cine',
            'bio' => 'Clásicos, cine de autor y alguna que otra de superhéroes para no ser tan serio.',
            'vistas' => [
                346 => [10, 'Tres horas y media y no sobra nada. Todo el cine de acción que vino después le debe algo.'],
                18148 => [10, null], 389 => [10, null], 238 => [10, null],
                240 => [10, 'Para mí supera a la primera. El paralelo entre Vito y Michael es devastador.'],
                28 => [9, null], 62 => [10, null],
                11216 => [9, 'El final con los besos censurados me destruye cada vez. Una carta de amor al cine.'],
                25376 => [9, null],
                118340 => [7, 'Me divertí más de lo que pensaba. La banda sonora hace la mitad del trabajo.'],
                414906 => [8, null],
            ],
        ],
        'valentina' => [
            'name' => 'Valentina Ríos', 'username' => 'valerios',
            'bio' => 'Ghibli, Pixar y todo lo que me haga llorar en el colectivo.',
            'vistas' => [
                129 => [10, null],
                4935 => [9, 'El castillo, Calcifer, la música de Hisaishi. Es como entrar a un cuento que no querés que termine.'],
                1022789 => [8, 'Ansiedad es el personaje más preciso que hizo Pixar. Lloré en la escena de la tormenta, obvio.'],
                976573 => [7, null],
                194 => [9, 'Cada detalle de París parece pintado a mano. Imposible no salir de buen humor.'],
                11216 => [10, null], 13 => [9, null], 497 => [9, null], 244786 => [9, null], 792307 => [8, null],
            ],
        ],
        'tomas' => [
            'name' => 'Tomás Iglesias', 'username' => 'tomi_series',
            'bio' => 'Más series que películas. Siempre a mitad de alguna temporada.',
            'vistas' => [
                693134 => [9, 'Esto es cine de pantalla gigante. La escena del gusano de arena me dejó pegado a la butaca.'],
                157336 => [9, null], 27205 => [8, null],
                792293 => [6, 'Entretenida, pero se queda en la superficie de una historia que daba para mucho más.'],
                872585 => [9, null], 438631 => [8, null],
            ],
        ],
    ];

    /** @var Collection<int, Title> películas del catálogo, por tmdb_id */
    private Collection $peliculas;

    /** @var Collection<int, Title> series del catálogo, por tmdb_id */
    private Collection $series;

    private CarbonImmutable $hoy;

    public function run(): void
    {
        mt_srand(20261108);
        $this->hoy = CarbonImmutable::now();

        $vistas = self::VISTAS;
        $todas = array_unique(array_merge($vistas, self::PENDIENTES, ...array_map(
            fn ($a) => array_keys($a['vistas']), array_values(self::AMIGOS)
        )));
        $this->peliculas = Title::where('type', 'movie')->whereIn('tmdb_id', $todas)->get()->keyBy('tmdb_id');
        $this->series = Title::where('type', 'tv')->whereIn('tmdb_id', [87108, 136315, 95396, 100088])->get()->keyBy('tmdb_id');

        $demo = User::create([
            'name' => 'Demo',
            'username' => 'demo',
            'email' => config('demo.email'),
            'password' => config('demo.password'),
            'bio' => 'Cuenta de demostración de Movieboxd. Cargá, reseñá y armá listas: todo vuelve a como estaba cada noche.',
        ]);
        $demo->forceFill(['email_verified_at' => $this->hoy->subYear()])->save();

        $amigos = collect(self::AMIGOS)->map(function (array $a, string $clave) {
            $user = User::create([
                'name' => $a['name'],
                'username' => $a['username'],
                'email' => "{$a['username']}@example.com",
                'password' => Str::random(40), // nadie entra con estas cuentas
                'bio' => $a['bio'],
            ]);
            $user->forceFill(['email_verified_at' => $this->hoy->subYear()])->save();

            return $user;
        });

        $this->seguimientos($demo, $amigos);
        $resenasDemo = $this->diarioDemo($demo);
        $resenasAmigos = $this->diarioAmigos($amigos);
        $this->series($demo, $amigos);
        $this->conversacion($demo, $amigos, $resenasDemo, $resenasAmigos);
        $this->favoritasYPendientes($demo, $amigos);
        $this->listas($demo, $amigos);
    }

    private function seguimientos(User $demo, Collection $amigos): void
    {
        $pares = [
            [$demo, $amigos['martina'], 300], [$demo, $amigos['joaquin'], 280], [$demo, $amigos['valentina'], 200], [$demo, $amigos['tomas'], 90],
            [$amigos['martina'], $demo, 299], [$amigos['joaquin'], $demo, 270], [$amigos['valentina'], $demo, 190],
            [$amigos['joaquin'], $amigos['valentina'], 150], [$amigos['valentina'], $amigos['joaquin'], 149], [$amigos['tomas'], $amigos['joaquin'], 60],
        ];

        DB::table('follows')->insert(array_map(fn ($p) => [
            'follower_id' => $p[0]->id,
            'followed_id' => $p[1]->id,
            'created_at' => $this->hoy->subDays($p[2]),
            'updated_at' => $this->hoy->subDays($p[2]),
        ], $pares));
    }

    /**
     * Unas 55 entradas en el último año y medio, más densas cerca de hoy, con
     * algunas re-vistas de sus preferidas.
     *
     * @return Collection<int, Review> reseñas por tmdb_id
     */
    private function diarioDemo(User $demo): Collection
    {
        $vistas = self::VISTAS;
        shuffle($vistas);

        $logs = [];
        foreach ($vistas as $tmdb) {
            // Sesgo hacia lo reciente: más actividad en los últimos meses.
            $logs[] = ['tmdb' => $tmdb, 'dias' => (int) round(540 * (mt_rand(0, 1000) / 1000) ** 1.7), 'rewatch' => false];
        }
        foreach (array_slice(array_keys(self::PREFERIDAS), 0, 8) as $tmdb) {
            $primera = collect($logs)->firstWhere('tmdb', $tmdb)['dias'] ?? 400;
            $logs[] = ['tmdb' => $tmdb, 'dias' => mt_rand(0, max(0, $primera - 30)), 'rewatch' => true];
        }
        // Las reseñas van en la vista más reciente: cronológico, de la más vieja a la más nueva.
        usort($logs, fn ($a, $b) => $b['dias'] <=> $a['dias']);

        $resenas = collect();
        $ultima = [];
        foreach ($logs as $log) {
            $ultima[$log['tmdb']] = $log['dias'];
        }

        $etiquetas = [['cine'], ['en casa'], ['con amigos'], ['cine', 'estreno'], ['maratón'], []];
        foreach ($logs as $log) {
            $titulo = $this->peliculas->get($log['tmdb']);
            if (! $titulo) {
                continue;
            }
            $puntaje = self::PREFERIDAS[$log['tmdb']] ?? $this->puntaje();
            $resena = $ultima[$log['tmdb']] === $log['dias'] ? (self::RESENAS_DEMO[$log['tmdb']] ?? null) : null;

            $review = $this->loguear($demo, $titulo, $log['dias'], $puntaje, [
                'rewatch' => $log['rewatch'],
                'like' => $puntaje >= 9,
                'resena' => $resena,
                'tags' => $etiquetas[mt_rand(0, count($etiquetas) - 1)],
            ]);
            if ($review) {
                $resenas[$log['tmdb']] = $review;
            }
        }

        return $resenas;
    }

    /**
     * La actividad de los amigos es lo que llena el inicio: casi toda en el último mes y medio.
     *
     * @return Collection<string, Collection<int, Review>> reseñas por amigo y tmdb_id
     */
    private function diarioAmigos(Collection $amigos): Collection
    {
        return $amigos->map(function (User $amigo, string $clave) {
            $resenas = collect();
            foreach (self::AMIGOS[$clave]['vistas'] as $tmdb => [$puntaje, $texto]) {
                $titulo = $this->peliculas->get($tmdb);
                if (! $titulo) {
                    continue;
                }
                $dias = mt_rand(1, 10) <= 8 ? mt_rand(0, 45) : mt_rand(46, 240);
                $review = $this->loguear($amigo, $titulo, $dias, $puntaje, ['like' => $puntaje >= 9, 'resena' => $texto]);
                if ($review) {
                    $resenas[$tmdb] = $review;
                }
            }

            return $resenas;
        });
    }

    /**
     * Chernobyl terminada y reseñada, Severance vista, The Bear a medio ver.
     * Tomás, más seriéfilo, va adelante.
     */
    private function series(User $demo, Collection $amigos): void
    {
        if ($chernobyl = $this->series->get(87108)) {
            $this->verEpisodios($demo, $chernobyl, 210, null);
            $this->loguear($demo, $chernobyl, 205, 10, [
                'like' => true,
                'resena' => 'Pocas veces una serie me dejó tan angustiado. Jared Harris está perfecto y el último episodio, en el juicio, es una clase de cómo contar algo complejo.',
            ]);
        }
        if ($severance = $this->series->get(95396)) {
            $this->verEpisodios($demo, $severance, 100, null);
            $this->puntuar($demo, $severance, 9);
        }
        if ($bear = $this->series->get(136315)) {
            $this->verEpisodios($demo, $bear, 20, 6);
        }

        $tomas = $amigos['tomas'];
        foreach ([136315 => 10, 95396 => 9, 100088 => 9, 87108 => 10] as $tmdb => $puntaje) {
            if ($serie = $this->series->get($tmdb)) {
                $this->verEpisodios($tomas, $serie, mt_rand(15, 120), null);
                $this->puntuar($tomas, $serie, $puntaje);
            }
        }
        if ($bear) {
            $this->resenar($tomas, $bear, 3, 'Sí, chef. El séptimo episodio de la primera temporada, todo en un solo plano, es de lo más tenso que vi en televisión.');
        }
    }

    /**
     * Comentarios y likes entre todos, para que las reseñas tengan conversación.
     *
     * @param  Collection<int, Review>  $demo
     * @param  Collection<string, Collection<int, Review>>  $deAmigos
     */
    private function conversacion(User $usuarioDemo, Collection $amigos, Collection $demo, Collection $deAmigos): void
    {
        $hilos = [
            [$demo->get(496243), $amigos['joaquin'], 'Totalmente. La escena de la inundación es una obra maestra de montaje.'],
            [$demo->get(496243), $amigos['martina'], 'La tengo que volver a ver, la vi una sola vez en el cine.'],
            [$demo->get(25376), $amigos['valentina'], '¡El plano en la cancha! Todavía no entiendo cómo lo filmaron.'],
            [$demo->get(872585), $amigos['tomas'], 'Coincido con lo de la audiencia. Igual la vería de nuevo en IMAX mañana.'],
            [$deAmigos['martina']->get(530385), $usuarioDemo, 'Me la recomendaste hace mil y todavía no la vi. Ya está en la watchlist.'],
            [$deAmigos['tomas']->get(693134), $usuarioDemo, 'Está en mi watchlist, la veo este finde.'],
            [$deAmigos['joaquin']->get(346), $amigos['valentina'], 'Me convenciste, la agrego.'],
        ];
        foreach ($hilos as [$review, $autor, $texto]) {
            if ($review) {
                $this->comentar($autor, $review, $texto);
            }
        }

        // Likes a reseñas: los amigos a casi todas las del demo, y el demo a varias de ellos.
        foreach ($demo as $review) {
            foreach ($amigos as $amigo) {
                if (mt_rand(1, 10) <= 6) {
                    $this->darLike($amigo, $review);
                }
            }
        }
        foreach ($deAmigos->flatten(1) as $review) {
            if (mt_rand(1, 10) <= 5) {
                $this->darLike($usuarioDemo, $review);
            }
        }
    }

    private function favoritasYPendientes(User $demo, Collection $amigos): void
    {
        $favoritas = [
            $demo->id => [238, 496243, 129, 25376],
            $amigos['joaquin']->id => [346, 240, 62, 18148],
            $amigos['valentina']->id => [129, 4935, 194, 11216],
            $amigos['martina']->id => [274, 530385, 670],
        ];
        foreach ($favoritas as $userId => $tmdbs) {
            $posicion = 1;
            foreach ($tmdbs as $tmdb) {
                if ($titulo = $this->peliculas->get($tmdb)) {
                    Favorite::create(['user_id' => $userId, 'title_id' => $titulo->id, 'position' => $posicion++]);
                }
            }
        }

        $yaVistas = WatchedTitle::where('user_id', $demo->id)->pluck('title_id')->all();
        foreach (self::PENDIENTES as $i => $tmdb) {
            $titulo = $this->peliculas->get($tmdb);
            if ($titulo && ! in_array($titulo->id, $yaVistas, true)) {
                $this->conFecha(WatchlistItem::make(['user_id' => $demo->id, 'title_id' => $titulo->id]), 5 + $i * 17)->save();
            }
        }
    }

    private function listas(User $demo, Collection $amigos): void
    {
        $scifi = $this->lista($demo, 'Ciencia ficción que me voló la cabeza', 'Mi top personal. Para discutir en los comentarios.', true, 120, [
            62 => null, 603 => null, 157336 => null, 27205 => null, 438631 => null, 1124 => 'No es ciencia ficción pura, pero el final lo amerita.', 105 => null,
        ]);
        $this->lista($demo, 'Para empezar con el cine coreano', 'Tres puertas de entrada, de la más amable a la más brutal.', false, 60, [
            496243 => null, 670 => null, 49797 => 'Pendiente: me la recomendó Martina.',
        ]);
        $this->lista($demo, 'Studio Ghibli para una tarde de domingo', null, false, 25, [
            129 => null, 4935 => null,
        ]);
        $clasicos = $this->lista($amigos['joaquin'], 'Clásicos que hay que ver al menos una vez', 'Sin orden de importancia. Bueno, un poco sí.', true, 80, [
            346 => null, 18148 => null, 389 => null, 238 => null, 240 => null, 62 => null, 11216 => null,
        ]);

        foreach ([$amigos['joaquin'], $amigos['tomas'], $amigos['valentina']] as $amigo) {
            $this->darLike($amigo, $scifi);
        }
        $this->comentar($amigos['tomas'], $scifi, 'Interstellar arriba de Matrix, y no se discute más.');
        $this->darLike($demo, $clasicos);
        $this->comentar($demo, $clasicos, 'Me faltan Los siete samuráis y Cuentos de Tokio. Este año las veo.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Una entrada de diario como la carga la app (DiaryEntryController::store):
     * entrada, rating vigente, like y reseña atada. Devuelve la reseña si hubo.
     *
     * @param  array{rewatch?: bool, like?: bool, resena?: ?string, tags?: list<string>}  $op
     */
    private function loguear(User $user, Title $titulo, int $dias, ?int $puntaje, array $op = []): ?Review
    {
        $entrada = $this->conFecha(DiaryEntry::make([
            'user_id' => $user->id,
            'loggable_type' => 'title',
            'loggable_id' => $titulo->id,
            'watched_on' => $this->hoy->subDays($dias)->toDateString(),
            'rating' => $puntaje,
            'is_rewatch' => $op['rewatch'] ?? false,
            'tags' => $op['tags'] ?? [],
        ]), $dias);
        $entrada->save();

        if ($puntaje) {
            $this->puntuar($user, $titulo, $puntaje);
        }
        if ($op['like'] ?? false) {
            $this->darLike($user, $titulo, $dias);
        }
        if (! empty($op['resena'])) {
            return $this->resenar($user, $titulo, $dias, $op['resena'], $entrada);
        }

        return null;
    }

    private function puntuar(User $user, Title $titulo, int $valor): void
    {
        Rating::updateOrCreate(
            ['user_id' => $user->id, 'rateable_type' => 'title', 'rateable_id' => $titulo->id],
            ['value' => $valor],
        );
    }

    private function resenar(User $user, Title $titulo, int $dias, string $texto, ?DiaryEntry $entrada = null): Review
    {
        $review = $this->conFecha(Review::make([
            'user_id' => $user->id,
            'reviewable_type' => 'title',
            'reviewable_id' => $titulo->id,
            'diary_entry_id' => $entrada?->id,
            'body' => $texto,
            'contains_spoilers' => false,
        ]), $dias);
        $review->save();

        return $review;
    }

    private function comentar(User $user, Model $sobre, string $texto): void
    {
        $dias = max(0, (int) $this->hoy->diffInDays($sobre->created_at, true) - mt_rand(0, 3));
        $this->conFecha(Comment::make([
            'user_id' => $user->id,
            'commentable_type' => $sobre->getMorphClass(),
            'commentable_id' => $sobre->id,
            'body' => $texto,
        ]), $dias)->save();
    }

    private function darLike(User $user, Model $sobre, ?int $dias = null): void
    {
        $existe = Like::where([
            'user_id' => $user->id, 'likeable_type' => $sobre->getMorphClass(), 'likeable_id' => $sobre->id,
        ])->exists();
        if ($existe) {
            return;
        }
        $dias ??= max(0, (int) $this->hoy->diffInDays($sobre->created_at, true) - mt_rand(0, 2));
        $this->conFecha(Like::make([
            'user_id' => $user->id,
            'likeable_type' => $sobre->getMorphClass(),
            'likeable_id' => $sobre->id,
        ]), $dias)->save();
    }

    /**
     * Mira los episodios en orden, uno o dos por noche, terminando hace $dias.
     * Sin $cantidad, todos (sin especiales). Si los ve todos, la serie queda vista.
     */
    private function verEpisodios(User $user, Title $serie, int $dias, ?int $cantidad): void
    {
        $episodios = Episode::where('title_id', $serie->id)
            ->where('season_number', '>', 0)
            ->orderBy('season_number')->orderBy('episode_number')
            ->get();
        $todos = $cantidad === null || $cantidad >= $episodios->count();
        $episodios = $episodios->take($cantidad ?? $episodios->count());

        $dia = $dias + (int) ceil($episodios->count() / 2);
        foreach ($episodios as $i => $episodio) {
            EpisodeWatch::create([
                'user_id' => $user->id,
                'episode_id' => $episodio->id,
                'title_id' => $serie->id,
                'season_id' => $episodio->season_id,
                'watched_at' => $this->hoy->subDays(max($dias, $dia - intdiv($i, 2)))->setTime(22, 0),
            ]);
        }

        if ($todos && $episodios->isNotEmpty()) {
            WatchedTitle::firstOrCreate(['user_id' => $user->id, 'title_id' => $serie->id]);
        }
    }

    private function lista(User $user, string $nombre, ?string $descripcion, bool $ranking, int $dias, array $items): ListModel
    {
        $lista = $this->conFecha(ListModel::make([
            'user_id' => $user->id,
            'name' => $nombre,
            'slug' => Str::slug($nombre),
            'description' => $descripcion,
            'is_ranked' => $ranking,
            'is_public' => true,
        ]), $dias);
        $lista->save();

        $posicion = 1;
        foreach ($items as $tmdb => $nota) {
            if ($titulo = $this->peliculas->get($tmdb)) {
                ListItem::create(['list_id' => $lista->id, 'title_id' => $titulo->id, 'position' => $posicion++, 'note' => $nota]);
            }
        }

        return $lista;
    }

    /** Puntaje de 1 a 10 (medias estrellas), cargado hacia lo bueno: uno mira lo que le interesa. */
    private function puntaje(): int
    {
        $valores = [5, 6, 6, 7, 7, 7, 8, 8, 8, 8, 9, 9, 10, 4];

        return $valores[mt_rand(0, count($valores) - 1)];
    }

    /**
     * @template T of Model
     *
     * @param  T  $modelo
     * @return T
     */
    private function conFecha(Model $modelo, int $dias): Model
    {
        $momento = $this->hoy->subDays($dias)->setTime(mt_rand(18, 23), mt_rand(0, 59));
        if ($momento->isFuture()) {
            $momento = $this->hoy->subMinutes(mt_rand(5, 90));
        }
        $modelo->created_at = $momento;
        $modelo->updated_at = $momento;

        return $modelo;
    }
}
