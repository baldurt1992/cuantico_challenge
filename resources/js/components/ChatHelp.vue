<script setup lang="ts">
import type { AgentHelp, HelpTarget } from "../types";

defineProps<{ help: AgentHelp; disabled?: boolean }>();
const emit = defineEmits<{
    navigate: [target: HelpTarget];
    ask: [prompt: string];
}>();
function navigate(event: MouseEvent, target: HelpTarget) {
    if (
        event.ctrlKey ||
        event.metaKey ||
        event.shiftKey ||
        event.altKey ||
        event.button !== 0
    )
        return;
    emit("navigate", target);
}
</script>

<template>
    <div class="space-y-3 mt-3 text-sm">
        <p class="text-xs text-muted">
            {{ help.merchant_name }} ·
            {{ help.role === "operator" ? "Operador" : "Lector" }}
        </p>
        <p class="font-medium">Lo que puedo responder aquí</p>
        <p class="text-xs text-muted">
            Pulsa una pregunta para enviarla y ver la respuesta en este chat.
            También puedes escribirla con tus propias fechas o filtros.
        </p>
        <ul class="space-y-3" aria-label="Consultas disponibles en el chat">
            <li
                v-for="query in help.queries"
                :key="query.label"
                class="space-y-2"
            >
                <p class="font-medium">{{ query.label }}</p>
                <p class="text-xs text-muted">{{ query.description }}</p>
                <UButton
                    v-if="query.prompt"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="whitespace-normal text-left"
                    icon="i-lucide-send"
                    :disabled="disabled"
                    @click="emit('ask', query.prompt)"
                    >{{ query.prompt }}</UButton
                >
                <p v-else class="text-xs text-muted">
                    Este comercio todavía no tiene mensajeros asignados para
                    esta consulta.
                </p>
            </li>
        </ul>
        <p class="font-medium pt-2">Accesos al panel</p>
        <p class="text-xs text-muted">
            Estos enlaces abren la interfaz; no realizan una consulta en el
            chat.
        </p>
        <ul class="space-y-3" aria-label="Comandos y accesos disponibles">
            <li
                v-for="action in help.actions"
                :key="action.command"
                class="rounded-lg bg-elevated/50 p-3"
            >
                <UButton
                    :to="action.href"
                    external
                    variant="link"
                    class="p-0 text-left whitespace-normal"
                    trailing-icon="i-lucide-arrow-up-right"
                    @click="navigate($event, action.target)"
                    >{{ action.label }}</UButton
                >
                <p class="text-xs text-muted mt-1">{{ action.description }}</p>
                <code class="block text-xs text-primary mt-2">{{
                    action.command
                }}</code>
            </li>
        </ul>
        <p class="text-xs text-muted">
            Escribe <code>/help</code> para ver los comandos y tus permisos. Los
            comandos con / muestran accesos al panel. Las preguntas anteriores
            se responden aquí.
        </p>
    </div>
</template>
