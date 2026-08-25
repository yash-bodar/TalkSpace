<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { User, ShieldCheck, KeyRound, AlertTriangle } from 'lucide-vue-next';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Account Settings — TalkSpace" />

    <AuthenticatedLayout>
        <div class="min-h-[calc(100vh-4rem)] bg-[#F8FAFC] py-10 px-4 sm:px-6 lg:px-8 text-slate-800 font-sans">
            <div class="max-w-4xl mx-auto space-y-8">
                
                <!-- Hero Profile Banner -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-600 via-brand-700 to-purple-800 border border-brand-500/30 p-6 md:p-8 shadow-xl shadow-brand-900/10 text-white">
                    <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="relative flex flex-col sm:flex-row items-center sm:items-start gap-6">
                        <div class="relative">
                            <img 
                                :src="user.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name)" 
                                alt="Profile Avatar"
                                class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl object-cover ring-4 ring-white/40 shadow-2xl"
                            />
                            <span class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-400 rounded-full border-2 border-brand-800 glow-emerald"></span>
                        </div>
                        <div class="flex-1 text-center sm:text-left space-y-1.5">
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/15 border border-white/20 rounded-full text-white text-xs font-semibold">
                                <ShieldCheck class="w-3.5 h-3.5 text-brand-200" />
                                <span>Verified Account</span>
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ user.name }}</h1>
                            <p class="text-sm text-brand-100">{{ user.email }}</p>
                        </div>
                    </div>
                </div>

                <!-- 1. Profile Information Form -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 transition-all hover:border-brand-300">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-2xl bg-brand-50 border border-brand-200/60 flex items-center justify-center text-brand-600">
                            <User class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Profile Details</h2>
                            <p class="text-xs text-slate-500">Update your account name and email address.</p>
                        </div>
                    </div>

                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-2xl"
                    />
                </div>

                <!-- 2. Security / Password Form -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 transition-all hover:border-brand-300">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-2xl bg-purple-50 border border-purple-200/60 flex items-center justify-center text-purple-600">
                            <KeyRound class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Update Password</h2>
                            <p class="text-xs text-slate-500">Keep your account protected with strong credentials.</p>
                        </div>
                    </div>

                    <UpdatePasswordForm class="max-w-2xl" />
                </div>

                <!-- 3. Danger Zone -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-rose-200/80">
                    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                        <div class="w-10 h-10 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600">
                            <AlertTriangle class="w-5 h-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 tracking-tight">Danger Zone</h2>
                            <p class="text-xs text-rose-500">Permanently remove your account and all communication data.</p>
                        </div>
                    </div>

                    <DeleteUserForm class="max-w-2xl" />
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
