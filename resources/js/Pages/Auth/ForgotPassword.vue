<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, ArrowLeft } from 'lucide-vue-next';

// YB - 26-08-2026 - iOS Style Forgot Password
defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password - TalkSpace" />

        <div class="mb-5 text-center">
            <h2 class="text-lg font-extrabold text-slate-900 tracking-tight">Forgot Password</h2>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Enter your email address and we'll send you a password reset link.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 p-3.5 rounded-2xl"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-xs font-bold text-slate-700 mb-1.5">Email Address</label>
                <input
                    id="email"
                    type="email"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition shadow-xs"
                    placeholder="name@example.com"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.email" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full mt-2 py-3.5 px-4 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-50 text-white font-bold text-sm rounded-2xl shadow-lg shadow-brand-600/30 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
            >
                <Mail class="w-4 h-4" />
                Send Reset Link
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500 pt-4 border-t border-slate-100">
            Remember your password?
            <Link :href="route('login')" class="text-brand-600 hover:text-brand-700 font-bold ml-1 transition inline-flex items-center gap-1">
                Sign In
            </Link>
        </div>
    </GuestLayout>
</template>
