<script setup>
import { computed } from 'vue';

const props = defineProps({
    salas: Array,
    margen: { type: Number, default: 15 },
    // Hora actual (HH:MM), solo cuando el día mostrado es hoy.
    ahora: { type: String, default: null },
    // Recepción puede retirar un bloqueo haciendo clic sobre él.
    puedeRetirarBloqueos: { type: Boolean, default: false },
});

const emit = defineEmits(['elegir', 'retirar-bloqueo']);

const aMinutos = (hora) => {
    const [h, m] = hora.split(':').map(Number);
    return h * 60 + m;
};
const aHora = (minutos) => `${String(Math.floor(minutos / 60)).padStart(2, '0')}:${String(minutos % 60).padStart(2, '0')}`;

// El rango visible: de 07 a 22 h, extendido para que quepa todo lo del día.
const tramos = computed(() => props.salas.flatMap((sala) => [
    ...sala.ocupaciones.map((o) => [aMinutos(o.inicio) - props.margen, aMinutos(o.fin) + props.margen]),
    ...sala.bloqueos.map((b) => [aMinutos(b.inicio), aMinutos(b.fin)]),
]));
const desde = computed(() => Math.max(0, Math.min(7 * 60, Math.floor(Math.min(...tramos.value.map((t) => t[0])) / 60) * 60)));
const hasta = computed(() => Math.min(24 * 60, Math.max(22 * 60, Math.ceil(Math.max(...tramos.value.map((t) => t[1])) / 60) * 60)));
const duracion = computed(() => hasta.value - desde.value);
const horas = computed(() => Array.from({ length: duracion.value / 60 + 1 }, (_, i) => desde.value / 60 + i));

const porcentaje = (minutos) => `${((minutos - desde.value) / duracion.value) * 100}%`;

// Posición de un tramo dentro de la barra, recortado al rango visible.
const posicion = (inicio, fin) => {
    const a = Math.max(inicio, desde.value);
    const b = Math.min(fin, hasta.value);

    return { left: porcentaje(a), width: `${((b - a) / duracion.value) * 100}%` };
};

// El texto dentro del bloque depende de cuánto mide: completo, solo el inicio o nada (queda en el tooltip).
const fraccion = (inicio, fin) => (fin - inicio) / duracion.value;
const textoDe = (franja, completo) => {
    if (fraccion(franja.desde, franja.hasta) >= 0.075) {
        return completo;
    }

    return fraccion(franja.desde, franja.hasta) >= 0.03 ? franja.inicio : '';
};

const bloqueos = (sala) => sala.bloqueos.map((b) => ({ ...b, desde: aMinutos(b.inicio), hasta: aMinutos(b.fin) }));

// Una reserva que quedó bajo un bloqueo es una reserva afectada (P-09): se resalta.
const ocupaciones = (sala) => sala.ocupaciones.map((o) => {
    const desde = aMinutos(o.inicio);
    const hasta = aMinutos(o.fin);

    return { ...o, desde, hasta, afectada: bloqueos(sala).some((b) => b.desde < hasta && desde < b.hasta) };
});
const hayAfectadas = computed(() => props.salas.some((sala) => ocupaciones(sala).some((o) => o.afectada)));

const franjasDelDia = (sala) => [
    ...ocupaciones(sala).map((o) => ({ ...o, tipo: o.afectada ? 'afectada' : 'ocupada' })),
    ...sala.bloqueos.map((b) => ({ ...b, tipo: b.motivo, esBloqueo: true })),
].sort((x, y) => x.inicio.localeCompare(y.inicio));

// Clic en un hueco: la media hora más cercana hacia abajo.
const elegir = (sala, evento) => {
    const barra = evento.currentTarget.getBoundingClientRect();
    const minuto = desde.value + ((evento.clientX - barra.left) / barra.width) * duracion.value;
    const inicio = Math.min(Math.floor(minuto / 30) * 30, hasta.value - 30);

    emit('elegir', { salaId: sala.id, inicio: aHora(inicio) });
};

