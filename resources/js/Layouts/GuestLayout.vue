<script setup>
import { Link } from '@inertiajs/vue3';
import fullLogo from '../../../public/logos/full_logo.png';

// YB - 26-08-2026 - Premium iOS Guest Auth Layout
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-[#F4F6FB] p-4 text-slate-800 relative overflow-hidden font-sans select-text"
    >
        <!-- Background Soft Gradient Atmospheric Glows -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[650px] h-[450px] bg-gradient-to-tr from-brand-500/10 via-purple-400/10 to-transparent blur-[140px] rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-10 w-96 h-96 bg-brand-500/5 blur-[120px] rounded-full pointer-events-none"></div>

        <!-- Premium iOS Style Floating Auth Card -->
        <div
            class="w-full sm:max-w-[430px] bg-white px-8 py-9 shadow-2xl shadow-slate-300/40 rounded-[28px] border border-slate-100/90 z-10 relative transition-all"
        >
            <!-- Centered TalkSpace Logo Header -->
            <div class="flex flex-col items-center mb-6">
                <Link :href="route('chat.index')" class="flex flex-col items-center group">
                    <img 
                        :src="fullLogo" 
                        alt="TalkSpace" 
                        class="h-11 w-auto object-contain transition-transform duration-200 group-hover:scale-105"
                    />
                </Link>
            </div>

            <slot />
        </div>
    </div>
</template>
