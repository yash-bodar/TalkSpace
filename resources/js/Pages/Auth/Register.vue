<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Create Account - TalkSpace" />

        <div class="mb-5 text-center">
            <h2 class="text-lg font-extrabold text-slate-900 tracking-tight">Create Account</h2>
            <p class="text-xs text-slate-500 mt-1">Join TalkSpace to connect and chat in real-time.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 mb-1.5">Full Name</label>
                <input
                    id="name"
                    type="text"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition shadow-xs"
                    placeholder="e.g. John Doe"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="block text-xs font-bold text-slate-700 mb-1.5">Email Address</label>
                <input
                    id="email"
                    type="email"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition shadow-xs"
                    placeholder="name@example.com"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="block text-xs font-bold text-slate-700 mb-1.5">Password</label>
                <input
                    id="password"
                    type="password"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition shadow-xs"
                    placeholder="••••••••"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-bold text-slate-700 mb-1.5">Confirm Password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition shadow-xs"
                    placeholder="••••••••"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.password_confirmation" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full mt-2 py-3.5 px-4 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-50 text-white font-bold text-sm rounded-2xl shadow-lg shadow-brand-600/30 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
            >
                <UserPlus class="w-4 h-4" />
                Create Account
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500 pt-4 border-t border-slate-100">
            Already have an account?
            <Link :href="route('login')" class="text-brand-600 hover:text-brand-700 font-bold ml-1 transition">
                Sign In
            </Link>
        </div>
    </GuestLayout>
</template>
