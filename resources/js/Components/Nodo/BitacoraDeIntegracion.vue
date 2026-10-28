<script setup>
defineProps({
    entradas: Array,
});
</script>

<template>
    <section class="bg-white shadow sm:rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900">Bitácora de integración</h3>
        <p class="mt-1 text-sm text-gray-600">
            Domain events publicados y lo que cruzaría hacia cada vecino simulado.
        </p>

        <p v-if="entradas.length === 0" class="mt-4 text-sm text-gray-500">Todavía no hay eventos.</p>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="entrada in entradas" :key="entrada.id" class="py-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded bg-orange-100 px-2 py-0.5 font-semibold text-orange-800">{{ entrada.evento }}</span>
                    <span class="text-gray-400">→</span>
                    <span class="font-medium text-gray-800">{{ entrada.destino }}</span>
                    <span class="ms-auto text-xs text-gray-400">{{ entrada.ocurrido }}</span>
                </div>
                <code class="mt-1 block break-all text-xs text-gray-500">{{ JSON.stringify(entrada.mensaje) }}</code>
            </li>
        </ul>
    </section>
</template>