const rayado = 'repeating-linear-gradient(135deg, rgb(107 114 128) 0 6px, rgb(75 85 99) 6px 12px)';
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600">
            <span class="flex items-center gap-1"><span class="inline-block size-3 rounded bg-indigo-500" /> Ocupada</span>
            <span class="flex items-center gap-1"><span class="inline-block size-3 rounded bg-indigo-200" /> Margen de {{ margen }} min</span>
            <span class="flex items-center gap-1"><span class="inline-block size-3 rounded" :style="{ background: rayado }" /> Bloqueada</span>
            <span v-if="hayAfectadas" class="flex items-center gap-1"><span class="inline-block size-3 rounded bg-indigo-500 ring-2 ring-amber-400" /> Reserva afectada por un bloqueo</span>
            <span v-if="ahora" class="flex items-center gap-1"><span class="inline-block h-3 w-0.5 bg-red-500" /> Ahora</span>
            <span class="hidden sm:inline ms-auto text-gray-400">Haz clic en un hueco libre para reservarlo</span>
        </div>

        <!-- Escritorio y tablet: una barra por sala, sin scroll horizontal -->
        <div class="mt-4 hidden sm:grid grid-cols-[10rem_1fr] gap-x-4 gap-y-3">
            <div />
            <div class="relative h-5 text-xs text-gray-400">
                <span
                    v-for="hora in horas"
                    :key="hora"
                    class="absolute -translate-x-1/2"
                    :class="{ 'hidden lg:block': hora % 2 === 1 && hora !== desde / 60 && hora !== hasta / 60 }"
                    :style="{ left: porcentaje(hora * 60) }"
                >{{ hora }}h</span>
            </div>

            <template v-for="sala in salas" :key="sala.id">
                <div class="flex flex-col justify-center">
                    <p class="font-medium text-gray-900 truncate">{{ sala.nombre }}</p>
                    <p class="text-xs text-gray-500">Aforo {{ sala.aforo }}</p>
                </div>

                <div
                    class="relative h-12 cursor-pointer overflow-hidden rounded-md bg-gray-50 ring-1 ring-gray-200 hover:ring-indigo-300"
                    :title="`Reservar ${sala.nombre}`"
                    @click="elegir(sala, $event)"
                >
                    <div
                        v-for="hora in horas.slice(1, -1)"
                        :key="`l${hora}`"
                        class="absolute inset-y-0 border-l border-gray-200/70"
                        :style="{ left: porcentaje(hora * 60) }"
                    />

                    <div
                        v-for="(b, i) in bloqueos(sala)"
                        :key="`b${i}`"
                        class="absolute inset-y-1.5 flex items-center justify-center overflow-hidden rounded text-[11px] font-medium text-white"
                        :class="puedeRetirarBloqueos ? 'cursor-pointer hover:ring-2 hover:ring-red-400' : 'cursor-default'"
                        :style="{ ...posicion(b.desde, b.hasta), background: rayado }"
                        :title="`Bloqueada por ${b.motivo} ${b.inicio}–${b.fin}` + (puedeRetirarBloqueos ? ' · clic para retirar' : '')"
                        @click.stop="puedeRetirarBloqueos && emit('retirar-bloqueo', { sala, bloqueo: b })"
                    >
                        <span class="truncate px-1">{{ textoDe(b, `${b.motivo} ${b.inicio}–${b.fin}`) }}</span>
                    </div>

                    <template v-for="(o, i) in ocupaciones(sala)" :key="`o${i}`">
                        <div
                            v-if="!o.afectada"
                            class="absolute inset-y-1.5 rounded bg-indigo-200 cursor-default"
                            :style="posicion(o.desde - margen, o.hasta + margen)"
                            title="Margen de limpieza: no reservable"
                            @click.stop
                        />
                        <div
                            class="absolute inset-y-1.5 flex items-center justify-center overflow-hidden rounded bg-indigo-500 text-[11px] font-medium text-white cursor-default"
                            :class="{ 'ring-2 ring-amber-400': o.afectada }"
                            :style="posicion(o.desde, o.hasta)"
                            :title="o.afectada ? `Reserva afectada por un bloqueo ${o.inicio}–${o.fin}` : `Ocupada ${o.inicio}–${o.fin}`"
                            @click.stop
                        >
                            <span class="truncate px-1">{{ textoDe(o, `${o.inicio}–${o.fin}`) }}</span>
                        </div>
                    </template>

                    <div
                        v-if="ahora"
                        class="pointer-events-none absolute inset-y-0 w-0.5 bg-red-500"
                        :style="{ left: porcentaje(aMinutos(ahora)) }"
                    />
                </div>
            </template>
        </div>

        <!-- Celular: lista de franjas por sala -->
        <ul class="mt-4 space-y-3 sm:hidden">
            <li v-for="sala in salas" :key="sala.id" class="rounded-md ring-1 ring-gray-200 p-3">
                <div class="flex items-baseline justify-between">
                    <p class="font-medium text-gray-900">{{ sala.nombre }}</p>
                    <p class="text-xs text-gray-500">Aforo {{ sala.aforo }}</p>
                </div>
                <p v-if="franjasDelDia(sala).length === 0" class="mt-2 text-sm text-green-700">Libre todo el día</p>
                <div v-else class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="(f, i) in franjasDelDia(sala)"
                        :key="i"
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-indigo-100 text-indigo-800': f.tipo === 'ocupada',
                            'bg-amber-100 text-amber-800': f.tipo === 'afectada',
                            'bg-gray-200 text-gray-700': f.tipo !== 'ocupada' && f.tipo !== 'afectada',
                        }"
                    >
                        {{ f.inicio }}–{{ f.fin }} · {{ f.tipo }}
                        <button
                            v-if="f.esBloqueo && puedeRetirarBloqueos"
                            type="button"
                            class="ms-1 font-bold text-gray-500 hover:text-red-600"
                            :aria-label="`Retirar bloqueo ${f.inicio}–${f.fin}`"
                            @click="emit('retirar-bloqueo', { sala, bloqueo: f })"
                        >×</button>
                    </span>
                </div>
            </li>
        </ul>
    </div>
</template>
