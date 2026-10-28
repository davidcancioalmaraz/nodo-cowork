<?php

/*
|--------------------------------------------------------------------------
| Vecinos simulados
|--------------------------------------------------------------------------
|
| El prototipo solo implementa Reservas y disponibilidad. Los demás
| contextos se simulan con datos estáticos escritos en SU propio lenguaje
| (campos en inglés, como los mostraría su API). Los ACL de src/ los
| traducen al lenguaje de nuestro dominio.
|
*/

return [

    // Espacios y recursos ② ③: catálogo completo de Nodo.
    'espacios' => [
        'sala-a' => ['name' => 'Sala A', 'type' => 'SALA_REUNION', 'status' => 'ACTIVE', 'capacity' => 4],
        'sala-b' => ['name' => 'Sala B', 'type' => 'SALA_REUNION', 'status' => 'ACTIVE', 'capacity' => 8],
        'sala-capacitacion' => ['name' => 'Sala de capacitación', 'type' => 'SALA_CAPACITACION', 'status' => 'ACTIVE', 'capacity' => 40],
        'salon' => ['name' => 'Salón de eventos', 'type' => 'SALON', 'status' => 'ACTIVE', 'capacity' => 120],
        'escritorio-f1' => ['name' => 'Escritorio fijo 1', 'type' => 'ESCRITORIO_FIJO', 'status' => 'ACTIVE', 'capacity' => 1],
    ],

    // Tarifas y precios ④: tarifa vigente por hora de cada sala.
    'tarifas' => [
        'sala-a' => ['rate_id' => 'T-01', 'unit' => 'HOUR', 'amount' => 50, 'currency' => 'BOB'],
        'sala-b' => ['rate_id' => 'T-02', 'unit' => 'HOUR', 'amount' => 80, 'currency' => 'BOB'],
        'sala-capacitacion' => ['rate_id' => 'T-03', 'unit' => 'HOUR', 'amount' => 200, 'currency' => 'BOB'],
    ],

    // Membresías y planes ⑤: planes de los miembros.
    'membresias' => [
        'm-ana' => ['full_name' => 'Ana Rojas', 'plan_status' => 'ACTIVE', 'debt' => 0, 'included_minutes' => 1200, 'used_minutes' => 600],
        'm-luis' => ['full_name' => 'Luis Pérez', 'plan_status' => 'OVERDUE', 'debt' => 350, 'included_minutes' => 600, 'used_minutes' => 600],
    ],

    // Control de acceso físico ⑨: el token que emitiría para cada usuario del
    // login. Cualquier contraseña es válida: la identidad está simulada.
    'identidades' => [
        'miembro' => ['sub' => 'm-ana', 'name' => 'Ana Rojas', 'roles' => ['member']],
        'recepcion' => ['sub' => 'r-carla', 'name' => 'Carla Vaca', 'roles' => ['receptionist']],
    ],

];
