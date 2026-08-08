<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * ¿Hay credenciales cargadas? Sin esto el botón no se muestra y la ruta aborta:
     * es preferible a un 500 de Socialite por client_id vacío.
     */
    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): RedirectResponse
    {
        abort_unless(self::configured(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(self::configured(), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            // El usuario canceló, el state expiró o Google devolvió un error
            return redirect()->route('login')->withErrors(['email' => __('app.oauth_failed')]);
        }

        if (blank($googleUser->getEmail())) {
            return redirect()->route('login')->withErrors(['email' => __('app.oauth_no_email')]);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Vincular una cuenta con contraseña exige que Google confirme el email:
            // sin esa garantía, cualquiera podría reclamar la cuenta ajena.
            if (blank($user->google_id) && ! $this->emailIsVerified($googleUser)) {
                return redirect()->route('login')->withErrors(['email' => __('app.oauth_email_unverified')]);
            }

            $this->link($user, $googleUser);
        } else {
            if (! Setting::enabled('features.registration')) {
                return redirect()->route('login')->withErrors(['email' => __('app.feature_disabled')]);
            }

            if (! $this->emailIsVerified($googleUser)) {
                return redirect()->route('login')->withErrors(['email' => __('app.oauth_email_unverified')]);
            }

            $user = $this->register($googleUser);
        }

        if ($user->isBanned()) {
            return redirect()->route('login')->withErrors(['email' => __('app.banned')]);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Google marca el email como verificado en el payload; si el campo no viene,
     * se asume no verificado.
     */
    protected function emailIsVerified(SocialiteUser $googleUser): bool
    {
        $raw = $googleUser->user ?? [];

        return (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
    }

    protected function link(User $user, SocialiteUser $googleUser): void
    {
        $user->google_id = $googleUser->getId();
        $user->email_verified_at ??= now();

        if (blank($user->avatar_path) && filled($googleUser->getAvatar())) {
            $user->avatar_path = $googleUser->getAvatar();
        }

        $user->save();
    }

    protected function register(SocialiteUser $googleUser): User
    {
        $user = new User;
        $user->name = $googleUser->getName() ?: Str::before($googleUser->getEmail(), '@');
        $user->username = $this->uniqueUsername($googleUser);
        $user->email = $googleUser->getEmail();
        $user->google_id = $googleUser->getId();
        $user->avatar_path = $googleUser->getAvatar();
        $user->email_verified_at = now();
        $user->password = null; // entra siempre por Google hasta que defina una
        $user->locale = app()->getLocale();
        $user->save();

        return $user;
    }

    /**
     * El username es parte de la URL del perfil y Google no lo provee:
     * se deriva del email y se desambigua con un sufijo.
     */
    protected function uniqueUsername(SocialiteUser $googleUser): string
    {
        $base = Str::of($googleUser->getNickname() ?: Str::before($googleUser->getEmail(), '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '')
            ->limit(24, '')
            ->value();

        if (Str::length($base) < 3) {
            $base = 'user'.Str::lower(Str::random(6));
        }

        $username = $base;

        for ($i = 2; User::where('username', $username)->exists(); $i++) {
            $username = Str::limit($base, 26, '').$i;
        }

        return $username;
    }
}
