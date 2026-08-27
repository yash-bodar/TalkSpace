<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';

// YB - 26-08-2026 - iOS Style Confirm Password
const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password - TalkSpace" />

        <div class="mb-5 text-center">
            <h2 class="text-lg font-extrabold text-slate-900 tracking-tight">Security Check</h2>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                This is a secure area. Please confirm your password before continuing.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="password" class="block text-xs font-bold text-slate-700 mb-1.5">Password</label>
                <input
                    id="password"
                    type="password"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition shadow-xs"
                    placeholder="••••••••"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.password" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full mt-2 py-3.5 px-4 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-50 text-white font-bold text-sm rounded-2xl shadow-lg shadow-brand-600/30 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
            >
                <ShieldCheck class="w-4 h-4" />
                Confirm Password
            </button>
        </form>
    </GuestLayout>
</template>
