<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Control de acceso físico ⑨ simulado: «miembro» o «recepcion» con
        // cualquier contraseña. El rol lo traduce ControlDeAccesoSimulado.
        Fortify::authenticateUsing(function (Request $request) {
            $usuario = Str::lower(trim((string) $request->input(Fortify::username())));
            $identidad = config("nodo.identidades.{$usuario}");

            if ($identidad === null) {
                return null;
            }

            return User::firstOrCreate(
                ['email' => "{$usuario}@nodo.test"],
                ['name' => $identidad['name'], 'password' => Str::random(40)],
            );
        });

        // Coherente con la identidad simulada: sus usuarios confirman con
        // cualquier contraseña; los demás, con la suya.
        Fortify::confirmPasswordsUsing(fn (User $user, ?string $password) => str_ends_with($user->email, '@nodo.test')
            || Hash::check((string) $password, $user->password));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
