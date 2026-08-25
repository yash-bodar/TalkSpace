<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import { Camera, Image as ImageIcon, Trash2, AlertCircle, Crop, ZoomIn, ZoomOut, RotateCw, Check, X } from 'lucide-vue-next';

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
const avatarPreview = ref(null);
const fileInput = ref(null);
const avatarError = ref(null);

// Cropper States
const isCropperOpen = ref(false);
const rawImageSrc = ref(null);
const cropZoom = ref(1);
const cropOffset = reactive({ x: 0, y: 0 });
const isDragging = ref(false);
const dragStart = reactive({ x: 0, y: 0 });
const imageElement = ref(null);

const form = useForm({
    _method: 'PATCH',
    name: user.value?.name || '',
    email: user.value?.email || '',
    avatar: null,
});

const onAvatarChange = (e) => {
    avatarError.value = null;
    const file = e.target.files[0];
    if (!file) return;

    // 5MB Limit validation
    if (file.size > 5 * 1024 * 1024) {
        avatarError.value = 'Profile photo must be less than 5 MB.';
        if (fileInput.value) fileInput.value.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        rawImageSrc.value = event.target.result;
        cropZoom.value = 1;
        cropOffset.x = 0;
        cropOffset.y = 0;
        isCropperOpen.value = true;
    };
    reader.readAsDataURL(file);
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

// Drag / Pan controls
const startDrag = (e) => {
    isDragging.value = true;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    dragStart.x = clientX - cropOffset.x;
    dragStart.y = clientY - cropOffset.y;
};

const onDrag = (e) => {
    if (!isDragging.value) return;
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    cropOffset.x = clientX - dragStart.x;
    cropOffset.y = clientY - dragStart.y;
};

const endDrag = () => {
    isDragging.value = false;
};

// Apply Crop using HTML Canvas
const applyCrop = () => {
    const img = imageElement.value;
    if (!img) return;

    const canvas = document.createElement('canvas');
    const cropSize = 400; // Output square avatar resolution
    canvas.width = cropSize;
    canvas.height = cropSize;
    const ctx = canvas.getContext('2d');

    const viewportSize = 250; // Viewport mask box in UI
    const scale = (img.naturalWidth / (img.width || viewportSize)) / cropZoom.value;

    const sourceX = (-cropOffset.x + (viewportSize / 2) * (cropZoom.value - 1)) * scale;
    const sourceY = (-cropOffset.y + (viewportSize / 2) * (cropZoom.value - 1)) * scale;
    const sourceSize = viewportSize * scale;

    ctx.drawImage(
        img,
        sourceX,
        sourceY,
        sourceSize,
        sourceSize,
        0,
        0,
        cropSize,
        cropSize
    );

    canvas.toBlob((blob) => {
        if (!blob) return;
        const croppedFile = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
        form.avatar = croppedFile;
        avatarPreview.value = URL.createObjectURL(croppedFile);
        isCropperOpen.value = false;
    }, 'image/jpeg', 0.92);
};

const cancelCrop = () => {
    isCropperOpen.value = false;
    rawImageSrc.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

const submitForm = () => {
    form.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Keep local preview if needed or let props.auth.user take over
            setTimeout(() => {
                avatarPreview.value = null;
            }, 300);
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-bold text-white tracking-tight">
                Profile Information
            </h2>

            <p class="mt-1 text-xs text-slate-400">
                Update your account's profile photo, display name, and email address.
            </p>
        </header>

        <form
            @submit.prevent="submitForm"
            class="space-y-6 mt-6"
        >
            <!-- Avatar Upload Section -->
            <div class="p-5 bg-slate-50 border border-slate-200/80 rounded-3xl space-y-3">
                <label class="block text-xs font-bold text-slate-700">Profile Photo (Max 5 MB)</label>
                
                <div class="flex items-center gap-5">
                    <div class="relative group cursor-pointer" @click="triggerFileInput">
                        <img 
                            :src="avatarPreview || user.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name)" 
                            alt="Avatar Preview"
                            class="w-20 h-20 rounded-2xl object-cover ring-2 ring-brand-500/40 group-hover:ring-brand-500 shadow-md transition"
                        />
                        <div class="absolute inset-0 bg-black/40 rounded-2xl flex flex-col items-center justify-center text-white opacity-0 group-hover:opacity-100 transition backdrop-blur-xs">
                            <Camera class="w-5 h-5 text-white" />
                            <span class="text-[10px] font-bold mt-1">Change</span>
                        </div>
                    </div>

                    <div class="space-y-1.5 flex-1">
                        <input 
                            ref="fileInput"
                            type="file" 
                            accept="image/png, image/jpeg, image/jpg, image/webp" 
                            class="hidden" 
                            @change="onAvatarChange"
                        />
                        <button 
                            type="button" 
                            @click="triggerFileInput"
                            class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold border border-slate-300 shadow-xs transition flex items-center gap-2"
                        >
                            <ImageIcon class="w-3.5 h-3.5 text-brand-600" />
                            <span>Upload New Photo</span>
                        </button>
                        <p class="text-[11px] text-slate-500">Supported: JPG, PNG, WEBP. Maximum file size: 5MB.</p>
                        <p v-if="avatarError || form.errors.avatar" class="text-xs text-rose-600 font-semibold flex items-center gap-1 mt-1">
                            <AlertCircle class="w-3.5 h-3.5 flex-shrink-0" />
                            <span>{{ avatarError || form.errors.avatar }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="name" class="block text-xs font-bold text-slate-700">Full Name</label>

                <input
                    id="name"
                    type="text"
                    class="w-full bg-white border border-slate-300 focus:border-brand-500 rounded-2xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 transition shadow-xs"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <p v-if="form.errors.name" class="text-xs text-rose-600 mt-1">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="email" class="block text-xs font-bold text-slate-700">Email Address</label>

                <input
                    id="email"
                    type="email"
                    class="w-full bg-white border border-slate-300 focus:border-brand-500 rounded-2xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 transition shadow-xs"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <p v-if="form.errors.email" class="text-xs text-rose-600 mt-1">{{ form.errors.email }}</p>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-amber-600">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-brand-600 underline hover:text-brand-700 ml-1 font-semibold"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-emerald-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <button 
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-50 text-white rounded-2xl text-xs font-bold shadow-lg shadow-brand-600/25 transition-all hover:scale-105 active:scale-95"
                >
                    Save Changes
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
                        Saved successfully.
                    </p>
                </Transition>
            </div>
        </form>

        <!-- Interactive Image Cropper Modal -->
        <div 
            v-if="isCropperOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md overflow-y-auto animate-in fade-in duration-200"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl p-5 sm:p-6 space-y-4 animate-in zoom-in-95 duration-200 custom-scrollbar">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <Crop class="w-4 h-4 text-brand-600" />
                        <span>Crop Profile Photo</span>
                    </h3>
                    <button 
                        @click="cancelCrop" 
                        class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Crop Viewport Container -->
                <div 
                    class="relative w-full h-64 bg-slate-900 rounded-2xl overflow-hidden cursor-grab active:cursor-grabbing border border-slate-200 flex items-center justify-center select-none"
                    @mousedown="startDrag"
                    @mousemove="onDrag"
                    @mouseup="endDrag"
                    @mouseleave="endDrag"
                    @touchstart="startDrag"
                    @touchmove="onDrag"
                    @touchend="endDrag"
                >
                    <!-- Draggable & Scalable Image -->
                    <img 
                        ref="imageElement"
                        :src="rawImageSrc" 
                        alt="Crop target" 
                        class="max-w-none pointer-events-none transition-transform duration-75 origin-center"
                        :style="{
                            transform: `translate(${cropOffset.x}px, ${cropOffset.y}px) scale(${cropZoom})`,
                            width: '240px',
                            height: 'auto'
                        }"
                    />

                    <!-- Squircle Mask Overlay (matching TalkSpace avatars) -->
                    <div class="absolute inset-0 pointer-events-none border-4 border-brand-500 rounded-3xl w-52 h-52 m-auto shadow-[0_0_0_9999px_rgba(15,23,42,0.85)]"></div>
                </div>

                <!-- Zoom Slider Controls -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs text-slate-600 font-semibold">
                        <span class="flex items-center gap-1"><ZoomOut class="w-3.5 h-3.5 text-brand-600" /> Zoom</span>
                        <span>{{ Math.round(cropZoom * 100) }}%</span>
                    </div>
                    <input 
                        type="range" 
                        min="1" 
                        max="3" 
                        step="0.05" 
                        v-model.number="cropZoom" 
                        class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-600"
                    />
                    <p class="text-[11px] text-slate-500 text-center">Drag image to frame inside the avatar box</p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <button 
                        type="button" 
                        @click="cancelCrop"
                        class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        @click="applyCrop"
                        class="px-6 py-2.5 rounded-2xl text-xs font-bold bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 text-white shadow-lg shadow-brand-600/25 transition flex items-center gap-1.5"
                    >
                        <Check class="w-4 h-4" />
                        <span>Apply & Crop</span>
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>
