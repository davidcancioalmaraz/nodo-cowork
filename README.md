# Nodo Cowork

Prototipo del área **Reservas y disponibilidad** (subdominio core) del caso Nodo, diseñada con Domain-Driven Design.
Diseño completo: [cllanosucb/nodo-reservas-y-disponibilidad](https://github.com/cllanosucb/nodo-reservas-y-disponibilidad).

Laravel 13 + Jetstream (Inertia + Vue) dockerizado con PostgreSQL 17.

## Requisitos

- Docker y Docker Compose

## Levantar el proyecto

```bash
docker compose up -d
```

El primer arranque construye la imagen y, de forma automática:

1. Crea `.env` a partir de `.env.example` (si no existe).
2. Instala dependencias de Composer y npm.
3. Genera `APP_KEY`.
4. Compila los assets con Vite.
5. Ejecuta las migraciones contra PostgreSQL.

La aplicación queda disponible en http://localhost:8000.

## Servicios

| Servicio          | Descripción                  | Puerto host |
|-------------------|------------------------------|-------------|
| `nodo-cowork-app` | PHP 8.5 + Apache + Node 22   | 8000, 5173  |
| `nodo-cowork-db`  | PostgreSQL 17                | 5432        |

Credenciales de la base de datos (definidas en `.env.example`):

| Variable      | Valor          |
|---------------|----------------|
| `DB_DATABASE` | `nodo_cowork`  |
| `DB_USERNAME` | `nodo_cowork`  |
| `DB_PASSWORD` | `secret`       |

## Comandos útiles

```bash
# Artisan
docker compose exec -u www-data nodo-cowork-app php artisan migrate

# Composer / npm
docker compose exec -u www-data nodo-cowork-app composer require vendor/paquete
docker compose exec -u www-data nodo-cowork-app npm install

# Vite en modo desarrollo (HMR)
docker compose exec -u www-data nodo-cowork-app npm run dev

# Tests
docker compose exec -u www-data nodo-cowork-app php artisan test

# Consola de PostgreSQL
docker compose exec nodo-cowork-db psql -U nodo_cowork -d nodo_cowork

# Detener (añadir -v para borrar también los datos de la base)
docker compose down
```

> Nota: si usas `npm run dev`, elimina `public/hot` al detenerlo para volver a servir los assets compilados.

## Prototipo

### Bounded contexts y estructura

Cada bounded context es un módulo en `src/` (namespace `Nodo\`) con su dominio, su aplicación y su infraestructura.
El dominio no depende de Laravel; `app/` es solo la capa de entrega (controllers e Inertia).

```text
src/
├── Compartido/        Shared kernel ①: Franja, SalaId · eventos, reloj, errores de dominio
├── Disponibilidad/    Aggregate AgendaDelRecurso · BloquearSala · LiberarFranja (P-07) · «Salas disponibles» (lectura)
└── Reserva/           Aggregate Reserva · SolicitarReserva · CancelarReserva · fachadas ACL de los vecinos
```

### Aggregates e invariantes

| Invariante | Dónde se protege | Test |
|---|---|---|
| INV-D1 Nunca dos ocupaciones se solapan | `AgendaDelRecurso::ocupar()` | `tests/Unit/Disponibilidad/AgendaDelRecursoTest.php` |
| INV-D2 Siempre quedan 15 min de margen | `AgendaDelRecurso::ocupar()` con `Margen::deSala()` | idem |
| INV-D3 Una franja bloqueada no admite ocupaciones | `AgendaDelRecurso::bloquear()` / `ocupar()` · se libera con `retirarBloqueo()` (D-53) | idem |
| INV-D4 Solo se libera una ocupación existente, una vez | `AgendaDelRecurso::liberar()` | idem |
| INV-R1 Toda reserva confirmada tiene su franja ocupada | `SolicitarReserva`: ocupar → confirmar, con compensación | `tests/Feature/Nodo/SolicitarReservaTest.php` |
| INV-R2 Solo estados válidos | `EstadoDeReserva::puedePasarA()` | `tests/Unit/Reserva/ReservaTest.php` |
| INV-R3 Confirmación automática: al día y ≥ 24 h | `Reserva::motivoParaNoAceptar()` / `confirmar()` | idem |
| INV-R4 < 3 h no se registra; 3–24 h solo asistida | `Reserva::solicitar()` con `Anticipacion` | idem |
| INV-R5 Asistentes ≤ aforo | `Reserva::confirmar()` con `Asistentes` | idem |
| INV-R7 Cancelar con < 4 h → una penalización | `Reserva::cancelar()` con `PoliticaDeCancelacion` | idem y `CancelarReservaTest.php` |
| INV-R8 La asistida guarda titular y quién la registró | `Responsable::asistida()` | idem |

La agenda tiene **versión** (bloqueo optimista): si dos solicitudes compiten por el último cupo, una gana y la otra reintenta.

### Vecinos simulados

Los demás contextos no se implementan: sus datos son estáticos en `config/nodo.php`, escritos en **su** lenguaje
(campos en inglés, como los daría su API), y entran por las fachadas ACL del documento de integración,
que los traducen a nuestro glosario. Reemplazar un vecino real solo cambia su adaptador.

| Vecino | Fachada | Adaptador simulado |
|---|---|---|
| Espacios y recursos ② ③ | `SalasReservables`, `AforoDeSala` | `SalasReservablesSimulado`, `EspaciosYRecursosSimulado` |
| Tarifas y precios ④ | `PrecioDeFranja` | `TarifasSimulado` |
| Membresías y planes ⑤ | `EstadoDelMiembro` | `MembresiasYPlanesSimulado` (Ana al día, Luis no) |
| Control de acceso físico ⑨ | `QuienActua` | `ControlDeAccesoSimulado` |
| Notificaciones ⑦, Facturación ⑥ | — | `BitacoraDeIntegracion`: registra lo que se enviaría (visible en pantalla) |

**Identidad simulada:** en el login, usuario `miembro` (Ana Rojas) o `recepcion` (Carla Vaca), con **cualquier contraseña**.

### Guion de la demo

1. Ingresar como `miembro` y reservar la Sala B para pasado mañana de 10:00 a 11:00 → **Reserva confirmada**.
2. Pedir la misma sala de 11:05 a 12:00 → rechazada por **INV-D2** (margen de 15 min).
3. Pedir la Sala B para 9 asistentes → rechazada por **INV-R5**; pedir para mañana temprano → no se registra (**INV-R4**).
4. Ingresar como `recepcion`: bloquear la Sala A e intentar reservarla → **INV-D3**; retirar el bloqueo (clic en el bloque o «Retirar») → *Franja liberada* y la sala vuelve a reservarse; reserva asistida para Luis → **INV-R3** (no está al día).
5. Reserva asistida para Ana con ~3,5 h de anticipación y cancelarla → **penalización** (**INV-R7**); cancelarla otra vez → **INV-R2**.
   La bitácora muestra *Reserva cancelada → Franja liberada → Penalización aplicada* y lo que recibe cada vecino.

### Fuera del alcance del prototipo

Lista de espera (INV-R9), visitante con pago por adelantado (INV-R6), equipo portátil, horas extra, salida anticipada,
y reasignar, reprogramar o reembolsar reservas afectadas por un bloqueo (P-09).

> Si cambias archivos de `resources/js`, recompila con `npm run build` (o usa `npm run dev`).
