<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BitacoraDeIntegracion from '@/Components/Nodo/BitacoraDeIntegracion.vue';
import DangerButton from '@/Components/DangerButton.vue';

defineProps({
    reservas: Array,
    bitacora: Array,
});

const esRecepcion = computed(() => usePage().props.actor?.rol === 'recepcion');

const coloresDeEstado = {
    confirmada: 'bg-green-100 text-green-800',
    rechazada: 'bg-red-100 text-red-800',
    cancelada: 'bg-gray-200 text-gray-700',
    solicitada: 'bg-yellow-100 text-yellow-800',
};

const cancelar = (reserva) => {
    if (confirm(`¿Cancelar la reserva de ${reserva.sala} del ${reserva.fecha} ${reserva.inicio}–${reserva.fin}?`)) {
        router.post(route('reservas.cancelar', reserva.id), {}, { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout :title="esRecepcion ? 'Todas las reservas' : 'Mis reservas'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ esRecepcion ? 'Todas las reservas' : 'Mis reservas' }}
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <section class="bg-white shadow sm:rounded-lg overflow-x-auto">
                    <p v-if="reservas.length === 0" class="p-6 text-sm text-gray-500">No hay reservas todavía.</p>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Sala y franja</th>
                                <th class="px-4 py-3">Titular</th>
                                <th class="px-4 py-3">Asistentes</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3">Precio conservado</th>
                                <th class="px-4 py-3">Penalización</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="reserva in reservas" :key="reserva.id" class="align-top">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ reserva.sala }}</p>
                                    <p class="text-gray-500">{{ reserva.fecha }} · {{ reserva.inicio }}–{{ reserva.fin }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-gray-900">{{ reserva.titular }}</p>
                                    <p v-if="reserva.registradaPor" class="text-xs text-gray-500">Asistida por {{ reserva.registradaPor }}</p>
                                </td>
                                <td class="px-4 py-3">{{ reserva.asistentes }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="coloresDeEstado[reserva.estado]">{{ reserva.estado }}</span>
                                    <p v-if="reserva.motivoDeRechazo" class="mt-1 max-w-xs text-xs text-red-700">{{ reserva.motivoDeRechazo }}</p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ reserva.precio ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ reserva.penalizacion ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <DangerButton v-if="reserva.puedeCancelar" @click="cancelar(reserva)">Cancelar</DangerButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <BitacoraDeIntegracion :entradas="bitacora" />
            </div>
        </div>
    </AppLayout>
</template>
