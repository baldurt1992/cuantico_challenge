<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
const form = useForm({ email: "", password: "" });
function submit() {
    form.post("/login", { onFinish: () => form.reset("password") });
}
</script>

<template>
    <UApp>
        <Head title="Ingresar" />
        <main class="min-h-screen flex items-center justify-center p-6">
            <UCard
                class="w-full max-w-md"
                :ui="{ body: 'p-8 sm:p-10' }"
                aria-labelledby="login-title"
            >
                <div
                    class="flex items-center gap-2 text-primary mb-10 font-semibold"
                >
                    <UIcon name="i-lucide-route" class="size-6" /> Operaciones
                </div>
                <h1
                    id="login-title"
                    class="text-3xl font-semibold tracking-tight"
                >
                    Operaciones de entrega
                </h1>
                <p class="mt-3 text-muted leading-relaxed">
                    Consulta entregas, revisa cada costo y pregunta al asistente
                    de tu comercio.
                </p>
                <UForm :state="form" class="mt-8 space-y-5" @submit="submit">
                    <UFormField
                        label="Correo"
                        name="email"
                        :error="form.errors.email"
                        ><UInput
                            v-model="form.email"
                            name="email"
                            type="email"
                            autocomplete="username"
                            required
                            class="w-full"
                            size="lg"
                    /></UFormField>
                    <UFormField
                        label="Contraseña"
                        name="password"
                        :error="form.errors.password"
                        ><UInput
                            v-model="form.password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full"
                            size="lg"
                    /></UFormField>
                    <UButton
                        type="submit"
                        :loading="form.processing"
                        block
                        size="lg"
                        >Ingresar a operaciones
                        <UIcon name="i-lucide-arrow-right"
                    /></UButton>
                </UForm>
                <p class="mt-8 text-xs text-muted">
                    Entorno de evaluación con datos sintéticos. Solicita tus
                    credenciales de acceso al autor.
                </p>
            </UCard>
        </main>
    </UApp>
</template>
