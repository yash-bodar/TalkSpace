<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Update Password
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Ensure your account is using a long, random password to stay
                secure.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-6">
            <div class="space-y-1.5">
                <label for="current_password" class="block text-xs font-bold text-slate-700">Current Password</label>

                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="w-full bg-white border border-slate-300 focus:border-brand-500 rounded-2xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 transition shadow-xs"
                    autocomplete="current-password"
                />

                <p v-if="form.errors.current_password" class="text-xs text-rose-600 mt-1">{{ form.errors.current_password }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="password" class="block text-xs font-bold text-slate-700">New Password</label>

                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="w-full bg-white border border-slate-300 focus:border-brand-500 rounded-2xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 transition shadow-xs"
                    autocomplete="new-password"
                />

                <p v-if="form.errors.password" class="text-xs text-rose-600 mt-1">{{ form.errors.password }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="password_confirmation" class="block text-xs font-bold text-slate-700">Confirm Password</label>

                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="w-full bg-white border border-slate-300 focus:border-brand-500 rounded-2xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 transition shadow-xs"
                    autocomplete="new-password"
                />

                <p v-if="form.errors.password_confirmation" class="text-xs text-rose-600 mt-1">{{ form.errors.password_confirmation }}</p>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button 
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-50 text-white rounded-2xl text-xs font-bold shadow-md shadow-brand-600/25 transition-all hover:scale-105 active:scale-95"
                >
                    Update Password
                </button>

                <Transition
                    enter-active-class="transition ease-in-out duration-200"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out duration-200"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs text-emerald-600 font-bold"
                    >
                        Password updated.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
