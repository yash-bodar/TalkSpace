<script setup>
import { Link } from '@inertiajs/vue3';
import fullLogo from '../../../public/logos/full_logo.png';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-[#F1F5F9] p-4 text-slate-800 relative overflow-hidden font-sans"
    >
        <!-- Background Soft Gradient Glows -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] bg-gradient-to-tr from-brand-500/10 via-purple-400/10 to-transparent blur-[140px] rounded-full pointer-events-none"></div>
        <div class="absolute bottom-10 right-10 w-96 h-96 bg-brand-500/5 blur-[120px] rounded-full pointer-events-none"></div>

        <!-- Logo & Branding -->
        <div class="mb-6 flex flex-col items-center space-y-2 z-10">
            <Link :href="route('chat.index')" class="flex flex-col items-center gap-2 group">
                <img 
                    :src="fullLogo" 
                    alt="TalkSpace" 
                    class="h-12 w-auto object-contain transition-transform duration-200 group-hover:scale-105"
                />
            </Link>
        </div>

        <!-- Crisp Card -->
        <div
            class="w-full sm:max-w-md overflow-hidden bg-white/95 backdrop-blur-2xl px-8 py-8 shadow-xl shadow-slate-200/60 rounded-3xl border border-slate-200/80 z-10 relative"
        >
            <slot />
        </div>
    </div>
</template>
