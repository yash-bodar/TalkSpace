<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LogIn, User } from 'lucide-vue-next';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const fillTestAccount = (email, password = 'password') => {
    form.email = email;
    form.password = password;
};
</script>

<template>
    <GuestLayout>
        <Head title="Sign In - TalkSpace" />

        <div v-if="status" class="mb-5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 p-3.5 rounded-2xl">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4.5">
            <div>
                <label for="email" class="block text-xs font-bold text-slate-700 mb-1.5">Email Address</label>
                <input
                    id="email"
                    type="email"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition"
                    placeholder="name@example.com"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-bold text-slate-700">Password</label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-brand-600 hover:text-brand-700 font-bold transition"
                    >
                        Forgot?
                    </Link>
                </div>

                <input
                    id="password"
                    type="password"
                    class="w-full bg-white text-slate-800 placeholder:text-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition"
                    placeholder="••••••••"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-1.5 text-xs" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" class="rounded-md bg-white border-slate-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-xs font-medium text-slate-600 select-none">Remember this device</span>
                </label>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full mt-2 py-3.5 px-4 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-50 text-white font-bold text-sm rounded-2xl shadow-lg shadow-brand-600/30 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
            >
                <LogIn class="w-4 h-4" />
                Sign In
            </button>
        </form>

        <!-- Quick 1-Click Test Accounts Login Selector -->
        <div class="mt-6 pt-5 border-t border-slate-100">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 text-center mb-2.5">
                ⚡ 1-Click Quick Test Accounts
            </p>
            <div class="grid grid-cols-2 gap-2">
                <button 
                    type="button"
                    @click="fillTestAccount('yash@parextech.com', 'Yash@123')"
                    class="p-2.5 bg-slate-50 hover:bg-brand-50/50 hover:border-brand-300 border border-slate-200/80 rounded-2xl text-left transition flex items-center gap-2 group cursor-pointer"
                >
                    <img src="https://ui-avatars.com/api/?name=Yash+Bodar&background=d91a8d&color=fff" class="w-7 h-7 rounded-xl shadow-xs" />
                    <div class="truncate">
                        <p class="text-xs font-bold text-slate-800 group-hover:text-brand-600 truncate">Yash Bodar</p>
                        <p class="text-[10px] text-slate-500 truncate">yash@parextech</p>
                    </div>
                </button>

                <button 
                    type="button"
                    @click="fillTestAccount('gautam@parextech.com', 'Gautam@123')"
                    class="p-2.5 bg-slate-50 hover:bg-brand-50/50 hover:border-brand-300 border border-slate-200/80 rounded-2xl text-left transition flex items-center gap-2 group cursor-pointer"
                >
                    <img src="https://ui-avatars.com/api/?name=Gautam&background=8b1e7c&color=fff" class="w-7 h-7 rounded-xl shadow-xs" />
                    <div class="truncate">
                        <p class="text-xs font-bold text-slate-800 group-hover:text-brand-600 truncate">Gautam</p>
                        <p class="text-[10px] text-slate-500 truncate">gautam@parextech</p>
                    </div>
                </button>
            </div>
        </div>

        <div class="mt-6 text-center text-xs text-slate-500">
            Don't have an account?
            <Link :href="route('register')" class="text-brand-600 hover:text-brand-700 font-bold ml-1 transition">
                Create an account
            </Link>
        </div>
    </GuestLayout>
</template>
