<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';

// YB - 26-08-2026 - iOS Style Verify Email
const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification - TalkSpace" />

        <div class="mb-5 text-center">
            <h2 class="text-lg font-extrabold text-slate-900 tracking-tight">Verify Your Email</h2>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Thanks for signing up! Please verify your email address by clicking the link we sent you.
            </p>
        </div>

        <div
            class="mb-4 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 p-3.5 rounded-2xl"
            v-if="verificationLinkSent"
        >
            A new verification link has been sent to your email address.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full py-3.5 px-4 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-50 text-white font-bold text-sm rounded-2xl shadow-lg shadow-brand-600/30 transition-all hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
            >
                <MailCheck class="w-4 h-4" />
                Resend Verification Email
            </button>

            <div class="pt-3 text-center">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-xs font-bold text-slate-500 hover:text-slate-800 transition cursor-pointer"
                >
                    Log Out
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
