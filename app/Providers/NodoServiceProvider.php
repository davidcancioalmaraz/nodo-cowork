<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Nodo\Compartido\Aplicacion\PublicadorDeEventos;
use Nodo\Compartido\Dominio\Reloj;
use Nodo\Compartido\Infraestructura\BitacoraDeIntegracion;
use Nodo\Compartido\Infraestructura\PublicadorDeEventosLaravel;
use Nodo\Compartido\Infraestructura\RelojDelSistema;
use Nodo\Disponibilidad\Aplicacion\LiberarFranja;
use Nodo\Disponibilidad\Aplicacion\SalasDisponibles;
use Nodo\Disponibilidad\Aplicacion\SalasReservables;
use Nodo\Disponibilidad\Dominio\AgendaDelRecursoRepository;
use Nodo\Disponibilidad\Dominio\Eventos\FranjaLiberada;
use Nodo\Disponibilidad\Dominio\Eventos\SalaBloqueada;
use Nodo\Disponibilidad\Infraestructura\EspaciosYRecursos\SalasReservablesSimulado;
use Nodo\Disponibilidad\Infraestructura\Lectura\SalasDisponiblesPostgres;
use Nodo\Disponibilidad\Infraestructura\Persistencia\AgendaDelRecursoRepositoryPostgres;
use Nodo\Reserva\Aplicacion\ListadoDeReservas;
use Nodo\Reserva\Aplicacion\Puertos\AforoDeSala;
use Nodo\Reserva\Aplicacion\Puertos\EstadoDelMiembro;
use Nodo\Reserva\Aplicacion\Puertos\PrecioDeFranja;
use Nodo\Reserva\Aplicacion\Puertos\QuienActua;
use Nodo\Reserva\Dominio\Eventos\PenalizacionAplicada;
use Nodo\Reserva\Dominio\Eventos\ReservaCancelada;
use Nodo\Reserva\Dominio\Eventos\ReservaConfirmada;
use Nodo\Reserva\Dominio\Eventos\ReservaSolicitada;
use Nodo\Reserva\Dominio\Eventos\SolicitudRechazada;
use Nodo\Reserva\Dominio\ReservaRepository;
use Nodo\Reserva\Infraestructura\Lectura\ListadoDeReservasPostgres;
use Nodo\Reserva\Infraestructura\Persistencia\ReservaRepositoryPostgres;
use Nodo\Reserva\Infraestructura\Vecinos\ControlDeAccesoSimulado;
use Nodo\Reserva\Infraestructura\Vecinos\EspaciosYRecursosSimulado;
use Nodo\Reserva\Infraestructura\Vecinos\MembresiasYPlanesSimulado;
use Nodo\Reserva\Infraestructura\Vecinos\TarifasSimulado;

/**
 * Conecta los bounded contexts de src/ con Laravel: cada puerto con su
 * implementación y cada evento de dominio con quien reacciona en proceso.
 */
class NodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Compartido
        $this->app->singleton(Reloj::class, RelojDelSistema::class);
        $this->app->singleton(PublicadorDeEventos::class, PublicadorDeEventosLaravel::class);

        // Disponibilidad
        $this->app->bind(AgendaDelRecursoRepository::class, AgendaDelRecursoRepositoryPostgres::class);
        $this->app->bind(SalasDisponibles::class, SalasDisponiblesPostgres::class);
        $this->app->bind(SalasReservables::class, fn () => new SalasReservablesSimulado(config('nodo.espacios')));

        // Reserva
        $this->app->bind(ReservaRepository::class, ReservaRepositoryPostgres::class);
        $this->app->bind(ListadoDeReservas::class, ListadoDeReservasPostgres::class);
        $this->app->bind(AforoDeSala::class, fn () => new EspaciosYRecursosSimulado(config('nodo.espacios')));
        $this->app->bind(EstadoDelMiembro::class, fn () => new MembresiasYPlanesSimulado(config('nodo.membresias')));
        $this->app->bind(PrecioDeFranja::class, fn () => new TarifasSimulado(config('nodo.tarifas')));
        $this->app->bind(QuienActua::class, fn () => new ControlDeAccesoSimulado(
            config('nodo.identidades'),
            fn () => Auth::check() ? Str::before(Auth::user()->email, '@') : null,
        ));
    }

    public function boot(): void
    {
        // La bitácora se registra primero para que el orden se lea causal:
        // «Reserva cancelada» antes que la «Franja liberada» que provoca.
        Event::listen([
            ReservaSolicitada::class,
            ReservaConfirmada::class,
            SolicitudRechazada::class,
            ReservaCancelada::class,
            PenalizacionAplicada::class,
            SalaBloqueada::class,
            FranjaLiberada::class,
        ], [BitacoraDeIntegracion::class, 'handle']);

        Event::listen(ReservaCancelada::class, [LiberarFranja::class, 'handle']); // P-07
    }
}
