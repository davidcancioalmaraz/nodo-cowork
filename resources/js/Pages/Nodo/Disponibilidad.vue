<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BitacoraDeIntegracion from '@/Components/Nodo/BitacoraDeIntegracion.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import LineaDeTiempoDelDia from '@/Components/Nodo/LineaDeTiempoDelDia.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    fecha: String,
    hoy: String,
    ahora: String,
    salas: Array,
    miembros: Array,
    bitacora: Array,
});

const esRecepcion = computed(() => usePage().props.actor?.rol === 'recepcion');

// Navegación por días: las fechas se manejan como texto Y-m-d.
const desplazar = (fecha, dias) => {
    const dia = new Date(`${fecha}T12:00:00`);
    dia.setDate(dia.getDate() + dias);

    return [dia.getFullYear(), dia.getMonth() + 1, dia.getDate()].map((n) => String(n).padStart(2, '0')).join('-');
};
const verDia = (fecha) => router.get(route('disponibilidad'), { fecha }, { preserveScroll: true });
const diaEnTexto = computed(() => new Intl.DateTimeFormat('es-BO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    .format(new Date(`${props.fecha}T12:00:00`)));

const solicitud = useForm({
    sala_id: props.salas[0]?.id ?? '',
    fecha: props.fecha,
    inicio: '10:00',
    fin: '11:00',
    asistentes: 2,
    titular_id: esRecepcion.value ? (props.miembros[0]?.id ?? '') : undefined,
});

// Clic en un hueco de la línea de tiempo: precarga la solicitud.
const formularioDeSolicitud = ref(null);
const campoAsistentes = ref(null);

const elegirHueco = ({ salaId, inicio }) => {
    const [h, m] = inicio.split(':').map(Number);
    const fin = Math.min(h * 60 + m + 60, 23 * 60 + 59);

    solicitud.sala_id = salaId;
    solicitud.fecha = props.fecha;
    solicitud.inicio = inicio;
    solicitud.fin = `${String(Math.floor(fin / 60)).padStart(2, '0')}:${String(fin % 60).padStart(2, '0')}`;

    formularioDeSolicitud.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
    campoAsistentes.value.focus({ preventScroll: true });
};

const solicitar = () => {
    solicitud.post(route('reservas.store'), { preserveScroll: true });
};

const bloqueo = useForm({
    sala_id: props.salas[0]?.id ?? '',
    fecha: props.fecha,
    inicio: '14:00',
    fin: '15:00',
    motivo: 'mantenimiento',
});

const bloquear = () => {
    bloqueo.post(route('bloqueos.store'), { preserveScroll: true });
};

// Bloqueos del día, para retirarlos (D-53).
const bloqueosDelDia = computed(() => props.salas
    .flatMap((sala) => sala.bloqueos.map((b) => ({ sala, bloqueo: b })))
    .sort((x, y) => x.bloqueo.inicio.localeCompare(y.bloqueo.inicio)));

const retirarBloqueo = ({ sala, bloqueo: b }) => {
    if (confirm(`¿Retirar el bloqueo por ${b.motivo} de ${sala.nombre}, ${b.inicio}–${b.fin}? La franja volverá a estar disponible.`)) {
        router.delete(route('bloqueos.destroy', { sala: sala.id, fecha: props.fecha, bloqueo: b.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout title="Disponibilidad">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Salas disponibles</h2>
                    <p class="mt-1 text-sm text-gray-500 first-letter:uppercase">{{ diaEnTexto }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <SecondaryButton @click="verDia(desplazar(fecha, -1))">◀ Anterior</SecondaryButton>
                    <SecondaryButton :disabled="fecha === hoy" @click="verDia(hoy)">Hoy</SecondaryButton>
                    <SecondaryButton @click="verDia(desplazar(fecha, 1))">Siguiente ▶</SecondaryButton>
                    <TextInput type="date" aria-label="Elegir día" :model-value="fecha" @update:model-value="verDia" />
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Modelo de lectura: Salas disponibles -->
                <section class="bg-white shadow sm:rounded-lg p-6">
                    <LineaDeTiempoDelDia
                        :salas="salas"
                        :ahora="fecha === hoy ? ahora : null"
                        :puede-retirar-bloqueos="esRecepcion"
                        @elegir="elegirHueco"
                        @retirar-bloqueo="retirarBloqueo"
                    />
                </section>

                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Solicitar reserva -->
                    <section class="bg-white shadow sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ esRecepcion ? 'Reserva asistida' : 'Solicitar reserva' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">
                            <template v-if="esRecepcion">
                                Recepción registra la reserva a nombre de un miembro; se permite desde 3 h de anticipación.
                            </template>
                            <template v-else>
                                Confirmación automática con 24 h o más de anticipación y la membresía al día.
                            </template>
                        </p>

                        <form ref="formularioDeSolicitud" class="mt-4 grid grid-cols-2 gap-4" @submit.prevent="solicitar">
                            <div v-if="esRecepcion" class="col-span-2">
                                <InputLabel for="titular" value="A nombre de" />
                                <select id="titular" v-model="solicitud.titular_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option v-for="miembro in miembros" :key="miembro.id" :value="miembro.id">{{ miembro.nombre }}</option>
                                </select>
                                <InputError class="mt-2" :message="solicitud.errors.titular_id" />
                            </div>
                            <div class="col-span-2">
                                <InputLabel for="sala" value="Sala" />
                                <select id="sala" v-model="solicitud.sala_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option v-for="sala in salas" :key="sala.id" :value="sala.id">{{ sala.nombre }} (aforo {{ sala.aforo }})</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="fecha" value="Fecha" />
                                <TextInput id="fecha" v-model="solicitud.fecha" type="date" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="solicitud.errors.fecha" />
                            </div>
                            <div>
                                <InputLabel for="asistentes" value="Asistentes" />
                                <TextInput id="asistentes" ref="campoAsistentes" v-model="solicitud.asistentes" type="number" min="1" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="solicitud.errors.asistentes" />
                            </div>
                            <div>
                                <InputLabel for="inicio" value="Inicio" />
                                <TextInput id="inicio" v-model="solicitud.inicio" type="time" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="solicitud.errors.inicio" />
                            </div>
                            <div>
                                <InputLabel for="fin" value="Fin" />
                                <TextInput id="fin" v-model="solicitud.fin" type="time" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="solicitud.errors.fin" />
                            </div>
                            <div class="col-span-2 flex justify-end">
                                <PrimaryButton :disabled="solicitud.processing">Solicitar</PrimaryButton>
                            </div>
                        </form>
                    </section>

                    <!-- Bloquear sala (solo recepción) -->
                    <section v-if="esRecepcion" class="bg-white shadow sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900">Bloquear sala</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Saca una franja de disponibilidad por mantenimiento o limpieza. No admite nuevas ocupaciones.
                        </p>

                        <form class="mt-4 grid grid-cols-2 gap-4" @submit.prevent="bloquear">
                            <div class="col-span-2">
                                <InputLabel for="sala-bloqueo" value="Sala" />
                                <select id="sala-bloqueo" v-model="bloqueo.sala_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option v-for="sala in salas" :key="sala.id" :value="sala.id">{{ sala.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="fecha-bloqueo" value="Fecha" />
                                <TextInput id="fecha-bloqueo" v-model="bloqueo.fecha" type="date" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <InputLabel for="motivo" value="Motivo" />
                                <select id="motivo" v-model="bloqueo.motivo" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="mantenimiento">Mantenimiento</option>
                                    <option value="limpieza">Limpieza</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="inicio-bloqueo" value="Inicio" />
                                <TextInput id="inicio-bloqueo" v-model="bloqueo.inicio" type="time" class="mt-1 block w-full" required />
                            </div>
                            <div>
                                <InputLabel for="fin-bloqueo" value="Fin" />
                                <TextInput id="fin-bloqueo" v-model="bloqueo.fin" type="time" class="mt-1 block w-full" required />
                            </div>
                            <div class="col-span-2 flex justify-end">
                                <SecondaryButton type="submit" :disabled="bloqueo.processing">Bloquear</SecondaryButton>
                            </div>
                        </form>

                        <div class="mt-6 border-t border-gray-100 pt-4">
                            <h4 class="text-sm font-semibold text-gray-900">Bloqueos de este día</h4>
                            <p v-if="bloqueosDelDia.length === 0" class="mt-2 text-sm text-gray-500">Sin bloqueos.</p>
                            <ul v-else class="mt-2 divide-y divide-gray-100">
                                <li v-for="{ sala, bloqueo: b } in bloqueosDelDia" :key="b.id" class="flex items-center justify-between gap-4 py-2 text-sm">
                                    <span>
                                        <span class="font-medium text-gray-900">{{ sala.nombre }}</span>
                                        <span class="text-gray-600"> · {{ b.inicio }}–{{ b.fin }} · {{ b.motivo }}</span>
                                    </span>
                                    <DangerButton type="button" @click="retirarBloqueo({ sala, bloqueo: b })">Retirar</DangerButton>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <BitacoraDeIntegracion v-else :entradas="bitacora" />
                </div>

                <BitacoraDeIntegracion v-if="esRecepcion" :entradas="bitacora" />
            </div>
        </div>
    </AppLayout>
</template>
