<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import fullLogo from '../../../public/logos/full_logo.png';
import compactLogo from '../../../public/logos/logo.png';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="bg-[#F8FAFC] min-h-screen text-slate-800">
        <div>
            <nav
                class="border-b border-slate-200/80 bg-white/90 backdrop-blur-xl sticky top-0 z-50 shadow-xs"
            >
                <!-- Primary Navigation Menu -->
                <div class="w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between items-center">
                        <div class="flex items-center">
                            <!-- Logo: Responsive Full Logo / Compact Icon -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('chat.index')" class="flex items-center gap-2 group">
                                    <!-- Full logo on larger screens -->
                                    <img 
                                        :src="fullLogo" 
                                        alt="TalkSpace" 
                                        class="hidden sm:block h-9 w-auto object-contain transition-transform group-hover:scale-105"
                                    />
                                    <!-- Compact icon on mobile / collapsed -->
                                    <img 
                                        :src="compactLogo" 
                                        alt="TalkSpace" 
                                        class="block sm:hidden h-9 w-9 object-contain rounded-xl transition-transform group-hover:scale-105"
                                    />
                                </Link>
                            </div>

                        </div>

                        <div class="hidden sm:flex sm:items-center">
                            <!-- User Profile & Settings Dropdown -->
                            <div class="relative">
                                <Dropdown align="right" width="56">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-white p-1.5 pe-3 text-sm font-bold text-slate-800 transition duration-150 ease-in-out hover:bg-slate-50 hover:border-brand-300 focus:outline-none shadow-xs group"
                                        >
                                            <img 
                                                :src="$page.props.auth.user.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent($page.props.auth.user.name) + '&background=D91A8D&color=fff'" 
                                                alt="Avatar" 
                                                class="w-8 h-8 rounded-xl object-cover ring-1 ring-slate-200 group-hover:ring-brand-400 transition shadow-xs"
                                            />
                                            <span class="truncate max-w-[140px]">{{ $page.props.auth.user.name }}</span>

                                            <svg
                                                class="h-4 w-4 text-slate-400 group-hover:text-brand-600 transition"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/50">
                                            <p class="text-xs font-bold text-slate-900 truncate">{{ $page.props.auth.user.name }}</p>
                                            <p class="text-[11px] text-slate-500 truncate">{{ $page.props.auth.user.email }}</p>
                                        </div>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile & Settings
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white shadow dark:bg-gray-800"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
