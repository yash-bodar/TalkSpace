<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Delete Account
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Once your account is deleted, all of its resources and data will
                be permanently deleted. Before deleting your account, please
                download any data or information that you wish to retain.
            </p>
        </header>

        <button 
            @click="confirmUserDeletion"
            class="px-6 py-2.5 bg-rose-50 hover:bg-rose-600 border border-rose-200 hover:border-rose-600 text-rose-600 hover:text-white rounded-2xl text-xs font-bold transition-all hover:scale-105 active:scale-95 shadow-xs"
        >
            Delete Account
        </button>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6 bg-white border border-slate-200 rounded-3xl space-y-4 text-slate-800">
                <h2 class="text-lg font-bold text-slate-900 tracking-tight">
                    Are you sure you want to delete your account?
                </h2>

                <p class="text-xs text-slate-500 leading-relaxed">
                    Once your account is deleted, all of its messages, channels, and conversation history will be permanently deleted. Please enter your password to confirm.
                </p>

                <div class="mt-4 space-y-1.5">
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="w-full bg-white border border-slate-300 focus:border-rose-500 rounded-2xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20 transition shadow-xs"
                        placeholder="Enter your current password"
                        @keyup.enter="deleteUser"
                    />

                    <p v-if="form.errors.password" class="text-xs text-rose-600 mt-1">{{ form.errors.password }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button 
                        @click="closeModal"
                        class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                    >
                        Cancel
                    </button>

                    <button
                        :class="{ 'opacity-50': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                        class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white shadow-md shadow-rose-600/25 transition"
                    >
                        Permanently Delete
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
