<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick, defineAsyncComponent } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    Search, 
    Plus, 
    Send, 
    Paperclip, 
    X, 
    FileText, 
    Image as ImageIcon, 
    Check, 
    CheckCheck, 
    Users, 
    MessageSquare, 
    Circle,
    UserCheck,
    ArrowLeft,
    MoreVertical,
    Phone,
    Video,
    User,
    Mail,
    Calendar,
    Shield,
    ShieldCheck,
    Trash2,
    Info,
    ExternalLink,
    AlertCircle,
    Pin,
    PinOff,
    Sparkles,
    Filter,
    PanelLeftClose,
    PanelLeftOpen,
    ChevronLeft,
    ChevronRight,
    Edit3,
    Bell,
    Volume2,
    Crown,
    UserPlus,
    UserMinus,
    LogOut,
    Link as LinkIcon,
    Copy,
    Globe,
    Lock,
    Camera,
    CheckCircle2,
    XCircle,
    Settings,
    Smile,
    CornerUpLeft,
    ChevronUp,
    ChevronDown,
} from 'lucide-vue-next';
import { format, formatDistanceToNow, isToday, isYesterday, parseISO } from 'date-fns';
import { confirmDialog, showSuccess, showError, showToast } from '@/Utils/alert';
import AppleEmoji from '@/Components/AppleEmoji.vue';
import AppleChatInput from '@/Components/AppleChatInput.vue';
import { renderAppleEmojisHtml, isOnlyEmojis } from '@/Utils/emoji';

// YB - 08-09-2026 - Async on-demand loading of heavy EmojiPicker bundle
const EmojiPicker = defineAsyncComponent(() => import('@/Components/EmojiPicker.vue'));

const props = defineProps({
    conversations: {
        type: [Array, Object],
        default: () => [],
    },
    activeConversation: {
        type: Object,
        default: null,
    },
    messages: {
        type: [Array, Object],
        default: () => [],
    },
    chatRequests: {
        type: Array,
        default: () => [],
    },
    canCreateGroup: {
        type: Boolean,
        default: true,
    },
    auth: {
        type: Object,
        required: true,
    },
    pinnedConversations: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

// Normalization helper
const normalizeArray = (val) => {
    if (Array.isArray(val)) return val;
    if (val && Array.isArray(val.data)) return val.data;
    if (val && typeof val === 'object') return Object.values(val);
    return [];
};

// Reactive State
const localConversations = ref(normalizeArray(props.conversations));
const messageList = ref(normalizeArray(props.messages));

const messageInput = ref('');
const composerInputRef = ref(null);
const editInputRef = ref(null);
const selectedFile = ref(null);
const fileInput = ref(null);
const messagesContainer = ref(null);
const isSending = ref(false);
const searchQuery = ref('');
const isMobileChatOpen = ref(!!props.activeConversation);

// Message Edit & Delete State
// YB - 25-08-2026 - WhatsApp style message edit and delete dialog state
const editingMessage = ref(null);
const editInput = ref('');
const isEditing = ref(false);
const activeMessageMenuId = ref(null);
const deleteConfirmMessage = ref(null);
const isDeleteModalOpen = ref(false);

// Quoted Reply State - YB - 26-08-2026
const replyingToMessage = ref(null);
const highlightedMessageId = ref(null);

const startReplyToMessage = (msg) => {
    editingMessage.value = null;
    editInput.value = '';
    replyingToMessage.value = msg;
    activeMessageMenuId.value = null;
    scrollToBottom(true);
    nextTick(() => {
        composerInputRef.value?.focus();
    });
};

const cancelReply = () => {
    replyingToMessage.value = null;
};

const scrollToMessage = (msgId) => {
    if (!msgId) return;
    highlightedMessageId.value = Number(msgId);
    nextTick(() => {
        const el = document.getElementById(`message-${msgId}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    setTimeout(() => {
        if (highlightedMessageId.value === Number(msgId)) {
            highlightedMessageId.value = null;
        }
    }, 2500);
};

// Emoji Reactions State - YB - 26-08-2026
const quickEmojis = ['👍', '❤️', '😂', '🔥', '😢', '🙏', '🎉', '👏'];
const activeReactionPickerMessageId = ref(null);
const showReactionDetailsModal = ref(false);
const selectedReactionMessage = ref(null);
const isComposerEmojiPickerOpen = ref(false);

const handleComposerEmojiSelect = (emojiChar) => {
    if (editingMessage.value && editInputRef.value) {
        editInputRef.value.insertEmoji(emojiChar);
    } else if (composerInputRef.value) {
        composerInputRef.value.insertEmoji(emojiChar);
    } else {
        messageInput.value = (messageInput.value || '') + emojiChar;
    }
};

const handleReactionPickerSelect = async (messageId, emojiChar) => {
    activeReactionPickerMessageId.value = null;
    await toggleEmojiReaction(messageId, emojiChar);
};

const toggleEmojiReaction = async (messageId, emoji) => {
    activeReactionPickerMessageId.value = null;
    activeMessageMenuId.value = null;
    try {
        const res = await window.axios.post(route('chat.messages.reactions.toggle', messageId), { emoji });
        const targetMsg = messageList.value.find(m => Number(m.id) === Number(messageId));
        if (targetMsg) {
            targetMsg.reactions = res.data.reactions;
        }
    } catch (e) {
        console.error('Failed to toggle reaction', e);
        showError('Reaction Failed', 'Unable to add reaction.');
    }
};

const openReactionDetails = (msg) => {
    selectedReactionMessage.value = msg;
    showReactionDetailsModal.value = true;
};

// In-Chat Search State - YB - 26-08-2026
const isChatSearchOpen = ref(false);
const chatSearchQuery = ref('');
const currentSearchMatchIndex = ref(0);

const matchingMessageIds = computed(() => {
    if (!chatSearchQuery.value.trim()) return [];
    const q = chatSearchQuery.value.toLowerCase().trim();
    return messageList.value
        .filter(m => m.body && m.body.toLowerCase().includes(q) && !m.is_deleted_for_everyone)
        .map(m => m.id);
});

const goToNextSearchMatch = () => {
    if (matchingMessageIds.value.length === 0) return;
    currentSearchMatchIndex.value = (currentSearchMatchIndex.value + 1) % matchingMessageIds.value.length;
    scrollToMessage(matchingMessageIds.value[currentSearchMatchIndex.value]);
};

const goToPrevSearchMatch = () => {
    if (matchingMessageIds.value.length === 0) return;
    currentSearchMatchIndex.value = (currentSearchMatchIndex.value - 1 + matchingMessageIds.value.length) % matchingMessageIds.value.length;
    scrollToMessage(matchingMessageIds.value[currentSearchMatchIndex.value]);
};

const toggleChatSearch = () => {
    isChatSearchOpen.value = !isChatSearchOpen.value;
    if (!isChatSearchOpen.value) {
        chatSearchQuery.value = '';
        currentSearchMatchIndex.value = 0;
    }
};

// Pinned Messages State - YB - 26-08-2026
const pinnedMessages = computed(() => {
    return messageList.value.filter(m => m.is_pinned && !m.is_deleted_for_everyone);
});

const togglePinMessage = async (msg) => {
    activeMessageMenuId.value = null;
    try {
        const res = await window.axios.post(route('chat.messages.pin.toggle', msg.id));
        const updated = res.data.message;
        const targetMsg = messageList.value.find(m => Number(m.id) === Number(msg.id));
        if (targetMsg) {
            targetMsg.is_pinned = updated.is_pinned;
            targetMsg.pinned_at = updated.pinned_at;
        }
        showToast(updated.is_pinned ? 'Message pinned to chat' : 'Message unpinned', 'success');
    } catch (e) {
        console.error('Failed to toggle pin', e);
        showError('Action Failed', 'Could not update pinned message status.');
    }
};

// Group Message Info Modal (Who has seen & Who has received) - YB - 26-08-2026
const isMessageInfoModalOpen = ref(false);
const selectedMessageInfo = ref(null);
const loadingMessageInfo = ref(false);

const openMessageInfoModal = async (msg) => {
    activeMessageMenuId.value = null;
    isMessageInfoModalOpen.value = true;
    loadingMessageInfo.value = true;
    selectedMessageInfo.value = null;
    try {
        const res = await window.axios.get(route('chat.messages.info', msg.id));
        selectedMessageInfo.value = res.data;
    } catch (e) {
        console.error('Failed to load message info', e);
        showError('Message Info Error', 'Unable to fetch message delivery receipts.');
        isMessageInfoModalOpen.value = false;
    } finally {
        loadingMessageInfo.value = false;
    }
};

// Notification Sound (Web Audio API synthesis for zero external asset reliance)
const playNotificationSound = () => {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.12); // A5
        gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.25);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.25);
    } catch {
        // Silently ignore audio playback restrictions
    }
};

// Browser Desktop Push Notification - YB - 27-08-2026
const showDesktopNotification = (title, body, conversationId = null, icon = null) => {
    if ('Notification' in window) {
        const createNotification = () => {
            try {
                const notif = new Notification(title, {
                    body,
                    icon: icon || '/logos/favicon.ico',
                    tag: conversationId ? `talkspace-conv-${conversationId}` : undefined,
                    requireInteraction: false,
                });

                notif.onclick = (e) => {
                    e?.preventDefault();
                    try {
                        window.focus();
                        if (window.parent) window.parent.focus();
                    } catch (err) {}

                    notif.close();

                    if (conversationId) {
                        const targetConv = localConversations.value.find(c => Number(c.id) === Number(conversationId));
                        if (targetConv) {
                            selectConversation(targetConv);
                        } else {
                            router.visit(route('chat.show', conversationId));
                        }
                    }
                };
            } catch (e) {
                console.error('Desktop notification creation failed', e);
            }
        };

        if (Notification.permission === 'granted') {
            createNotification();
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    createNotification();
                }
            });
        }
    }
};

// Sidebar Collapse / Expand state
// YB - 25-08-2026 - Collapsible sidebar state persistent in localStorage
const isSidebarCollapsed = ref(JSON.parse(localStorage.getItem('talkspace_sidebar_collapsed') || 'false'));

const toggleSidebarCollapse = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem('talkspace_sidebar_collapsed', JSON.stringify(isSidebarCollapsed.value));
};

// Sidebar Navigation Tabs & Pinned Chats
// YB - 25-08-2026 - Pinned conversations and filter category tabs
const activeSidebarTab = ref('all'); // 'all' | 'unread' | 'groups' | 'direct'
const pinnedConversationIds = ref(new Set(JSON.parse(localStorage.getItem('talkspace_pinned_convs') || '[]')));

const togglePinConversation = (convId, e) => {
    if (e) e.stopPropagation();
    if (pinnedConversationIds.value.has(convId)) {
        pinnedConversationIds.value.delete(convId);
    } else {
        pinnedConversationIds.value.add(convId);
    }
    localStorage.setItem('talkspace_pinned_convs', JSON.stringify(Array.from(pinnedConversationIds.value)));
};

// Online Presence Tracking & Active Heartbeats
const onlineUserIds = ref(new Set());
const userHeartbeats = ref(new Map());
let heartbeatInterval = null;
let pruneHeartbeatInterval = null;

// Typing Indicators State
const typingUsers = ref(new Map());
let typingTimeout = null;

const sendHeartbeat = async () => {
    if (!currentUser.value) return;
    try {
        await window.axios.post(route('chat.heartbeat'));
    } catch {
        // Silently ignore ping network hiccup
    }
};

// New Conversation Modal State
const isModalOpen = ref(false);
const modalTab = ref('direct'); // 'direct' | 'group'
const userSearchQuery = ref('');
const availableUsers = ref([]);
const isSearchingUsers = ref(false);
const selectedGroupParticipants = ref([]);
const groupTitle = ref('');
const isCreatingConversation = ref(false);

// Header Menu & Profile Modal States
const isHeaderMenuOpen = ref(false);
const isProfileModalOpen = ref(false);
const selectedProfileUser = ref(null);

// Group Management State - YB - 26-08-2026
const isGroupInfoModalOpen = ref(false);
const isAddMembersModalOpen = ref(false);
const isEditGroupSettingsOpen = ref(false);
const groupSettingsForm = ref({ title: '', description: '', is_public: false });
const groupSettingsAvatar = ref(null);
const selectedNewMembers = ref([]);
const activeMemberActionMenuId = ref(null);
const copiedInviteLink = ref(false);
const groupAvatarInput = ref(null);
const groupInfoTab = ref('members'); // 'members' | 'requests'

// Real-Time WebRTC Call State
const isCallModalOpen = ref(false);
const isIncomingCallModalOpen = ref(false);
const activeCallType = ref('audio'); // 'audio' | 'video'
const callStatus = ref('idle'); // 'calling', 'ringing', 'connected', 'ended'
const incomingCallData = ref(null);
const callDurationSeconds = ref(0);
let callTimer = null;
let ringTimeout = null;

// Media Streams & Peer Connection
const localStream = ref(null);
const remoteStream = ref(null);
const localVideoRef = ref(null);
const remoteVideoRef = ref(null);
let peerConnection = null;

const rtcConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
    ],
};

const openUserProfile = (user = null) => {
    if (user) {
        selectedProfileUser.value = user.data || user;
    } else if (props.activeConversation) {
        selectedProfileUser.value = getConversationRecipient(props.activeConversation) || currentUser.value;
    } else {
        selectedProfileUser.value = currentUser.value;
    }
    isProfileModalOpen.value = true;
    isHeaderMenuOpen.value = false;
};

// Open WhatsApp-style Group Info Modal - YB - 26-08-2026
const openGroupInfo = () => {
    if (!props.activeConversation || props.activeConversation.type !== 'group') return;
    groupSettingsForm.value = {
        title: props.activeConversation.title || '',
        description: props.activeConversation.description || '',
        is_public: !!props.activeConversation.is_public,
    };
    groupSettingsAvatar.value = null;
    groupInfoTab.value = 'members';
    isGroupInfoModalOpen.value = true;
    isHeaderMenuOpen.value = false;
};

// Copy Group Invite Link to Clipboard - YB - 26-08-2026
const copyGroupInviteLink = () => {
    if (!props.activeConversation?.invite_url) return;
    navigator.clipboard.writeText(props.activeConversation.invite_url);
    copiedInviteLink.value = true;
    showToast('Invite link copied to clipboard!', 'success');
    setTimeout(() => {
        copiedInviteLink.value = false;
    }, 2000);
};

// Reset Group Invite Link - YB - 26-08-2026
const resetGroupInviteLink = async () => {
    if (!props.activeConversation) return;
    const confirmed = await confirmDialog({
        title: 'Reset Invite Link?',
        text: 'Existing invite links will be invalidated immediately. Anyone using the old link will no longer be able to join.',
        confirmButtonText: 'Yes, Reset Link',
        cancelButtonText: 'Cancel',
        isDanger: false,
    });
    if (!confirmed) return;

    try {
        const res = await window.axios.post(route('chat.groups.invite.reset', props.activeConversation.id));
        props.activeConversation.invite_code = res.data.invite_code;
        props.activeConversation.invite_url = res.data.invite_url;
        showToast('Invite link reset successfully!', 'success');
    } catch (e) {
        console.error('Failed to reset invite link', e);
        showError('Failed to reset invite link', e.response?.data?.message || 'Something went wrong.');
    }
};

// Open Add Members Modal - YB - 26-08-2026
const openAddMembersModal = () => {
    selectedNewMembers.value = [];
    isAddMembersModalOpen.value = true;
    searchUsers();
};

const toggleNewMemberSelection = (userId) => {
    const idx = selectedNewMembers.value.indexOf(userId);
    if (idx > -1) {
        selectedNewMembers.value.splice(idx, 1);
    } else {
        selectedNewMembers.value.push(userId);
    }
};

// Submit Adding Members to Group - YB - 26-08-2026
const submitAddMembers = async () => {
    if (!props.activeConversation || selectedNewMembers.value.length === 0) return;
    try {
        const res = await window.axios.post(route('chat.groups.members.add', props.activeConversation.id), {
            user_ids: selectedNewMembers.value,
        });
        props.activeConversation.participants = res.data.conversation.participants;
        isAddMembersModalOpen.value = false;
        selectedNewMembers.value = [];
        showToast('Members added successfully!', 'success');
    } catch (e) {
        console.error('Failed to add members', e);
        showError('Failed to add members', e.response?.data?.message || 'Something went wrong.');
    }
};

// Change Member Role (Make Admin / Dismiss Admin) - YB - 26-08-2026
const changeMemberRole = async (userId, newRole) => {
    if (!props.activeConversation) return;
    try {
        const res = await window.axios.patch(route('chat.groups.members.role', [props.activeConversation.id, userId]), {
            role: newRole,
        });
        props.activeConversation.participants = res.data.conversation.participants;
        activeMemberActionMenuId.value = null;
        showToast(`Member role updated to ${newRole}!`, 'success');
    } catch (e) {
        console.error('Failed to update member role', e);
        showError('Role Update Failed', e.response?.data?.message || 'Unable to update member role.');
    }
};

// Remove Member from Group - YB - 26-08-2026
const removeGroupMember = async (userId) => {
    if (!props.activeConversation) return;
    const confirmed = await confirmDialog({
        title: 'Remove Member?',
        text: 'Are you sure you want to remove this member from the group?',
        confirmButtonText: 'Yes, Remove',
        cancelButtonText: 'Cancel',
        isDanger: true,
    });
    if (!confirmed) return;

    try {
        const res = await window.axios.delete(route('chat.groups.members.remove', [props.activeConversation.id, userId]));
        props.activeConversation.participants = res.data.conversation.participants;
        activeMemberActionMenuId.value = null;
        showToast('Member removed from group', 'success');
    } catch (e) {
        console.error('Failed to remove member', e);
        showError('Failed to remove member', e.response?.data?.message || 'Something went wrong.');
    }
};

// Leave Current Group - YB - 26-08-2026
const leaveCurrentGroup = async () => {
    if (!props.activeConversation) return;
    const confirmed = await confirmDialog({
        title: 'Leave Group?',
        text: 'Are you sure you want to leave this group? You will no longer receive group messages.',
        confirmButtonText: 'Yes, Leave Group',
        cancelButtonText: 'Cancel',
        isDanger: true,
    });
    if (!confirmed) return;

    router.post(route('chat.groups.leave', props.activeConversation.id));
};

// Save Group Settings (Title, Bio, Public/Private, Avatar) - YB - 26-08-2026
const saveGroupSettings = async () => {
    if (!props.activeConversation) return;
    const formData = new FormData();
    if (groupSettingsForm.value.title) formData.append('title', groupSettingsForm.value.title);
    formData.append('description', groupSettingsForm.value.description || '');
    formData.append('is_public', groupSettingsForm.value.is_public ? '1' : '0');
    if (groupSettingsAvatar.value) {
        formData.append('avatar', groupSettingsAvatar.value);
    }

    try {
        const res = await window.axios.post(route('chat.groups.settings', props.activeConversation.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        props.activeConversation.title = res.data.conversation.title;
        props.activeConversation.description = res.data.conversation.description;
        props.activeConversation.avatar_url = res.data.conversation.avatar_url;
        props.activeConversation.is_public = res.data.conversation.is_public;
        
        const idx = localConversations.value.findIndex(c => Number(c.id) === Number(props.activeConversation.id));
        if (idx !== -1) {
            localConversations.value[idx].title = res.data.conversation.title;
            localConversations.value[idx].avatar_url = res.data.conversation.avatar_url;
        }

        isEditGroupSettingsOpen.value = false;
        groupSettingsAvatar.value = null;
        showToast('Group settings updated!', 'success');
    } catch (e) {
        console.error('Failed to save group settings', e);
        showError('Settings Save Failed', e.response?.data?.message || 'Could not save group settings.');
    }
};

const onGroupAvatarFileChanged = (e) => {
    const file = e.target.files?.[0];
    if (file) {
        groupSettingsAvatar.value = file;
        saveGroupSettings();
    }
};

// Approve & Reject Private Group Join Requests - YB - 26-08-2026
const handleApproveRequest = async (requestId) => {
    if (!props.activeConversation) return;
    try {
        const res = await window.axios.post(route('chat.groups.requests.approve', [props.activeConversation.id, requestId]));
        props.activeConversation.participants = res.data.conversation.participants;
        props.activeConversation.join_requests = res.data.conversation.join_requests;
        showToast('Join request approved!', 'success');
    } catch (e) {
        console.error('Failed to approve request', e);
        showError('Approval Failed', e.response?.data?.message || 'Something went wrong.');
    }
};

const handleRejectRequest = async (requestId) => {
    if (!props.activeConversation) return;
    const confirmed = await confirmDialog({
        title: 'Reject Request?',
        text: 'Are you sure you want to reject this join request?',
        confirmButtonText: 'Yes, Reject',
        cancelButtonText: 'Cancel',
        isDanger: true,
    });
    if (!confirmed) return;

    try {
        const res = await window.axios.post(route('chat.groups.requests.reject', [props.activeConversation.id, requestId]));
        props.activeConversation.join_requests = res.data.conversation.join_requests;
        showToast('Join request rejected', 'info');
    } catch (e) {
        console.error('Failed to reject request', e);
        showError('Failed to reject request', e.response?.data?.message || 'Something went wrong.');
    }
};

// Helper to compute tick state for WhatsApp group vs 1-1 - YB - 26-08-2026
const getMessageTickState = (msg) => {
    if (props.activeConversation?.type === 'group') {
        if (msg.read_by_all) return 'read';
        if (msg.delivered_to_all) return 'delivered';
        return 'sent';
    }

    if (msg.read_at) return 'read';
    if (msg.delivered_at) return 'delivered';
    return 'sent';
};

// Format call timer
const formattedCallDuration = computed(() => {
    const mins = Math.floor(callDurationSeconds.value / 60);
    const secs = callDurationSeconds.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const startCallTimer = () => {
    callDurationSeconds.value = 0;
    clearInterval(callTimer);
    callTimer = setInterval(() => {
        callDurationSeconds.value++;
    }, 1000);
};

const stopCallTimer = () => {
    clearInterval(callTimer);
    callTimer = null;
};

// Send WebRTC Signal to Peer via backend
const sendCallSignal = async (type, payload = null) => {
    const convId = props.activeConversation?.id || incomingCallData.value?.conversation_id;
    if (!convId) return;

    try {
        await window.axios.post(route('chat.call.signal', convId), {
            type,
            call_type: activeCallType.value,
            payload,
        });
    } catch (err) {
        console.error('Failed to send call signal:', err);
    }
};

// Start Outgoing Call
const initiateCall = async (type = 'audio') => {
    if (!props.activeConversation) return;

    activeCallType.value = type;
    callStatus.value = 'calling';
    isCallModalOpen.value = true;
    isHeaderMenuOpen.value = false;

    try {
        localStream.value = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: type === 'video',
        });

        if (type === 'video' && localVideoRef.value) {
            localVideoRef.value.srcObject = localStream.value;
        }

        peerConnection = new RTCPeerConnection(rtcConfig);

        localStream.value.getTracks().forEach((track) => {
            peerConnection.addTrack(track, localStream.value);
        });

        peerConnection.ontrack = (event) => {
            remoteStream.value = event.streams[0];
            if (remoteVideoRef.value) {
                remoteVideoRef.value.srcObject = remoteStream.value;
            }
        };

        peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                sendCallSignal('candidate', event.candidate);
            }
        };

        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);

        await sendCallSignal('offer', offer);

        // Auto timeout for missed call after 30 seconds if not answered
        ringTimeout = setTimeout(() => {
            if (callStatus.value === 'calling') {
                endCall('missed');
            }
        }, 30000);

    } catch (err) {
        console.error('Media access error:', err);
        callStatus.value = 'ended';
        setTimeout(() => { isCallModalOpen.value = false; }, 1500);
    }
};

// Accept Incoming Call
const acceptIncomingCall = async () => {
    clearTimeout(ringTimeout);
    isIncomingCallModalOpen.value = false;
    isCallModalOpen.value = true;
    callStatus.value = 'connected';
    startCallTimer();

    try {
        localStream.value = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: activeCallType.value === 'video',
        });

        if (activeCallType.value === 'video' && localVideoRef.value) {
            localVideoRef.value.srcObject = localStream.value;
        }

        peerConnection = new RTCPeerConnection(rtcConfig);

        localStream.value.getTracks().forEach((track) => {
            peerConnection.addTrack(track, localStream.value);
        });

        peerConnection.ontrack = (event) => {
            remoteStream.value = event.streams[0];
            if (remoteVideoRef.value) {
                remoteVideoRef.value.srcObject = remoteStream.value;
            }
        };

        peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                sendCallSignal('candidate', event.candidate);
            }
        };

        if (incomingCallData.value?.payload) {
            await peerConnection.setRemoteDescription(new RTCSessionDescription(incomingCallData.value.payload));
            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);
            await sendCallSignal('answer', answer);
        }
    } catch (err) {
        console.error('Failed to accept call:', err);
        endCall('failed');
    }
};

// Reject Incoming Call
const rejectIncomingCall = async () => {
    clearTimeout(ringTimeout);
    isIncomingCallModalOpen.value = false;
    await sendCallSignal('rejected');
};

// End / Terminate Call & Log outcome in chat stream
const endCall = async (reason = 'completed') => {
    clearTimeout(ringTimeout);
    stopCallTimer();

    const durationStr = callDurationSeconds.value > 0 ? formattedCallDuration.value : null;

    if (localStream.value) {
        localStream.value.getTracks().forEach(t => t.stop());
        localStream.value = null;
    }
    if (peerConnection) {
        peerConnection.close();
        peerConnection = null;
    }

    const convId = props.activeConversation?.id || incomingCallData.value?.conversation_id;

    if (callStatus.value === 'calling' && reason === 'missed') {
        // Log missed call
        if (convId) {
            await window.axios.post(route('chat.call.log', convId), {
                status: 'missed',
                call_type: activeCallType.value,
            });
        }
    } else if (durationStr) {
        // Log completed call with duration
        if (convId) {
            await window.axios.post(route('chat.call.log', convId), {
                status: 'completed',
                call_type: activeCallType.value,
                duration: durationStr,
            });
        }
    }

    sendCallSignal('hangup');

    callStatus.value = 'ended';
    setTimeout(() => {
        isCallModalOpen.value = false;
        callStatus.value = 'idle';
        incomingCallData.value = null;
    }, 1000);
};

// Filter and Sort conversations by tab, search, and pinned status
// YB - 25-08-2026 - Pinned priority sorting & Category Tab Filtering
const filteredConversations = computed(() => {
    let list = [...localConversations.value];

    // Filter by Tab
    if (activeSidebarTab.value === 'unread') {
        list = list.filter(c => c.unread_count > 0);
    } else if (activeSidebarTab.value === 'groups') {
        list = list.filter(c => c.type === 'group');
    } else if (activeSidebarTab.value === 'direct') {
        list = list.filter(c => c.type === 'direct');
    }

    // Filter by Search Query
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c => 
            c.title.toLowerCase().includes(q) ||
            (c.latest_message?.body && c.latest_message.body.toLowerCase().includes(q))
        );
    }

    // Sort: Pinned first, then latest activity
    return list.sort((a, b) => {
        const aPinned = pinnedConversationIds.value.has(a.id);
        const bPinned = pinnedConversationIds.value.has(b.id);
        if (aPinned && !bPinned) return -1;
        if (!aPinned && bPinned) return 1;
        return new Date(b.last_message_at || 0) - new Date(a.last_message_at || 0);
    });
});

// Group messages by calendar date (Filtering out messages deleted for current user)
const groupedMessages = computed(() => {
    const groups = [];
    let currentDate = null;
    let currentGroup = null;

    messageList.value.forEach((msg) => {
        if (!msg.created_at) return;
        // If message was deleted for me, hide from my view
        if (msg.deleted_for_user_ids && currentUser.value && msg.deleted_for_user_ids.includes(currentUser.value.id)) {
            return;
        }

        const msgDate = parseISO(msg.created_at);
        let dateLabel = format(msgDate, 'MMMM d, yyyy');
        if (isToday(msgDate)) dateLabel = 'Today';
        else if (isYesterday(msgDate)) dateLabel = 'Yesterday';

        if (currentDate !== dateLabel) {
            currentDate = dateLabel;
            currentGroup = { date: dateLabel, messages: [] };
            groups.push(currentGroup);
        }
        currentGroup.messages.push(msg);
    });

    return groups;
});

// YB - 26-08-2026 - Instantly mark a user as online in real time upon any socket activity
const markUserActive = (userId) => {
    if (!userId) return;
    const numId = Number(userId);
    onlineUserIds.value = new Set([...onlineUserIds.value, numId]);
    userHeartbeats.value.set(numId, Date.now());
};

// YB - 26-08-2026 - Custom Last Seen formatter matching exact timeframe criteria:
// - Within 24h (< 1440m): last seen Xh Ym ago (or Xm ago / just now)
// - Yesterday: last seen yesterday at h:mm a
// - Within 1 week: last seen X days ago
// - Over 1 week: last seen on dd/MM/yyyy
const formatLastActive = (isoString, fallback = 'last seen recently') => {
    if (!isoString) {
        if (fallback) {
            return fallback.startsWith('last seen') ? fallback : `last seen ${fallback}`;
        }
        return 'last seen recently';
    }

    try {
        const date = parseISO(isoString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMinutes = Math.floor(diffMs / (1000 * 60));

        // 1. Within 24 hours (< 1440 mins)
        if (diffMinutes < 1440) {
            if (diffMinutes < 1) {
                return 'last seen just now';
            }
            const hours = Math.floor(diffMinutes / 60);
            const mins = diffMinutes % 60;
            if (hours > 0 && mins > 0) {
                return `last seen ${hours}h ${mins}m ago`;
            } else if (hours > 0) {
                return `last seen ${hours}h ago`;
            }
            return `last seen ${mins}m ago`;
        }

        // 2. Yesterday (24h to 48h ago or calendar yesterday)
        if (isYesterday(date) || diffMinutes < 2880) {
            return `last seen yesterday at ${format(date, 'h:mm a')}`;
        }

        // 3. Within 1 week (2 to 7 days ago)
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        if (diffDays <= 7) {
            const dayCount = Math.max(1, diffDays);
            const label = dayCount === 1 ? '1 day' : `${dayCount} days`;
            return `last seen ${label} ago`;
        }

        // 4. Over 1 week
        return `last seen on ${format(date, 'dd/MM/yyyy')}`;
    } catch {
        return fallback?.startsWith('last seen') ? fallback : `last seen ${fallback || 'recently'}`;
    }
};

// YB - 25-08-2026 - Check if user is online with type-safe Number normalization & heartbeat check
const isUserOnline = (userId) => {
    if (!userId) return false;
    const numId = Number(userId);
    if (onlineUserIds.value.has(numId)) return true;
    // Also check active heartbeat within last 90 seconds - YB - 26-08-2026
    const lastPing = userHeartbeats.value.get(numId);
    if (lastPing && (Date.now() - lastPing < 90000)) {
        return true;
    }
    return false;
};

// YB - 26-08-2026 - Helper to safely extract direct recipient whether wrapped in .data or flat
const getConversationRecipient = (conv) => {
    if (!conv) return null;
    const recipient = conv.direct_recipient?.data || conv.direct_recipient;
    if (recipient?.id) return recipient;

    const participants = normalizeArray(conv.participants);
    if (participants.length > 0 && currentUser.value) {
        return participants.find(p => Number(p.id) !== Number(currentUser.value.id)) || null;
    }
    return null;
};

// YB - 26-08-2026 - Check if direct conversation partner is online with safe unwrapping
const isConversationOnline = (conv) => {
    if (!conv || conv.type !== 'direct') return false;
    
    const recipient = getConversationRecipient(conv);
    if (recipient?.id) {
        return isUserOnline(recipient.id);
    }

    return false;
};

// Format Timestamp
const formatMessageTime = (isoString) => {
    if (!isoString) return '';
    try {
        const date = parseISO(isoString);
        return format(date, 'h:mm a');
    } catch {
        return '';
    }
};

const formatConversationTime = (isoString) => {
    if (!isoString) return '';
    try {
        const date = parseISO(isoString);
        if (isToday(date)) return format(date, 'h:mm a');
        if (isYesterday(date)) return 'Yesterday';
        return format(date, 'MMM d');
    } catch {
        return '';
    }
};

// YB - 08-09-2026 - Format sidebar latest message preview safely
const getLatestMessagePreview = (conv) => {
    const msg = conv?.latest_message;
    if (!msg) return 'No messages yet';

    if (msg.is_deleted_for_everyone) {
        return '🚫 This message was deleted';
    }
    if (msg.body && msg.body.trim()) {
        return msg.body;
    }
    if (msg.attachment_name || msg.attachment_url || msg.attachment_path) {
        return msg.attachment_name ? `📎 ${msg.attachment_name}` : '📎 Attachment';
    }
    return 'No messages yet';
};

// Scroll to Bottom
const scrollToBottom = async (smooth = false) => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTo({
            top: messagesContainer.value.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto',
        });
    }
};

// Select Conversation
const selectConversation = (conv) => {
    isMobileChatOpen.value = true;
    if (conv) {
        conv.unread_count = 0;
    }
    router.visit(route('chat.show', conv.id), {
        preserveState: true,
        preserveScroll: true,
    });
};

// Handle File Selection
const onFileSelected = (event) => {
    const file = event.target.files?.[0];
    if (file) {
        selectedFile.value = file;
    }
};

const clearSelectedFile = () => {
    selectedFile.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

// Typing Event Trigger with Debounce
const handleTypingInput = () => {
    if (!props.activeConversation) return;

    if (!typingTimeout) {
        window.axios.post(route('chat.typing', props.activeConversation.id), { is_typing: true });
    }

    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        if (props.activeConversation) {
            window.axios.post(route('chat.typing', props.activeConversation.id), { is_typing: false });
        }
        typingTimeout = null;
    }, 2500);
};

// Send Message
const sendMessage = async () => {
    if ((!messageInput.value.trim() && !selectedFile.value) || isSending.value || !props.activeConversation) {
        return;
    }

    isSending.value = true;
    const bodyContent = messageInput.value.trim();
    const fileToSend = selectedFile.value;

    const formData = new FormData();
    if (bodyContent) formData.append('body', bodyContent);
    if (fileToSend) formData.append('attachment', fileToSend);
    if (replyingToMessage.value) formData.append('reply_to_id', replyingToMessage.value.id);

    // Reset input immediately for snappy UX
    messageInput.value = '';
    clearSelectedFile();
    cancelReply();

    try {
        const response = await window.axios.post(
            route('chat.messages.store', props.activeConversation.id),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );

        const newMsg = response.data.message;
        
        // Append locally if not already in list
        if (!messageList.value.some(m => m.id === newMsg.id)) {
            messageList.value.push(newMsg);
        }

        // Update conversation latest message in sidebar
        updateConversationLatestMessage(props.activeConversation.id, newMsg);
        scrollToBottom(true);
    } catch (error) {
        console.error('Failed to send message:', error);
    } finally {
        isSending.value = false;
    }
};

// Edit Message Action
// YB - 25-08-2026 - Message editing
const startEditMessage = (msg) => {
    replyingToMessage.value = null;
    editingMessage.value = msg;
    editInput.value = msg.body;
    activeMessageMenuId.value = null;
    nextTick(() => {
        editInputRef.value?.focus();
    });
};

const cancelEditMessage = () => {
    editingMessage.value = null;
    editInput.value = '';
};

const saveEditMessage = async () => {
    if (!editingMessage.value || !editInput.value.trim()) return;
    const msgId = editingMessage.value.id;
    isEditing.value = true;

    try {
        const response = await window.axios.patch(route('chat.messages.update', msgId), {
            body: editInput.value.trim(),
        });

        const updated = response.data.message;
        const idx = messageList.value.findIndex(m => m.id === msgId);
        if (idx !== -1) {
            messageList.value[idx] = updated;
        }

        cancelEditMessage();
    } catch (err) {
        console.error('Failed to edit message:', err);
    } finally {
        isEditing.value = false;
    }
};

// Delete Message Action (WhatsApp style: For Me vs For Everyone)
// YB - 25-08-2026 - Message delete
const openDeleteModal = (msg) => {
    deleteConfirmMessage.value = msg;
    isDeleteModalOpen.value = true;
    activeMessageMenuId.value = null;
};

const closeDeleteModal = () => {
    deleteConfirmMessage.value = null;
    isDeleteModalOpen.value = false;
};

// YB - 08-09-2026 - Execute message deletion with sidebar preview fallback for deleted-for-me
const executeDeleteMessage = async (type = 'me') => {
    if (!deleteConfirmMessage.value) return;
    const msgId = deleteConfirmMessage.value.id;

    try {
        const response = await window.axios.delete(route('chat.messages.delete', msgId), {
            data: { type },
        });

        const updated = response.data.message;

        if (type === 'everyone') {
            const idx = messageList.value.findIndex(m => m.id === msgId);
            if (idx !== -1) {
                messageList.value[idx] = updated;
            }
            if (props.activeConversation) {
                updateConversationLatestMessage(props.activeConversation.id, updated);
            }
        } else {
            // Delete for me: remove from local state immediately
            messageList.value = messageList.value.filter(m => m.id !== msgId);

            // Find the latest remaining message in this conversation not deleted for me
            if (props.activeConversation) {
                const remaining = messageList.value.filter(m => {
                    if (m.is_deleted_for_everyone) return true;
                    const df = m.deleted_for_user_ids || [];
                    return !df.includes(currentUser.value?.id);
                });
                const prevMsg = remaining.length > 0 ? remaining[remaining.length - 1] : null;
                updateConversationLatestMessage(props.activeConversation.id, prevMsg);
            }
        }

        closeDeleteModal();
    } catch (err) {
        console.error('Failed to delete message:', err);
    }
};

// Update conversation in sidebar
// YB - 08-09-2026 code comment
const updateConversationLatestMessage = (convId, msg) => {
    const idx = localConversations.value.findIndex(c => Number(c.id) === Number(convId));
    if (idx !== -1) {
        const conv = { ...localConversations.value[idx] };
        conv.latest_message = msg;
        if (msg?.created_at) {
            conv.last_message_at = msg.created_at;
            localConversations.value.splice(idx, 1);
            localConversations.value.unshift(conv);
        } else {
            localConversations.value[idx] = conv;
        }
    }
};

// User Search for New Chat Modal
let userSearchDebounce = null;
const searchUsers = () => {
    clearTimeout(userSearchDebounce);
    userSearchDebounce = setTimeout(async () => {
        isSearchingUsers.value = true;
        try {
            const response = await window.axios.get(route('chat.users.search'), {
                params: { query: userSearchQuery.value },
            });
            availableUsers.value = response.data.users;
        } catch (error) {
            console.error('Error fetching users:', error);
        } finally {
            isSearchingUsers.value = false;
        }
    }, 300);
};

const openNewChatModal = () => {
    isModalOpen.value = true;
    userSearchQuery.value = '';
    selectedGroupParticipants.value = [];
    groupTitle.value = '';
    searchUsers();
};

const startDirectChat = (user) => {
    isCreatingConversation.value = true;
    router.post(route('chat.store'), {
        type: 'direct',
        recipient_id: user.id,
    }, {
        onSuccess: () => {
            isModalOpen.value = false;
            isCreatingConversation.value = false;
        },
        onError: () => {
            isCreatingConversation.value = false;
        }
    });
};

const createGroupChat = () => {
    if (!groupTitle.value.trim() || selectedGroupParticipants.value.length === 0) return;

    isCreatingConversation.value = true;
    router.post(route('chat.store'), {
        type: 'group',
        title: groupTitle.value.trim(),
        participant_ids: selectedGroupParticipants.value,
    }, {
        onSuccess: () => {
            isModalOpen.value = false;
            isCreatingConversation.value = false;
        },
        onError: () => {
            isCreatingConversation.value = false;
        }
    });
};

const toggleGroupParticipant = (userId) => {
    const idx = selectedGroupParticipants.value.indexOf(userId);
    if (idx > -1) {
        selectedGroupParticipants.value.splice(idx, 1);
    } else {
        selectedGroupParticipants.value.push(userId);
    }
};

// Echo Setup & Channel Subscriptions
const setupEchoListeners = () => {
    if (!window.Echo || !currentUser.value) return;

    // 1. Presence Channel: Track online users
    window.Echo.join('online')
        .here((users) => {
            const ids = new Set();
            users.forEach((u) => {
                const id = u.id ?? u.user_id ?? u.user_info?.id;
                if (id) ids.add(Number(id));
            });
            onlineUserIds.value = ids;
        })
        .joining((user) => {
            const id = user.id ?? user.user_id ?? user.user_info?.id;
            if (id) {
                onlineUserIds.value = new Set([...onlineUserIds.value, Number(id)]);
            }
        })
        .leaving((user) => {
            const id = user.id ?? user.user_id ?? user.user_info?.id;
            if (id) {
                const updated = new Set(onlineUserIds.value);
                updated.delete(Number(id));
                onlineUserIds.value = updated;
            }
        });

    // 2. Heartbeat Channel: Guaranteed real-time active pings across all users - YB - 27-08-2026 Private authenticated channel
    window.Echo.private('online-heartbeats')
        .listen('.user.heartbeat', (event) => {
            if (event.user_id && Number(event.user_id) !== Number(currentUser.value?.id)) {
                markUserActive(event.user_id);
            }
        });

    // 3. Personal Private Channel: Notifications & inbox updates
    window.Echo.private(`user.${currentUser.value.id}`)
        .listen('.message.sent', (event) => {
            const incomingMsg = event.message;
            if (incomingMsg.sender_id) {
                markUserActive(incomingMsg.sender_id);
            }

            // Automatically acknowledge delivery back to sender so sender gets Double Tick immediately
            if (incomingMsg.conversation_id) {
                window.axios.post(route('chat.delivered', incomingMsg.conversation_id)).catch(() => {});
            }

            if (!props.activeConversation || props.activeConversation.id !== incomingMsg.conversation_id) {
                const conv = localConversations.value.find(c => Number(c.id) === Number(incomingMsg.conversation_id));
                const isGroup = conv?.type === 'group' || incomingMsg.conversation?.type === 'group';
                const groupTitle = conv?.title || incomingMsg.conversation?.title || 'Group';
                const senderName = incomingMsg.sender?.name || 'TalkSpace';

                // Suppress audio chime and desktop notification for system / event messages (grey pills) - YB - 27-08-2026
                if (incomingMsg.type !== 'system' && incomingMsg.sender_id !== currentUser.value?.id) {
                    playNotificationSound();

                    const notifTitle = isGroup ? `${senderName} (${groupTitle})` : senderName;
                    const notifBody = incomingMsg.body || (incomingMsg.attachment_name ? '📎 ' + incomingMsg.attachment_name : 'Sent you a message');
                    const notifAvatar = conv?.avatar_url || incomingMsg.sender?.avatar_url;

                    showDesktopNotification(notifTitle, notifBody, incomingMsg.conversation_id, notifAvatar);
                }

                // Update unread count and latest message in sidebar
                if (conv) {
                    if (incomingMsg.type !== 'system') {
                        conv.unread_count = (conv.unread_count || 0) + 1;
                    }
                    updateConversationLatestMessage(incomingMsg.conversation_id, incomingMsg);
                } else {
                    router.reload({ only: ['conversations'] });
                }
            }
        });

    // 4. Active Conversation Channel
    subscribeToActiveChat();
};

const subscribeToActiveChat = () => {
    if (!window.Echo || !props.activeConversation) return;

    window.Echo.private(`chat.${props.activeConversation.id}`)
        .listen('.message.sent', (event) => {
            const incomingMsg = event.message;
            if (incomingMsg.sender_id) {
                markUserActive(incomingMsg.sender_id);
            }
            if (!messageList.value.some(m => m.id === incomingMsg.id)) {
                messageList.value.push(incomingMsg);
                scrollToBottom(true);

                // Suppress notifications for system messages & own messages - YB - 27-08-2026
                if (incomingMsg.sender_id !== currentUser.value?.id && incomingMsg.type !== 'system') {
                    playNotificationSound();
                    if (typeof document !== 'undefined' && !document.hasFocus()) {
                        const isGroup = props.activeConversation?.type === 'group';
                        const groupTitle = props.activeConversation?.title || 'Group';
                        const senderName = incomingMsg.sender?.name || 'TalkSpace';
                        const notifTitle = isGroup ? `${senderName} (${groupTitle})` : senderName;
                        const notifBody = incomingMsg.body || (incomingMsg.attachment_name ? '📎 ' + incomingMsg.attachment_name : 'New message');
                        const notifAvatar = props.activeConversation?.avatar_url || incomingMsg.sender?.avatar_url;

                        showDesktopNotification(
                            notifTitle,
                            notifBody,
                            props.activeConversation?.id,
                            notifAvatar
                        );
                    }
                }
            }
            updateConversationLatestMessage(props.activeConversation.id, incomingMsg);
            
            // Mark as read immediately since user is actively in the chat
            window.axios.post(route('chat.read', props.activeConversation.id));
        })
        .listen('.message.delivered', (event) => {
            if (event.user_id) markUserActive(event.user_id);
            if (Number(event.conversation_id) === Number(props.activeConversation?.id) && event.user_id !== currentUser.value?.id) {
                const eventDeliveredAt = event.delivered_at ? new Date(event.delivered_at).getTime() : Date.now();
                messageList.value.forEach(m => {
                    if (m.sender_id === currentUser.value?.id && !m.delivered_at) {
                        const msgCreated = m.created_at ? new Date(m.created_at).getTime() : 0;
                        if (msgCreated <= eventDeliveredAt) {
                            m.delivered_at = event.delivered_at;
                        }
                    }
                });
            }
        })
        .listen('.message.updated', (event) => {
            const updatedMsg = event.message;
            if (updatedMsg.sender_id) markUserActive(updatedMsg.sender_id);
            const idx = messageList.value.findIndex(m => m.id === updatedMsg.id);
            if (idx !== -1) {
                messageList.value[idx] = updatedMsg;
            }
        })
        .listen('.message.reaction.toggled', (event) => {
            if (event.user_id) markUserActive(event.user_id);
            const msg = messageList.value.find(m => Number(m.id) === Number(event.message_id));
            if (msg) {
                msg.reactions = event.reactions;
            }
        })
        .listen('.message.pinned.toggled', (event) => {
            if (event.user_id) markUserActive(event.user_id);
            const msg = messageList.value.find(m => Number(m.id) === Number(event.message_id));
            if (msg) {
                msg.is_pinned = event.is_pinned;
                msg.pinned_at = event.message?.pinned_at;
            }
        })
        .listen('.user.typing', (event) => {
            if (event.user_id) markUserActive(event.user_id);
            if (event.user_id !== currentUser.value.id) {
                if (event.is_typing) {
                    typingUsers.value.set(event.user_id, event.user_name);
                } else {
                    typingUsers.value.delete(event.user_id);
                }
            }
        })
        .listen('.message.read', (event) => {
            if (event.user_id) markUserActive(event.user_id);
            if (Number(event.conversation_id) === Number(props.activeConversation?.id) && event.user_id !== currentUser.value?.id) {
                const eventReadAt = event.read_at ? new Date(event.read_at).getTime() : Date.now();
                messageList.value.forEach(m => {
                    if (m.sender_id === currentUser.value?.id) {
                        const msgCreated = m.created_at ? new Date(m.created_at).getTime() : 0;
                        if (msgCreated <= eventReadAt) {
                            if (!m.read_at) m.read_at = event.read_at;
                            if (!m.delivered_at) m.delivered_at = event.read_at;
                        }
                    }
                });
            }
        })
        .listen('.group.updated', (event) => {
            if (props.activeConversation && Number(props.activeConversation.id) === Number(event.conversation?.id)) {
                props.activeConversation.title = event.conversation.title;
                props.activeConversation.description = event.conversation.description;
                props.activeConversation.avatar_url = event.conversation.avatar_url;
                props.activeConversation.is_public = event.conversation.is_public;
            }
            const idx = localConversations.value.findIndex(c => Number(c.id) === Number(event.conversation?.id));
            if (idx !== -1) {
                localConversations.value[idx].title = event.conversation.title;
                localConversations.value[idx].avatar_url = event.conversation.avatar_url;
            }
        })
        .listen('.group.member.joined', (event) => {
            if (props.activeConversation && Number(props.activeConversation.id) === Number(event.conversation_id)) {
                if (event.user && !props.activeConversation.participants?.some(p => Number(p.id) === Number(event.user.id))) {
                    props.activeConversation.participants = [...(props.activeConversation.participants || []), { ...event.user, role: 'member' }];
                }
            }
            router.reload({ only: ['activeConversation', 'conversations'] });
        })
        .listen('.group.member.removed', (event) => {
            if (Number(event.removed_user_id) === Number(currentUser.value?.id)) {
                router.visit(route('chat.index'));
            } else if (props.activeConversation && Number(props.activeConversation.id) === Number(event.conversation_id)) {
                props.activeConversation.participants = props.activeConversation.participants?.filter(p => Number(p.id) !== Number(event.removed_user_id));
            }
        })
        .listen('.group.role.updated', (event) => {
            if (props.activeConversation && Number(props.activeConversation.id) === Number(event.conversation_id)) {
                const member = props.activeConversation.participants?.find(p => Number(p.id) === Number(event.user_id));
                if (member) member.role = event.role;
                if (Number(event.user_id) === Number(currentUser.value?.id)) {
                    props.activeConversation.user_role = event.role;
                    props.activeConversation.is_admin = event.role === 'admin';
                }
            }
        })
        .listen('.call.signaled', async (event) => {
            if (event.caller?.id) markUserActive(event.caller.id);
            if (event.caller.id === currentUser.value.id) return;

            if (event.type === 'offer') {
                incomingCallData.value = event;
                activeCallType.value = event.call_type || 'audio';
                isIncomingCallModalOpen.value = true;
            } else if (event.type === 'answer' && peerConnection) {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(event.payload));
                callStatus.value = 'connected';
                startCallTimer();
            } else if (event.type === 'candidate' && peerConnection) {
                try {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(event.payload));
                } catch (e) {
                    console.error('Error adding ice candidate:', e);
                }
            } else if (event.type === 'hangup' || event.type === 'rejected') {
                stopCallTimer();
                if (localStream.value) {
                    localStream.value.getTracks().forEach(t => t.stop());
                    localStream.value = null;
                }
                if (peerConnection) {
                    peerConnection.close();
                    peerConnection = null;
                }
                callStatus.value = 'ended';
                setTimeout(() => {
                    isCallModalOpen.value = false;
                    isIncomingCallModalOpen.value = false;
                    callStatus.value = 'idle';
                }, 1000);
            }
        });
};

const unsubscribeFromActiveChat = (convId) => {
    if (window.Echo && convId) {
        window.Echo.leave(`chat.${convId}`);
    }
};

// Sync Props with State
watch(() => props.conversations, (newConvs) => {
    localConversations.value = normalizeArray(newConvs);
}, { deep: true });

// YB - 24-08-2026 - Only re-seed messageList from props when the entire
// conversation changes. Within an active conversation messages are appended
// via WebSocket, so we must not clobber local state on every Inertia refresh.
watch(() => props.messages, (newMsgs) => {
    messageList.value = normalizeArray(newMsgs);
    scrollToBottom();
}, { deep: false });

// YB - 24-08-2026 - Guard: only re-subscribe when conversation ID changes.
// Inertia re-creates prop objects on every partial reload (new reference),
// which previously caused the watcher to destroy/recreate the WebSocket
// channel on every incoming message.
watch(() => props.activeConversation, (newConv, oldConv) => {
    const prevId = oldConv?.id ?? null;
    const nextId = newConv?.id ?? null;

    if (prevId !== nextId) {
        // Conversation actually switched — leave the old channel
        if (prevId) unsubscribeFromActiveChat(prevId);
        // Subscribe to the new one
        if (nextId) subscribeToActiveChat();
    }

    // Always update typing state and scroll on conversation change
    if (nextId) {
        typingUsers.value.clear();
        scrollToBottom();
    }
});

const closeAllPickers = () => {
    isComposerEmojiPickerOpen.value = false;
    activeReactionPickerMessageId.value = null;
};

onMounted(() => {
    setupEchoListeners();
    scrollToBottom();

    // Global click listener to close emoji popups on outside click - YB - 26-08-2026
    window.addEventListener('click', closeAllPickers);

    // Send initial heartbeat immediately & catch up pending deliveries
    sendHeartbeat();

    // Unread Catch-Up Notification when opening TalkSpace with missed messages - YB - 27-08-2026
    const totalUnread = localConversations.value.reduce((acc, c) => acc + (c.unread_count || 0), 0);
    if (totalUnread > 0) {
        const unreadChatsCount = localConversations.value.filter(c => c.unread_count > 0).length;
        setTimeout(() => {
            playNotificationSound();
            showToast(
                `You have ${totalUnread} unread ${totalUnread === 1 ? 'message' : 'messages'} in ${unreadChatsCount} ${unreadChatsCount === 1 ? 'chat' : 'chats'}`,
                'info'
            );
        }, 700);
    }

    // Send heartbeat every 60 seconds (1 minute) while user is browsing - YB - 26-08-2026
    heartbeatInterval = setInterval(sendHeartbeat, 60000);

    // Send fresh heartbeat immediately when user switches back to this tab
    window.addEventListener('focus', sendHeartbeat);

    // Prune users whose fallback heartbeat has expired (> 90 seconds) - YB - 26-08-2026
    pruneHeartbeatInterval = setInterval(() => {
        const now = Date.now();
        let changed = false;
        const currentSet = new Set(onlineUserIds.value);

        userHeartbeats.value.forEach((timestamp, uid) => {
            if (now - timestamp > 90000) {
                userHeartbeats.value.delete(uid);
                if (currentSet.has(uid)) {
                    currentSet.delete(uid);
                    changed = true;
                }
            }
        });

        if (changed) {
            onlineUserIds.value = currentSet;
        }
    }, 30000);
});

onUnmounted(() => {
    clearInterval(heartbeatInterval);
    clearInterval(pruneHeartbeatInterval);
    window.removeEventListener('focus', sendHeartbeat);
    window.removeEventListener('click', closeAllPickers);

    if (window.Echo) {
        window.Echo.leave('online');
        window.Echo.leave('online-heartbeats');
        if (currentUser.value) window.Echo.leave(`user.${currentUser.value.id}`);
        if (props.activeConversation) window.Echo.leave(`chat.${props.activeConversation.id}`);
    }
});
</script>

<template>
    <Head title="TalkSpace - Real-Time Chat" />

    <AuthenticatedLayout>
        <div class="h-[calc(100dvh-4rem)] md:h-[calc(100vh-4rem)] bg-[#F8FAFC] text-slate-800 flex overflow-hidden font-sans relative">
            
            <!-- Left Sidebar: Conversations Stream (Full-height & Collapsible) -->
            <div 
                :class="[
                    'relative flex-shrink-0 bg-white/95 backdrop-blur-xl border-r border-slate-200/80 flex flex-col transition-all duration-300 z-20 shadow-xs h-full',
                    isSidebarCollapsed ? 'w-20' : 'w-full md:w-96',
                    isMobileChatOpen ? 'hidden md:flex' : 'flex'
                ]"
            >
                <!-- Outside Floating Expand/Collapse Arrow Button on the Right Border -->
                <button 
                    @click="toggleSidebarCollapse"
                    class="hidden md:flex absolute -right-3.5 top-6 z-30 w-7 h-7 bg-white hover:bg-brand-50 text-brand-600 border border-brand-200 hover:border-brand-400 rounded-full shadow-md items-center justify-center transition-all hover:scale-110 active:scale-95 group"
                    :title="isSidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
                >
                    <ChevronRight v-if="isSidebarCollapsed" class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                    <ChevronLeft v-else class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                </button>

                <!-- Header with Brand & New Chat Button -->
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <!-- Expanded Header -->
                    <div class="flex items-center space-x-3 min-w-0" v-if="!isSidebarCollapsed">
                        <div class="relative group cursor-pointer flex-shrink-0" @click="openUserProfile(currentUser)">
                            <img 
                                :src="currentUser?.avatar_url || 'https://ui-avatars.com/api/?name=User&background=D91A8D&color=fff'" 
                                alt="Avatar" 
                                class="w-11 h-11 rounded-2xl ring-2 ring-brand-500/30 object-cover shadow-sm transition group-hover:scale-105"
                            />
                            <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white glow-emerald"></span>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-bold text-base text-slate-900 tracking-tight flex items-center gap-1.5">
                                TalkSpace
                                <span class="text-[10px] uppercase font-extrabold tracking-wider px-1.5 py-0.5 rounded-md bg-brand-50 text-brand-600 border border-brand-200">Live</span>
                            </h2>
                            <p class="text-xs text-slate-500 truncate">{{ currentUser?.name }}</p>
                        </div>
                    </div>

                    <!-- Action Button: Only Plus button shown (centered when collapsed) -->
                    <div :class="isSidebarCollapsed ? 'w-full flex justify-center' : 'flex items-center'">
                        <button 
                            @click="openNewChatModal"
                            class="p-2.5 bg-gradient-to-tr from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 text-white rounded-xl shadow-md shadow-brand-600/25 transition-all duration-200 flex items-center justify-center hover:scale-105 active:scale-95"
                            :title="isSidebarCollapsed ? 'New Chat' : 'Start New Conversation'"
                        >
                            <Plus class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Search Input with Glass Styling (Hidden when collapsed) -->
                <div v-if="!isSidebarCollapsed" class="px-4 pt-3 pb-2">
                    <div class="relative">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Search chats or messages..." 
                            class="w-full glass-input text-slate-800 placeholder-slate-400 text-xs rounded-xl pl-10 pr-4 py-2.5 border border-slate-200 focus:outline-none transition shadow-xs"
                        />
                    </div>
                </div>

                <!-- Modern Category Filter Pills (Hidden when collapsed) -->
                <div v-if="!isSidebarCollapsed" class="px-4 pb-3 flex items-center space-x-1.5 overflow-x-auto no-scrollbar border-b border-slate-100/80">
                    <button 
                        @click="activeSidebarTab = 'all'"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 flex-shrink-0',
                            activeSidebarTab === 'all' 
                                ? 'bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 text-white shadow-xs shadow-brand-600/20 scale-[1.02]' 
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-800'
                        ]"
                    >
                        <span>All</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full" :class="activeSidebarTab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'">
                            {{ localConversations.length }}
                        </span>
                    </button>

                    <button 
                        @click="activeSidebarTab = 'unread'"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 flex-shrink-0',
                            activeSidebarTab === 'unread' 
                                ? 'bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 text-white shadow-xs shadow-brand-600/20 scale-[1.02]' 
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-800'
                        ]"
                    >
                        <span>Unread</span>
                        <span 
                            v-if="localConversations.filter(c => c.unread_count > 0).length > 0"
                            class="text-[10px] px-1.5 py-0.2 rounded-full bg-brand-600 text-white font-extrabold animate-pulse"
                        >
                            {{ localConversations.filter(c => c.unread_count > 0).length }}
                        </span>
                    </button>

                    <button 
                        @click="activeSidebarTab = 'direct'"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 flex-shrink-0',
                            activeSidebarTab === 'direct' 
                                ? 'bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 text-white shadow-xs shadow-brand-600/20 scale-[1.02]' 
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-800'
                        ]"
                    >
                        <span>Direct</span>
                    </button>

                    <button 
                        @click="activeSidebarTab = 'groups'"
                        :class="[
                            'px-3 py-1.5 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 flex-shrink-0',
                            activeSidebarTab === 'groups' 
                                ? 'bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 text-white shadow-xs shadow-brand-600/20 scale-[1.02]' 
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-800'
                        ]"
                    >
                        <Users class="w-3 h-3" />
                        <span>Groups</span>
                    </button>
                </div>

                <!-- Conversation Items -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100/70 custom-scrollbar px-2 py-1 space-y-1">
                    <div 
                        v-if="filteredConversations.length === 0" 
                        class="p-8 text-center text-slate-500 space-y-3"
                    >
                        <div class="w-12 h-12 rounded-2xl bg-brand-50 border border-brand-200/60 flex items-center justify-center mx-auto text-brand-600 shadow-xs">
                            <MessageSquare class="w-6 h-6 stroke-[1.5]" />
                        </div>
                        <p class="text-sm font-bold text-slate-800">
                            {{ activeSidebarTab === 'unread' ? 'No unread messages' : 'No conversations found' }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ activeSidebarTab === 'unread' ? 'You are all caught up!' : 'Start a chat with a team member to begin collaborating.' }}
                        </p>
                        <button 
                            v-if="activeSidebarTab !== 'unread'"
                            @click="openNewChatModal"
                            class="px-4 py-2 bg-gradient-to-r from-brand-600 to-brand-800 text-white text-xs font-bold rounded-xl shadow-xs transition inline-flex items-center gap-1.5"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            New Message
                        </button>
                    </div>

                    <div 
                        v-for="conv in filteredConversations" 
                        :key="conv.id"
                        @click="selectConversation(conv)"
                        :class="[
                            'group relative rounded-2xl cursor-pointer flex items-center transition-all duration-200 border',
                            activeConversation?.id === conv.id 
                                ? 'bg-brand-50/90 border-brand-200/80 shadow-xs' 
                                : 'bg-transparent border-transparent hover:bg-slate-50/80',
                            isSidebarCollapsed ? 'p-2 justify-center' : 'p-3 space-x-3.5'
                        ]"
                    >
                        <!-- Avatar -->
                        <div class="relative flex-shrink-0">
                            <img 
                                :src="conv.avatar_url" 
                                alt="Avatar" 
                                class="w-12 h-12 rounded-2xl object-cover ring-2 ring-slate-100 group-hover:ring-brand-500/20 transition shadow-xs"
                            />
                            <!-- Online indicator (only for direct chat partner) -->
                            <span 
                                v-if="isConversationOnline(conv)"
                                class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white glow-emerald"
                            ></span>
                            <span 
                                v-else-if="conv.type === 'group' && conv.is_public"
                                class="absolute -bottom-0.5 -right-0.5 w-4 h-4 bg-slate-700 rounded-full border-2 border-white text-white flex items-center justify-center text-[9px]"
                            >
                                #
                            </span>
                        </div>

                        <!-- Conversation Details (Hidden when collapsed) -->
                        <div v-if="!isSidebarCollapsed" class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center space-x-1.5 min-w-0 pr-2">
                                    <!-- Pin Indicator -->
                                    <Pin v-if="pinnedConversationIds.has(conv.id)" class="w-3 h-3 text-brand-600 fill-brand-600 flex-shrink-0" />
                                    
                                    <h3 class="font-bold text-slate-800 text-sm truncate group-hover:text-brand-600 transition">
                                        {{ conv.title }}
                                    </h3>
                                </div>
                                <span class="text-[11px] text-slate-400 font-medium flex-shrink-0">
                                    {{ formatConversationTime(conv.last_message_at) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="text-xs text-slate-500 truncate pr-2 flex items-center gap-1">
                                    <span v-if="conv.latest_message && conv.latest_message.type !== 'system' && conv.latest_message.sender_id === currentUser?.id" class="text-brand-600 font-bold">You: </span>
                                    <span v-else-if="conv.latest_message && conv.latest_message.type !== 'system' && conv.type === 'group' && conv.latest_message.sender?.name" class="text-slate-700 font-semibold">{{ conv.latest_message.sender?.name?.split(' ')[0] }}: </span>
                                    <span class="truncate" v-html="renderAppleEmojisHtml(getLatestMessagePreview(conv))"></span>
                                </p>

                                <div class="flex items-center space-x-1.5 flex-shrink-0">
                                    <!-- Pin / Unpin Quick Toggle on Hover -->
                                    <button 
                                        @click="togglePinConversation(conv.id, $event)"
                                        class="opacity-0 group-hover:opacity-100 p-1 hover:bg-slate-200/80 rounded-lg text-slate-400 hover:text-brand-600 transition"
                                        :title="pinnedConversationIds.has(conv.id) ? 'Unpin chat' : 'Pin chat to top'"
                                    >
                                        <PinOff v-if="pinnedConversationIds.has(conv.id)" class="w-3.5 h-3.5" />
                                        <Pin v-else class="w-3.5 h-3.5" />
                                    </button>

                                    <!-- Unread Badge -->
                                    <span 
                                        v-if="conv.unread_count > 0 && activeConversation?.id !== conv.id"
                                        class="bg-gradient-to-r from-brand-600 to-brand-700 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full flex-shrink-0 shadow-xs animate-pulse"
                                    >
                                        {{ conv.unread_count }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div 
                :class="[
                    'flex-1 flex flex-col bg-[#F1F5F9] transition-all duration-300 h-full',
                    !isMobileChatOpen && !activeConversation ? 'hidden md:flex' : 'flex'
                ]"
            >
                <!-- Active Conversation Header with mobile-friendly height and spacing -->
                <div 
                    v-if="activeConversation"
                    class="h-16 md:h-20 py-2.5 px-3.5 md:px-8 bg-white/95 backdrop-blur-xl border-b border-slate-200/80 flex items-center justify-between flex-shrink-0 z-10 shadow-xs"
                >
                    <div 
                        class="flex items-center space-x-2.5 md:space-x-3.5 group min-w-0 flex-1 pr-2" 
                        :class="activeConversation.is_member !== false ? 'cursor-pointer' : ''"
                        @click="activeConversation.is_member !== false ? (activeConversation.type === 'group' ? openGroupInfo() : openUserProfile(activeConversation.direct_recipient || null)) : null"
                    >
                        <button 
                            @click.stop="isMobileChatOpen = false" 
                            class="md:hidden p-1.5 -ml-1 text-slate-500 hover:text-slate-900 rounded-xl hover:bg-slate-100 transition flex-shrink-0"
                            title="Back to chats"
                        >
                            <ArrowLeft class="w-5 h-5" />
                        </button>

                        <div class="relative flex-shrink-0">
                            <img 
                                :src="activeConversation.avatar_url" 
                                alt="Avatar" 
                                class="w-10 h-10 md:w-11 md:h-11 rounded-2xl object-cover ring-2 ring-brand-500/20 shadow-xs group-hover:ring-brand-500/50 transition"
                            />
                            <span 
                                v-if="activeConversation.is_member !== false && isConversationOnline(activeConversation)"
                                class="absolute -bottom-0.5 -right-0.5 w-3 h-3 md:w-3.5 md:h-3.5 bg-emerald-500 rounded-full border-2 border-white glow-emerald"
                            ></span>
                            <span 
                                v-else-if="activeConversation.is_member === false"
                                class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 md:w-4 md:h-4 bg-brand-600 rounded-full border-2 border-white text-white flex items-center justify-center text-[8px]"
                            >
                                <Lock class="w-2.5 h-2.5" />
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="font-bold text-slate-900 text-sm md:text-base flex items-center gap-1.5 group-hover:text-brand-600 transition truncate">
                                <span class="truncate">{{ activeConversation.title }}</span>
                                <span v-if="activeConversation.type === 'group' && activeConversation.is_member !== false" class="text-[10px] md:text-[11px] font-semibold bg-brand-50 text-brand-600 border border-brand-200 px-1.5 py-0.5 rounded-lg flex-shrink-0">
                                    {{ activeConversation.participants?.length || 0 }}
                                </span>
                                <span v-else-if="activeConversation.is_member === false" class="text-[9px] md:text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-lg flex-shrink-0">
                                    Private
                                </span>
                            </h2>

                            <!-- Dynamic Real-time Status / Typing indicator / Last Active -->
                            <div class="text-[11px] md:text-xs flex items-center gap-1.5 mt-0.5 truncate">
                                <span v-if="activeConversation.is_member === false" class="text-amber-600 font-semibold flex items-center gap-1 truncate">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping flex-shrink-0"></span>
                                    Request Pending
                                </span>
                                <span v-else-if="typingUsers.size > 0" class="text-brand-600 font-bold animate-pulse flex items-center gap-1.5 truncate">
                                    <span class="inline-block w-2 h-2 bg-brand-600 rounded-full animate-ping flex-shrink-0"></span>
                                    {{ Array.from(typingUsers.values()).join(', ') }} typing...
                                </span>
                                <span v-else-if="isConversationOnline(activeConversation)" class="text-emerald-600 font-semibold flex items-center gap-1.5 truncate">
                                    <span class="w-2 h-2 bg-emerald-500 rounded-full glow-emerald flex-shrink-0"></span>
                                    Active now
                                </span>
                                <span v-else class="text-slate-500 font-medium flex items-center gap-1.5 truncate">
                                    <span v-if="activeConversation.type === 'direct'">
                                        {{ formatLastActive(getConversationRecipient(activeConversation)?.last_active_at, getConversationRecipient(activeConversation)?.last_active_human) }}
                                    </span>
                                    <span v-else>
                                        {{ activeConversation.participants?.length || 0 }} members
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Quick Actions (Only for active members) -->
                    <div v-if="activeConversation.is_member !== false" class="relative flex items-center space-x-1 md:space-x-1.5 text-slate-500 flex-shrink-0">
                        <!-- In-Chat Search Button - YB - 26-08-2026 -->
                        <button 
                            @click="toggleChatSearch" 
                            title="Search in conversation"
                            class="p-2 md:p-2.5 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-all hover:scale-105 active:scale-95 border border-transparent hover:border-brand-200"
                            :class="isChatSearchOpen ? 'text-brand-600 bg-brand-50 border-brand-200' : ''"
                        >
                            <Search class="w-4 h-4" />
                        </button>
                        <button 
                            @click="initiateCall('audio')" 
                            title="Start Voice Call"
                            class="p-2 md:p-2.5 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-all hover:scale-105 active:scale-95 border border-transparent hover:border-brand-200"
                        >
                            <Phone class="w-4 h-4" />
                        </button>
                        <button 
                            @click="initiateCall('video')" 
                            title="Start Video Call"
                            class="p-2 md:p-2.5 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-all hover:scale-105 active:scale-95 border border-transparent hover:border-brand-200"
                        >
                            <Video class="w-4 h-4" />
                        </button>
                        <div class="h-5 w-px bg-slate-200 mx-0.5 md:mx-1"></div>
                        <button 
                            @click="isHeaderMenuOpen = !isHeaderMenuOpen" 
                            title="More options"
                            class="p-2 md:p-2.5 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-all hover:scale-105 active:scale-95 border border-transparent hover:border-brand-200"
                        >
                            <MoreVertical class="w-4 h-4" />
                        </button>

                        <!-- Header Options Dropdown -->
                        <div 
                            v-if="isHeaderMenuOpen" 
                            class="absolute right-0 top-12 w-52 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-50 animate-in fade-in zoom-in-95 duration-150"
                        >
                            <button 
                                v-if="activeConversation.type === 'group'"
                                @click="openGroupInfo(); isHeaderMenuOpen = false;"
                                class="w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:text-brand-600 hover:bg-brand-50 flex items-center gap-2.5 transition"
                            >
                                <Users class="w-4 h-4 text-brand-600" />
                                <span>Group Info</span>
                            </button>
                            <button 
                                v-else
                                @click="openUserProfile(activeConversation.direct_recipient || null); isHeaderMenuOpen = false;"
                                class="w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:text-brand-600 hover:bg-brand-50 flex items-center gap-2.5 transition"
                            >
                                <User class="w-4 h-4 text-brand-600" />
                                <span>View Profile</span>
                            </button>
                            <button 
                                @click="toggleChatSearch(); isHeaderMenuOpen = false;"
                                class="w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:text-brand-600 hover:bg-brand-50 flex items-center gap-2.5 transition"
                            >
                                <Search class="w-4 h-4 text-brand-600" />
                                <span>Search Messages</span>
                            </button>
                            <button 
                                @click="initiateCall('audio'); isHeaderMenuOpen = false;"
                                class="w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:text-emerald-600 hover:bg-emerald-50 flex items-center gap-2.5 transition"
                            >
                                <Phone class="w-4 h-4 text-emerald-600" />
                                <span>Voice Call</span>
                            </button>
                            <button 
                                @click="initiateCall('video'); isHeaderMenuOpen = false;"
                                class="w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:text-brand-600 hover:bg-brand-50 flex items-center gap-2.5 transition"
                            >
                                <Video class="w-4 h-4 text-brand-600" />
                                <span>Video Call</span>
                            </button>
                            <div class="h-px bg-slate-100 my-1"></div>
                            <button 
                                @click="scrollToBottom(true); isHeaderMenuOpen = false;"
                                class="w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 flex items-center gap-2.5 transition"
                            >
                                <MessageSquare class="w-4 h-4 text-slate-500" />
                                <span>Scroll to Latest</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- In-Chat Search Bar - YB - 26-08-2026 -->
                <div 
                    v-if="activeConversation && isChatSearchOpen && activeConversation.is_member !== false" 
                    class="bg-white/95 backdrop-blur-xl border-b border-slate-200/80 px-6 py-2.5 flex items-center justify-between gap-3 z-20 shadow-xs animate-in slide-in-from-top-2 duration-150"
                >
                    <div class="relative flex-1 max-w-md">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="chatSearchQuery"
                            type="text"
                            placeholder="Search in this conversation..."
                            class="w-full pl-9 pr-24 py-1.5 text-xs bg-slate-100 border border-slate-200 rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                            autofocus
                            @keydown.enter="goToNextSearchMatch"
                        />
                        <span v-if="chatSearchQuery.trim()" class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-500">
                            {{ matchingMessageIds.length > 0 ? `${currentSearchMatchIndex + 1} of ${matchingMessageIds.length}` : '0 results' }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <button 
                            @click="goToPrevSearchMatch" 
                            :disabled="matchingMessageIds.length === 0"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-brand-600 hover:bg-slate-100 disabled:opacity-30 transition"
                            title="Previous match"
                        >
                            <ChevronUp class="w-4 h-4" />
                        </button>
                        <button 
                            @click="goToNextSearchMatch" 
                            :disabled="matchingMessageIds.length === 0"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-brand-600 hover:bg-slate-100 disabled:opacity-30 transition"
                            title="Next match"
                        >
                            <ChevronDown class="w-4 h-4" />
                        </button>
                        <button 
                            @click="toggleChatSearch" 
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                            title="Close search"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Pinned Messages Banner Bar - YB - 26-08-2026 -->
                <div 
                    v-if="activeConversation && pinnedMessages.length > 0 && activeConversation.is_member !== false" 
                    class="bg-white/90 backdrop-blur-md border-b border-brand-100 px-6 py-2 flex items-center justify-between z-10 shadow-xs animate-in slide-in-from-top-1"
                >
                    <div 
                        class="flex items-center space-x-3 min-w-0 cursor-pointer flex-1 group" 
                        @click="scrollToMessage(pinnedMessages[0].id)"
                    >
                        <div class="p-1.5 rounded-xl bg-brand-50 text-brand-600 border border-brand-200 shadow-xs group-hover:scale-105 transition">
                            <Pin class="w-3.5 h-3.5 fill-brand-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <span>Pinned Message</span>
                                <span v-if="pinnedMessages.length > 1" class="text-[10px] text-brand-600 font-semibold bg-brand-50 px-1.5 py-0.2 rounded-md">
                                    +{{ pinnedMessages.length - 1 }} more
                                </span>
                            </p>
                            <p class="text-[11px] text-slate-500 truncate">
                                <span class="font-medium text-slate-700">{{ pinnedMessages[0].sender?.name }}:</span> {{ pinnedMessages[0].body || (pinnedMessages[0].attachment_name ? '📎 ' + pinnedMessages[0].attachment_name : '') }}
                            </p>
                        </div>
                    </div>
                    <button 
                        @click="togglePinMessage(pinnedMessages[0])" 
                        class="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition" 
                        title="Unpin message"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Empty State (No conversation selected) -->
                <div 
                    v-if="!activeConversation" 
                    class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500 space-y-4"
                >
                    <div class="w-24 h-24 rounded-3xl bg-gradient-to-tr from-brand-100 to-purple-100 border border-brand-200 flex items-center justify-center text-brand-600 shadow-md shadow-brand-600/10">
                        <MessageSquare class="w-12 h-12 stroke-[1.5]" />
                    </div>
                    <div class="max-w-md space-y-1.5">
                        <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Your TalkSpace Workspace</h3>
                        <p class="text-sm text-slate-500 leading-relaxed">Select an active conversation or start a new direct or group chat to begin real-time messaging with WebSockets.</p>
                    </div>
                    <button 
                        @click="openNewChatModal"
                        class="px-5 py-2.5 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 text-white rounded-xl text-sm font-bold shadow-md shadow-brand-600/25 transition-all hover:scale-105 active:scale-95 flex items-center gap-2"
                    >
                        <Plus class="w-4 h-4" />
                        Start New Conversation
                    </button>
                </div>

                <!-- Messages Stream with Date Dividers -->
                <div 
                    v-else 
                    ref="messagesContainer"
                    class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6 custom-scrollbar bg-[#F1F5F9]"
                >
                    <div 
                        v-if="messageList.length === 0" 
                        class="text-center py-16 text-slate-400 space-y-2"
                    >
                        <Sparkles class="w-8 h-8 text-brand-600 mx-auto stroke-[1.5] animate-bounce" />
                        <p class="text-sm font-bold text-slate-800">No messages in this chat room yet</p>
                        <p class="text-xs text-slate-500">Be the first to send a message! 🚀</p>
                    </div>

                    <!-- Date Group Loops -->
                    <div 
                        v-for="group in groupedMessages" 
                        :key="group.date"
                        class="space-y-4"
                    >
                        <!-- Date Badge Separator -->
                        <div class="flex items-center justify-center my-4">
                            <span class="px-3.5 py-1 bg-white/90 backdrop-blur-md border border-slate-200/80 rounded-full text-[11px] font-bold text-slate-600 tracking-wide shadow-xs">
                                {{ group.date }}
                            </span>
                        </div>

                        <!-- Message Bubbles & Centered System Badges -->
                        <template v-for="msg in group.messages" :key="msg.id">
                            <!-- Group System Info Badge (Centered like Today/Yesterday) - YB - 26-08-2026 -->
                            <div 
                                v-if="msg.type === 'system' && !msg.attachment_path" 
                                class="flex items-center justify-center my-2.5 w-full"
                            >
                                <span class="px-3.5 py-1.5 bg-slate-200/80 backdrop-blur-md text-slate-700 text-[11px] font-semibold rounded-xl shadow-xs text-center max-w-md border border-slate-300/60 select-none tracking-wide">
                                    {{ msg.body }}
                                </span>
                            </div>

                            <!-- Regular Chat Message Bubble / Call Activity Bubble -->
                            <div 
                                v-else
                                :id="'message-' + msg.id"
                                :class="[
                                    'flex items-end gap-2.5 max-w-xl group relative transition-all duration-300',
                                    msg.sender_id === currentUser?.id ? 'ml-auto flex-row-reverse' : 'mr-auto flex-row',
                                    highlightedMessageId === msg.id ? 'scale-[1.02]' : ''
                                ]"
                            >
                                <!-- Sender Avatar (for incoming messages) -->
                                <img 
                                    v-if="msg.sender_id !== currentUser?.id"
                                    :src="msg.sender?.avatar_url || 'https://ui-avatars.com/api/?name=User&background=D91A8D&color=fff'" 
                                    alt="Avatar" 
                                    class="w-8 h-8 rounded-xl object-cover flex-shrink-0 ring-1 ring-slate-200 shadow-xs mb-1"
                                />

                                <!-- Message Content Bubble Wrapper -->
                                <div class="space-y-1 max-w-lg relative">
                                    <!-- Sender name in group -->
                                    <p 
                                        v-if="activeConversation.type === 'group' && msg.sender_id !== currentUser?.id"
                                        class="text-[11px] font-bold text-brand-600 ml-1"
                                    >
                                        {{ msg.sender?.name }}
                                    </p>

                                    <!-- Bubble Body -->
                                    <div 
                                        :class="[
                                            'rounded-2xl px-4 py-2.5 text-sm break-words relative leading-relaxed transition-all shadow-xs',
                                            msg.sender_id === currentUser?.id 
                                                ? 'chat-bubble-outgoing text-white' 
                                                : 'chat-bubble-incoming text-slate-800',
                                            highlightedMessageId === msg.id ? 'ring-4 ring-amber-400/90 ring-offset-2' : ''
                                        ]"
                                    >
                                        <!-- Pinned Indicator Badge - YB - 26-08-2026 -->
                                        <div v-if="msg.is_pinned" class="flex items-center gap-1 text-[10px] font-extrabold pb-1.5 opacity-90 border-b border-white/20 mb-1.5" :class="msg.sender_id === currentUser?.id ? 'text-amber-200' : 'text-amber-600'">
                                            <Pin class="w-3 h-3 fill-current" />
                                            <span>Pinned</span>
                                        </div>

                                        <!-- Quoted Reply Parent Block - YB - 26-08-2026 -->
                                        <div 
                                            v-if="msg.reply_to" 
                                            @click="scrollToMessage(msg.reply_to.id)"
                                            class="mb-2 p-2.5 rounded-xl border-l-4 border-brand-500 cursor-pointer text-left transition transform hover:opacity-95"
                                            :class="msg.sender_id === currentUser?.id ? 'bg-black/20 text-white' : 'bg-slate-100 text-slate-800'"
                                        >
                                            <p class="text-[10px] font-extrabold flex items-center gap-1" :class="msg.sender_id === currentUser?.id ? 'text-brand-200' : 'text-brand-600'">
                                                <CornerUpLeft class="w-2.5 h-2.5" />
                                                {{ msg.reply_to.sender_name }}
                                            </p>
                                            <p class="text-[11px] truncate opacity-90 font-medium mt-0.5" v-html="renderAppleEmojisHtml(msg.reply_to.body || (msg.reply_to.attachment_name ? '📎 ' + msg.reply_to.attachment_name : ''))"></p>
                                        </div>

                                        <!-- Image Attachment -->
                                        <div v-if="msg.type === 'image' && msg.attachment_url" class="mb-2 max-w-sm rounded-xl overflow-hidden shadow-md">
                                            <a :href="msg.attachment_url" target="_blank" rel="noopener noreferrer">
                                                <img :src="msg.attachment_url" alt="Attachment" class="w-full h-auto max-h-72 object-cover rounded-xl hover:scale-[1.02] transition duration-200" />
                                            </a>
                                        </div>

                                        <!-- File Attachment -->
                                        <div 
                                            v-else-if="msg.type === 'file' && msg.attachment_url"
                                            class="mb-2 p-3 bg-black/25 rounded-xl flex items-center space-x-3 max-w-xs border border-white/10"
                                        >
                                            <FileText class="w-8 h-8 text-indigo-300 flex-shrink-0" />
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-semibold truncate">{{ msg.attachment_name }}</p>
                                                <p class="text-[10px] text-slate-300">{{ (msg.attachment_size / 1024).toFixed(1) }} KB</p>
                                            </div>
                                            <a 
                                                :href="msg.attachment_url" 
                                                download 
                                                class="text-xs px-2.5 py-1 bg-white/15 hover:bg-white/25 rounded-lg transition font-medium text-white shadow-xs"
                                            >
                                                Download
                                            </a>
                                        </div>

                                        <!-- WhatsApp-Style Call Activity Message -->
                                        <div 
                                            v-if="msg.type === 'system' && msg.attachment_path"
                                            class="flex items-center gap-3 p-1.5 min-w-[200px]"
                                        >
                                            <div 
                                                class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm"
                                                :class="msg.attachment_name === 'missed' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'"
                                            >
                                                <Video v-if="msg.attachment_path === 'video'" class="w-5 h-5" />
                                                <Phone v-else class="w-5 h-5" :class="msg.attachment_name === 'missed' ? 'rotate-[135deg]' : ''" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p 
                                                    class="text-xs font-bold truncate"
                                                    :class="msg.attachment_name === 'missed' ? 'text-rose-300' : 'text-slate-100'"
                                                >
                                                    {{ msg.body }}
                                                </p>
                                                <p class="text-[10px] text-slate-400 mt-0.5">
                                                    {{ msg.attachment_name === 'missed' ? 'Tap to call back' : (msg.created_at_human || 'Call ended') }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Text Body (with Apple iOS Emojis & Big Emoji Support) - YB - 26-08-2026 -->
                                        <div v-else-if="msg.body">
                                            <p 
                                                :class="[
                                                    'whitespace-pre-wrap text-[13.5px] select-text',
                                                    msg.is_deleted_for_everyone ? 'italic text-slate-400 opacity-90' : '',
                                                    isOnlyEmojis(msg.body) ? 'big-emoji-only leading-normal py-1' : 'leading-relaxed'
                                                ]"
                                                v-html="renderAppleEmojisHtml(msg.body)"
                                            ></p>
                                        </div>

                                        <!-- Message Footer: Edited tag, Timestamp & WhatsApp Read Receipts -->
                                        <div 
                                            :class="[
                                                'text-[10px] mt-1.5 flex items-center gap-1.5 justify-end font-medium select-none',
                                                msg.sender_id === currentUser?.id ? 'text-white/80' : 'text-slate-400'
                                            ]"
                                        >
                                            <!-- Edited Indicator -->
                                            <span v-if="msg.is_edited && !msg.is_deleted_for_everyone" class="opacity-75 italic text-[9.5px]">
                                                edited
                                            </span>

                                            <span>{{ formatMessageTime(msg.created_at) }}</span>

                                            <!-- WhatsApp Style Read Receipts for Outgoing messages -->
                                            <template v-if="msg.sender_id === currentUser?.id && !msg.is_deleted_for_everyone">
                                                <!-- Blue Tick (Read by all recipients) -->
                                                <span v-if="getMessageTickState(msg) === 'read'" class="text-sky-300 flex items-center" title="Read by everyone">
                                                    <CheckCheck class="w-3.5 h-3.5 stroke-[2.5]" />
                                                </span>
                                                <!-- Double Gray Tick (Delivered to all recipients) -->
                                                <span v-else-if="getMessageTickState(msg) === 'delivered'" class="opacity-80 flex items-center text-slate-200" title="Delivered to everyone">
                                                    <CheckCheck class="w-3.5 h-3.5 stroke-[2]" />
                                                </span>
                                                <!-- Single Gray Tick (Sent to server, awaiting delivery) -->
                                                <span v-else class="opacity-75 flex items-center text-slate-300" title="Sent">
                                                    <Check class="w-3.5 h-3.5 stroke-[2]" />
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Emoji Reactions List under Bubble - YB - 26-08-2026 -->
                                    <div 
                                        v-if="msg.reactions && msg.reactions.length > 0" 
                                        class="flex flex-wrap gap-1 mt-1 select-none"
                                        :class="msg.sender_id === currentUser?.id ? 'justify-end' : 'justify-start'"
                                    >
                                        <button 
                                            v-for="r in msg.reactions" 
                                            :key="r.emoji"
                                            @click="toggleEmojiReaction(msg.id, r.emoji)"
                                            @dblclick.stop="openReactionDetails(msg)"
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-xs font-semibold flex items-center gap-1.5 shadow-xs border transition transform hover:scale-105 active:scale-95',
                                                r.has_reacted 
                                                    ? 'bg-brand-50 text-brand-700 border-brand-300 ring-1 ring-brand-400/40' 
                                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                            ]"
                                            :title="r.users?.map(u => u.name).join(', ') || 'Reactions'"
                                        >
                                            <AppleEmoji :emoji="r.emoji" size="1.05rem" />
                                            <span class="text-[10px] font-bold">{{ r.count }}</span>
                                        </button>
                                    </div>

                                    <!-- Quick Reaction Hover Toolbar (WhatsApp Style with Apple Emojis) - YB - 26-08-2026 -->
                                    <div 
                                        v-if="!msg.is_deleted_for_everyone && activeConversation.is_member !== false"
                                        class="absolute -top-7 opacity-0 group-hover:opacity-100 transition-all duration-150 z-20 flex items-center bg-white/95 backdrop-blur-md rounded-full shadow-lg border border-slate-200 px-1.5 py-0.5 gap-0.5"
                                        :class="msg.sender_id === currentUser?.id ? 'right-0' : 'left-0'"
                                        @click.stop
                                    >
                                        <button 
                                            v-for="em in quickEmojis" 
                                            :key="em"
                                            @click="toggleEmojiReaction(msg.id, em)"
                                            class="hover:scale-125 transition p-1 active:scale-95 flex items-center justify-center"
                                            :title="'React ' + em"
                                        >
                                            <AppleEmoji :emoji="em" size="1.25rem" />
                                        </button>

                                        <!-- Plus Button: Open Full Apple iOS Emoji Picker -->
                                        <button 
                                            @click.stop="activeReactionPickerMessageId = activeReactionPickerMessageId === msg.id ? null : msg.id"
                                            class="hover:scale-110 transition p-1 text-xs active:scale-95 text-slate-500 hover:text-brand-600 rounded-full hover:bg-slate-100 flex items-center justify-center w-5 h-5"
                                            title="More Apple Emojis"
                                        >
                                            <Plus class="w-3.5 h-3.5" />
                                        </button>

                                        <div class="w-px h-3.5 bg-slate-200 mx-0.5"></div>
                                        <!-- Reply Button -->
                                        <button 
                                            @click="startReplyToMessage(msg)"
                                            class="p-1 text-slate-500 hover:text-brand-600 hover:bg-slate-100 rounded-full transition"
                                            title="Reply to message"
                                        >
                                            <CornerUpLeft class="w-3.5 h-3.5" />
                                        </button>
                                        <!-- More Options Menu Button -->
                                        <button 
                                            @click.stop="activeMessageMenuId = activeMessageMenuId === msg.id ? null : msg.id"
                                            class="p-1 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-full transition"
                                            title="More options"
                                        >
                                            <MoreVertical class="w-3.5 h-3.5" />
                                        </button>
                                    </div>

                                    <!-- Full Apple iOS Emoji Picker Popover for Reactions - YB - 26-08-2026 -->
                                    <div 
                                        v-if="activeReactionPickerMessageId === msg.id"
                                        class="absolute bottom-full mb-3 z-50 animate-in fade-in zoom-in-95"
                                        :class="msg.sender_id === currentUser?.id ? 'right-0' : 'left-0'"
                                        @click.stop
                                    >
                                        <EmojiPicker 
                                            :is-open="true" 
                                            @select="(em) => handleReactionPickerSelect(msg.id, em)" 
                                            @close="activeReactionPickerMessageId = null" 
                                        />
                                    </div>

                                    <!-- Dropdown Context Menu - YB - 26-08-2026 -->
                                    <div 
                                        v-if="activeMessageMenuId === msg.id"
                                        class="absolute right-0 top-full mt-1 w-44 bg-white rounded-2xl shadow-xl border border-slate-200 py-1.5 z-30 animate-in fade-in zoom-in-95 text-slate-700"
                                        @click.stop
                                    >
                                        <!-- Reply -->
                                        <button 
                                            @click="startReplyToMessage(msg)"
                                            class="w-full px-3.5 py-2 text-xs text-left hover:bg-slate-50 flex items-center gap-2 font-medium"
                                        >
                                            <CornerUpLeft class="w-3.5 h-3.5 text-brand-600" />
                                            <span>Reply</span>
                                        </button>

                                        <!-- Pin / Unpin Message -->
                                        <button 
                                            @click="togglePinMessage(msg)"
                                            class="w-full px-3.5 py-2 text-xs text-left hover:bg-slate-50 flex items-center gap-2 font-medium"
                                        >
                                            <PinOff v-if="msg.is_pinned" class="w-3.5 h-3.5 text-amber-600" />
                                            <Pin v-else class="w-3.5 h-3.5 text-amber-600" />
                                            <span>{{ msg.is_pinned ? 'Unpin Message' : 'Pin Message' }}</span>
                                        </button>

                                        <!-- Message Info (WhatsApp Read & Delivery breakdown) -->
                                        <button 
                                            v-if="msg.sender_id === currentUser?.id"
                                            @click="openMessageInfoModal(msg)"
                                            class="w-full px-3.5 py-2 text-xs text-left hover:bg-slate-50 flex items-center gap-2 font-medium text-slate-700"
                                        >
                                            <Info class="w-3.5 h-3.5 text-sky-600" />
                                            <span>Message Info</span>
                                        </button>

                                        <!-- Edit (only for sender & text messages) -->
                                        <button 
                                            v-if="msg.sender_id === currentUser?.id && msg.type === 'text'"
                                            @click="startEditMessage(msg)"
                                            class="w-full px-3.5 py-2 text-xs text-left hover:bg-slate-50 flex items-center gap-2 font-medium"
                                        >
                                            <Edit3 class="w-3.5 h-3.5 text-brand-600" />
                                            <span>Edit</span>
                                        </button>

                                        <!-- Delete -->
                                        <button 
                                            @click="openDeleteModal(msg)"
                                            class="w-full px-3.5 py-2 text-xs text-left hover:bg-rose-50 text-rose-600 flex items-center gap-2 font-medium"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Private Group Pending Approval Overlay Card - YB - 26-08-2026 -->
                <div 
                    v-if="activeConversation && activeConversation.is_member === false" 
                    class="absolute inset-0 z-30 flex items-center justify-center p-6 bg-slate-900/30 backdrop-blur-xl animate-in fade-in duration-300"
                >
                    <div class="bg-white/95 backdrop-blur-2xl border border-slate-200/80 rounded-3xl p-8 max-w-md w-full text-center shadow-2xl space-y-5 animate-in zoom-in-95 duration-200 relative overflow-hidden">
                        <!-- Ambient Glow -->
                        <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-48 h-48 bg-brand-500/20 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="relative flex flex-col items-center">
                            <div class="relative mb-3">
                                <img 
                                    :src="activeConversation.avatar_url" 
                                    alt="Group Avatar" 
                                    class="w-20 h-20 rounded-3xl object-cover ring-4 ring-white shadow-xl bg-slate-100"
                                />
                                <div class="absolute -bottom-1.5 -right-1.5 w-7 h-7 rounded-full bg-gradient-to-r from-brand-600 to-brand-700 text-white flex items-center justify-center shadow-md border-2 border-white">
                                    <Lock class="w-3.5 h-3.5" />
                                </div>
                            </div>

                            <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">
                                {{ activeConversation.title }}
                            </h3>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 mt-2 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200/80 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                Request Pending Approval
                            </span>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed max-w-xs mx-auto">
                            Your request to join this private group has been submitted. Group messages and info will automatically unlock here as soon as an administrator approves your request.
                        </p>

                        <div class="pt-2 flex flex-col gap-2">
                            <button 
                                @click="isMobileChatOpen = false; router.visit(route('chat.index'))"
                                class="w-full py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-2xl border border-slate-200 transition"
                            >
                                Back to Chats
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modern Message Composer Bar (Hidden for non-members) -->
                <div 
                    v-if="activeConversation && activeConversation.is_member !== false"
                    class="p-2.5 sm:p-3.5 md:p-4 bg-white/95 backdrop-blur-xl border-t border-slate-200/80 flex-shrink-0 shadow-sm pb-[max(0.625rem,env(safe-area-inset-bottom))]"
                >
                    <!-- Active Quoted Reply Banner (WhatsApp Style) - YB - 26-08-2026 -->
                    <div 
                        v-if="replyingToMessage" 
                        class="mb-2.5 px-3.5 py-2.5 bg-brand-50/90 rounded-2xl border-l-4 border-brand-600 border border-brand-200 flex items-center justify-between text-xs animate-in fade-in"
                    >
                        <div class="flex items-center space-x-2.5 min-w-0">
                            <CornerUpLeft class="w-4 h-4 text-brand-600 flex-shrink-0" />
                            <div class="min-w-0">
                                <p class="text-brand-700 font-bold text-[11px]">
                                    Replying to {{ replyingToMessage.sender?.name || 'User' }}
                                </p>
                                <p class="text-slate-600 text-xs truncate" v-html="renderAppleEmojisHtml(replyingToMessage.body || (replyingToMessage.attachment_name ? '📎 ' + replyingToMessage.attachment_name : ''))"></p>
                            </div>
                        </div>
                        <button 
                            @click="cancelReply" 
                            class="p-1 hover:bg-brand-100 text-slate-400 hover:text-slate-700 rounded-lg transition"
                            title="Cancel reply"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Form when editing (with Apple iOS Emojis) - YB - 08-09-2026 -->
                    <form v-if="editingMessage" @submit.prevent="saveEditMessage" class="flex items-end space-x-1.5 sm:space-x-2.5 relative">
                        <!-- Apple iOS Emoji Picker Button for Edit Mode -->
                        <div class="relative flex-shrink-0">
                            <button 
                                type="button" 
                                @click.stop="isComposerEmojiPickerOpen = !isComposerEmojiPickerOpen"
                                class="p-2.5 sm:p-3 text-slate-500 hover:text-brand-600 hover:bg-brand-50 rounded-2xl transition-all flex-shrink-0 border border-slate-200 bg-slate-50 shadow-xs"
                                :class="isComposerEmojiPickerOpen ? 'text-brand-600 bg-brand-50 border-brand-300 ring-2 ring-brand-500/20' : ''"
                                title="Insert Apple iOS Emoji"
                            >
                                <Smile class="w-4 h-4 sm:w-5 sm:h-5" />
                            </button>

                            <!-- Apple iOS Emoji Popover above input -->
                            <div 
                                v-if="isComposerEmojiPickerOpen" 
                                class="absolute bottom-full mb-3 left-0 -ml-12 sm:ml-0 z-50 animate-in fade-in zoom-in-95 shadow-2xl max-w-[calc(100vw-1.5rem)]"
                                @click.stop
                            >
                                <EmojiPicker 
                                    :is-open="true" 
                                    @select="handleComposerEmojiSelect" 
                                    @close="isComposerEmojiPickerOpen = false" 
                                />
                            </div>
                        </div>

                        <div class="flex-1 relative min-w-0">
                            <AppleChatInput 
                                ref="editInputRef"
                                v-model="editInput"
                                placeholder="Edit your message..."
                                @send="saveEditMessage"
                                maxHeight="8rem"
                            />
                        </div>
                        <button 
                            type="button" 
                            @click="cancelEditMessage"
                            class="px-2.5 sm:px-3.5 py-2.5 sm:py-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-2xl border border-slate-200 flex-shrink-0 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="!editInput.trim() || isEditing"
                            class="p-2.5 sm:p-3 bg-gradient-to-tr from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-40 text-white rounded-2xl shadow-md shadow-brand-600/25 transition-all duration-200 hover:scale-105 active:scale-95 disabled:hover:scale-100 flex items-center justify-center flex-shrink-0"
                            title="Save Changes"
                        >
                            <Check class="w-5 h-5" />
                        </button>
                    </form>

                    <!-- Normal Send Form (with Apple iOS Emojis) -->
                    <form v-else @submit.prevent="sendMessage" class="flex items-end space-x-1.5 sm:space-x-2.5 relative">
                        <!-- Attachment Input Button -->
                        <input 
                            ref="fileInput"
                            type="file" 
                            @change="onFileSelected"
                            class="hidden" 
                            accept="image/*,.pdf,.doc,.docx,.zip,.txt"
                        />
                        <button 
                            type="button" 
                            @click="fileInput?.click()"
                            class="p-2.5 sm:p-3 text-slate-500 hover:text-brand-600 hover:bg-brand-50 rounded-2xl transition-all flex-shrink-0 border border-slate-200 bg-slate-50 shadow-xs"
                            title="Attach File or Image"
                        >
                            <Paperclip class="w-4 h-4 sm:w-5 sm:h-5" />
                        </button>

                        <!-- Apple iOS Emoji Picker Button - YB - 26-08-2026 -->
                        <div class="relative flex-shrink-0">
                            <button 
                                type="button" 
                                @click.stop="isComposerEmojiPickerOpen = !isComposerEmojiPickerOpen"
                                class="p-2.5 sm:p-3 text-slate-500 hover:text-brand-600 hover:bg-brand-50 rounded-2xl transition-all flex-shrink-0 border border-slate-200 bg-slate-50 shadow-xs"
                                :class="isComposerEmojiPickerOpen ? 'text-brand-600 bg-brand-50 border-brand-300 ring-2 ring-brand-500/20' : ''"
                                title="Insert Apple iOS Emoji"
                            >
                                <Smile class="w-4 h-4 sm:w-5 sm:h-5" />
                            </button>

                            <!-- Apple iOS Emoji Popover above input -->
                            <div 
                                v-if="isComposerEmojiPickerOpen" 
                                class="absolute bottom-full mb-3 left-0 -ml-12 sm:ml-0 z-50 animate-in fade-in zoom-in-95 shadow-2xl max-w-[calc(100vw-1.5rem)]"
                                @click.stop
                            >
                                <EmojiPicker 
                                    :is-open="true" 
                                    @select="handleComposerEmojiSelect" 
                                    @close="isComposerEmojiPickerOpen = false" 
                                />
                            </div>
                        </div>

                        <!-- Rich Apple iOS Emoji Chat Input -->
                        <div class="flex-1 relative min-w-0">
                            <AppleChatInput 
                                ref="composerInputRef"
                                v-model="messageInput"
                                placeholder="Type a message..."
                                @send="sendMessage"
                                @typing="handleTypingInput"
                                maxHeight="9rem"
                            />
                        </div>

                        <!-- Send Button with Brand Glow -->
                        <button 
                            type="submit" 
                            :disabled="(!messageInput.trim() && !selectedFile) || isSending"
                            class="p-2.5 sm:p-3 bg-gradient-to-tr from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-40 text-white rounded-2xl shadow-md shadow-brand-600/25 transition-all duration-200 flex items-center justify-center flex-shrink-0 hover:scale-105 active:scale-95"
                        >
                            <Send class="w-4 h-4 sm:w-5 sm:h-5" />
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- New Chat Modal -->
        <div 
            v-if="isModalOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-md p-4 animate-in fade-in duration-200"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl">
                <!-- Modal Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight">New Conversation</h3>
                        <p class="text-xs text-slate-500">Search users and start chatting in real-time</p>
                    </div>
                    <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-xl hover:bg-slate-100 transition">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Tabs (Direct vs Group) -->
                <div class="p-2 bg-slate-50 border-b border-slate-100 flex space-x-2">
                    <button 
                        @click="modalTab = 'direct'"
                        :class="[
                            'flex-1 py-2 text-xs font-bold rounded-xl transition',
                            modalTab === 'direct' ? 'bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 text-white shadow-md shadow-brand-600/25' : 'text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        Direct Message
                    </button>
                    <button 
                        @click="modalTab = 'group'"
                        :class="[
                            'flex-1 py-2 text-xs font-bold rounded-xl transition',
                            modalTab === 'group' ? 'bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 text-white shadow-md shadow-brand-600/25' : 'text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        Group Chat
                    </button>
                </div>

                <!-- Group Title Input (Group tab only) -->
                <div v-if="modalTab === 'group'" class="p-4 border-b border-slate-100 space-y-2">
                    <label class="text-xs font-bold text-slate-700">Group Name</label>
                    <input 
                        v-model="groupTitle"
                        type="text" 
                        placeholder="e.g. TalkSpace Core Team 🚀" 
                        class="w-full glass-input text-slate-800 text-sm rounded-xl px-3.5 py-2.5 border border-slate-200 focus:outline-none shadow-xs"
                    />
                </div>

                <!-- User Search Input -->
                <div class="p-4 border-b border-slate-100">
                    <div class="relative">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="userSearchQuery"
                            @input="searchUsers"
                            type="text" 
                            placeholder="Search by name or email..." 
                            class="w-full glass-input text-slate-800 placeholder-slate-400 text-xs rounded-xl pl-10 pr-4 py-2.5 border border-slate-200 focus:outline-none shadow-xs"
                        />
                    </div>
                </div>

                <!-- Users List -->
                <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 p-2 custom-scrollbar space-y-1">
                    <div v-if="isSearchingUsers" class="p-6 text-center text-xs text-slate-500">
                        Searching users...
                    </div>
                    <div v-else-if="availableUsers.length === 0" class="p-6 text-center text-xs text-slate-500">
                        No users found.
                    </div>

                    <div 
                        v-for="u in availableUsers" 
                        :key="u.id"
                        @click="modalTab === 'direct' ? startDirectChat(u) : toggleGroupParticipant(u.id)"
                        class="p-2.5 hover:bg-slate-50 rounded-2xl cursor-pointer flex items-center justify-between transition"
                    >
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="relative flex-shrink-0">
                                <img :src="u.avatar_url" alt="Avatar" class="w-10 h-10 rounded-xl object-cover ring-1 ring-slate-200 shadow-xs" />
                                <span 
                                    v-if="isUserOnline(u.id)"
                                    class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white glow-emerald"
                                ></span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-800 truncate">{{ u.name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ u.email }}</p>
                            </div>
                        </div>

                        <!-- Checkbox for Group Mode -->
                        <div v-if="modalTab === 'group'" class="flex items-center">
                            <div 
                                class="w-5 h-5 rounded-lg border flex items-center justify-center transition"
                                :class="selectedGroupParticipants.includes(u.id) ? 'bg-brand-600 border-brand-600 text-white' : 'border-slate-300 bg-white'"
                            >
                                <Check v-if="selectedGroupParticipants.includes(u.id)" class="w-3.5 h-3.5" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-slate-500">
                        <span v-if="modalTab === 'group'">{{ selectedGroupParticipants.length }} participants selected</span>
                    </p>
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="isModalOpen = false" 
                            class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 rounded-xl transition"
                        >
                            Cancel
                        </button>
                        <button 
                            v-if="modalTab === 'group'"
                            @click="createGroupChat"
                            :disabled="selectedGroupParticipants.length === 0 || !groupTitle.trim()"
                            class="px-4 py-2 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-40 text-white text-xs font-bold rounded-xl shadow-md shadow-brand-600/25 transition flex items-center gap-1.5"
                        >
                            <Users class="w-3.5 h-3.5" />
                            Create Group
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. User Profile Modal -->
        <div 
            v-if="isProfileModalOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="isProfileModalOpen = false"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
                <!-- Header Banner -->
                <div class="h-28 bg-gradient-to-r from-brand-600 via-brand-700 to-purple-800 relative p-4 flex justify-between items-start">
                    <span class="px-2.5 py-1 bg-black/20 backdrop-blur-md rounded-lg text-white/90 text-xs font-semibold">TalkSpace Member</span>
                    <button 
                        @click="isProfileModalOpen = false"
                        class="w-8 h-8 rounded-full bg-black/20 hover:bg-black/40 backdrop-blur-md text-white flex items-center justify-center transition"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Profile Info -->
                <div class="px-6 pb-6 pt-0 relative">
                    <div class="relative -mt-12 mb-4 flex items-end justify-between">
                        <div class="relative">
                            <img 
                                :src="selectedProfileUser?.avatar_url || activeConversation?.avatar_url || 'https://ui-avatars.com/api/?name=User'" 
                                alt="Avatar" 
                                class="w-24 h-24 rounded-3xl object-cover ring-4 ring-white shadow-xl bg-slate-100"
                            />
                            <span 
                                v-if="selectedProfileUser && isUserOnline(selectedProfileUser.id)"
                                class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-500 rounded-full border-2 border-white glow-emerald"
                            ></span>
                        </div>

                        <!-- Quick actions -->
                        <div class="flex items-center space-x-2">
                            <button 
                                @click="initiateCall('audio')"
                                class="p-3 rounded-2xl bg-brand-50 hover:bg-brand-100 text-brand-600 border border-brand-200 transition hover:scale-105"
                                title="Voice Call"
                            >
                                <Phone class="w-4 h-4" />
                            </button>
                            <button 
                                @click="initiateCall('video')"
                                class="p-3 rounded-2xl bg-purple-50 hover:bg-purple-100 text-purple-600 border border-purple-200 transition hover:scale-105"
                                title="Video Call"
                            >
                                <Video class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Name and Status -->
                    <div class="space-y-1">
                        <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                            {{ selectedProfileUser?.name || activeConversation?.title || 'User' }}
                            <ShieldCheck class="w-4 h-4 text-brand-600" />
                        </h2>
                        
                        <!-- Real-time Presence & Last Active status in Modal -->
                        <div class="flex items-center gap-2 mt-1">
                            <template v-if="selectedProfileUser && isUserOnline(selectedProfileUser.id)">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 glow-emerald"></span>
                                <p class="text-xs font-semibold text-emerald-600">Online now</p>
                            </template>
                            <template v-else-if="selectedProfileUser?.last_active_at || selectedProfileUser?.last_active_human">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <p class="text-xs text-slate-500">{{ formatLastActive(selectedProfileUser.last_active_at, selectedProfileUser.last_active_human) }}</p>
                            </template>
                            <template v-else>
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <p class="text-xs text-slate-500">Offline</p>
                            </template>
                        </div>
                    </div>

                    <!-- Details Card -->
                    <div class="mt-5 space-y-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl p-4 text-xs">
                        <div class="flex items-center justify-between py-1">
                            <span class="text-slate-500 flex items-center gap-2"><Mail class="w-4 h-4 text-slate-400" /> Email</span>
                            <span class="text-slate-800 font-semibold">{{ selectedProfileUser?.email || 'N/A' }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-t border-slate-200/60">
                            <span class="text-slate-500 flex items-center gap-2"><Shield class="w-4 h-4 text-slate-400" /> Status</span>
                            <span class="text-emerald-600 font-semibold">Verified User</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-t border-slate-200/60">
                            <span class="text-slate-500 flex items-center gap-2"><Calendar class="w-4 h-4 text-slate-400" /> Member Since</span>
                            <span class="text-slate-800 font-semibold">Active Member</span>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div class="mt-5">
                        <button 
                            @click="isProfileModalOpen = false"
                            class="w-full py-3 bg-gradient-to-r from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 text-white rounded-2xl text-xs font-bold shadow-md shadow-brand-600/25 transition-all"
                        >
                            Close Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Active Call Interface Modal (Audio / Video) -->
        <div 
            v-if="isCallModalOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/80 backdrop-blur-md animate-in fade-in duration-200"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 text-center space-y-6 animate-in zoom-in-95 duration-200">
                
                <!-- Video Streams (if video call) -->
                <div v-if="activeCallType === 'video'" class="relative w-full h-64 bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 flex items-center justify-center">
                    <video ref="remoteVideoRef" autoplay playsinline class="w-full h-full object-cover"></video>
                    <video ref="localVideoRef" autoplay playsinline muted class="absolute bottom-3 right-3 w-28 h-20 object-cover rounded-xl border border-white/20 shadow-lg"></video>
                    <div v-if="callStatus !== 'connected'" class="absolute inset-0 bg-slate-950/80 flex items-center justify-center text-xs text-slate-300">
                        Connecting video stream...
                    </div>
                </div>

                <!-- Avatar for Audio Call -->
                <div v-else class="relative mx-auto w-24 h-24">
                    <img 
                        :src="activeConversation?.avatar_url || incomingCallData?.caller?.avatar_url || 'https://ui-avatars.com/api/?name=User'" 
                        alt="Avatar" 
                        class="w-24 h-24 rounded-3xl object-cover ring-4 ring-brand-500/40 shadow-xl"
                    />
                    <span v-if="callStatus === 'calling'" class="absolute inset-0 rounded-3xl border-2 border-brand-500 animate-ping opacity-30"></span>
                </div>

                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-slate-900">
                        {{ activeConversation?.title || incomingCallData?.caller?.name || 'Call' }}
                    </h3>
                    <p class="text-xs font-bold" :class="callStatus === 'connected' ? 'text-emerald-600' : 'text-brand-600 animate-pulse'">
                        <template v-if="callStatus === 'calling'">Ringing...</template>
                        <template v-else-if="callStatus === 'connected'">Connected • {{ formattedCallDuration }}</template>
                        <template v-else-if="callStatus === 'ended'">Call Ended</template>
                    </p>
                </div>

                <div class="flex items-center justify-center space-x-6 pt-2">
                    <button 
                        @click="endCall('completed')"
                        class="w-14 h-14 rounded-full bg-rose-600 hover:bg-rose-500 text-white flex items-center justify-center shadow-lg shadow-rose-600/30 hover:scale-110 active:scale-95 transition-all"
                        title="End Call"
                    >
                        <Phone class="w-6 h-6 rotate-[135deg]" />
                    </button>
                </div>
            </div>
        </div>

        <!-- 4. Incoming Call Dialog (Accept / Reject) -->
        <div 
            v-if="isIncomingCallModalOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xl animate-in fade-in duration-200"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl p-6 text-center space-y-6 animate-bounce duration-500">
                <div class="relative mx-auto w-24 h-24">
                    <img 
                        :src="incomingCallData?.caller?.avatar_url || 'https://ui-avatars.com/api/?name=User'" 
                        alt="Avatar" 
                        class="w-24 h-24 rounded-3xl object-cover ring-4 ring-brand-500 shadow-xl"
                    />
                    <span class="absolute inset-0 rounded-3xl border-2 border-emerald-500 animate-ping opacity-50"></span>
                </div>

                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-slate-900">{{ incomingCallData?.caller?.name }}</h3>
                    <p class="text-xs text-emerald-600 font-bold animate-pulse">
                        Incoming {{ incomingCallData?.call_type === 'video' ? 'Video' : 'Voice' }} Call...
                    </p>
                </div>

                <div class="flex items-center justify-center space-x-6 pt-2">
                    <!-- Decline -->
                    <button 
                        @click="rejectIncomingCall"
                        class="w-14 h-14 rounded-full bg-rose-600 hover:bg-rose-500 text-white flex items-center justify-center shadow-lg shadow-rose-600/30 hover:scale-110 active:scale-95 transition-all"
                        title="Decline"
                    >
                        <Phone class="w-6 h-6 rotate-[135deg]" />
                    </button>

                    <!-- Accept -->
                    <button 
                        @click="acceptIncomingCall"
                        class="w-14 h-14 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center shadow-lg shadow-emerald-600/30 hover:scale-110 active:scale-95 transition-all animate-pulse"
                        title="Accept Call"
                    >
                        <Phone class="w-6 h-6" />
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. WhatsApp Style Delete Message Dialog (For Me vs For Everyone) -->
        <div 
            v-if="isDeleteModalOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl p-6 space-y-4 animate-in zoom-in-95">
                <div class="flex items-center space-x-3 text-rose-600">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center border border-rose-100">
                        <Trash2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-slate-900">Delete Message?</h3>
                        <p class="text-xs text-slate-500">Choose how you want to delete this message.</p>
                    </div>
                </div>

                <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-xs text-slate-700 italic truncate max-h-20">
                    "{{ deleteConfirmMessage?.body || 'Attachment file' }}"
                </div>

                <div class="space-y-2 pt-1">
                    <!-- Delete for Everyone (if current user sent it) -->
                    <button 
                        v-if="deleteConfirmMessage?.sender_id === currentUser?.id"
                        @click="executeDeleteMessage('everyone')"
                        class="w-full py-2.5 px-4 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-xs rounded-xl border border-rose-200 transition text-center flex items-center justify-center gap-2"
                    >
                        <span>Delete for Everyone</span>
                    </button>

                    <!-- Delete for Me -->
                    <button 
                        @click="executeDeleteMessage('me')"
                        class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200/80 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition text-center"
                    >
                        Delete for Me
                    </button>

                    <!-- Cancel -->
                    <button 
                        @click="closeDeleteModal"
                        class="w-full py-2 text-xs font-semibold text-slate-400 hover:text-slate-700 transition text-center"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- 6. WhatsApp Style Group Info Modal / Drawer - YB - 26-08-2026 -->
        <div 
            v-if="isGroupInfoModalOpen && activeConversation?.type === 'group'" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="isGroupInfoModalOpen = false"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden shadow-2xl animate-in zoom-in-95 duration-200">
                <!-- Header Banner -->
                <div class="h-28 bg-gradient-to-r from-brand-600 via-brand-700 to-purple-800 relative p-4 flex justify-between items-start flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 bg-black/20 backdrop-blur-md rounded-lg text-white/90 text-xs font-semibold flex items-center gap-1.5">
                            <Users class="w-3.5 h-3.5" /> Group Info
                        </span>
                        <span 
                            class="px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1"
                            :class="activeConversation.is_public ? 'bg-emerald-500/30 text-white border border-emerald-400/40' : 'bg-slate-900/40 text-slate-200 border border-white/20'"
                        >
                            <Globe v-if="activeConversation.is_public" class="w-3 h-3 text-emerald-300" />
                            <Lock v-else class="w-3 h-3 text-slate-300" />
                            {{ activeConversation.is_public ? 'Public Group' : 'Private Group' }}
                        </span>
                    </div>
                    <button 
                        @click="isGroupInfoModalOpen = false"
                        class="w-8 h-8 rounded-full bg-black/20 hover:bg-black/40 backdrop-blur-md text-white flex items-center justify-center transition"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Group Avatar & Main Overview -->
                <div class="px-6 pt-0 pb-3 relative border-b border-slate-100 flex-shrink-0">
                    <div class="relative -mt-12 mb-3 flex items-end justify-between">
                        <!-- Group Avatar (with Admin Upload capability) -->
                        <div class="relative group">
                            <img 
                                :src="activeConversation.avatar_url" 
                                alt="Group Icon" 
                                class="w-24 h-24 rounded-3xl object-cover ring-4 ring-white shadow-xl bg-slate-100"
                            />
                            <button 
                                v-if="activeConversation.is_admin"
                                @click="$refs.groupAvatarInput?.click()"
                                class="absolute inset-0 rounded-3xl bg-black/40 text-white opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center transition backdrop-blur-xs"
                                title="Change Group Icon"
                            >
                                <Camera class="w-5 h-5 mb-0.5" />
                                <span class="text-[9px] font-bold">Change</span>
                            </button>
                            <input 
                                ref="groupAvatarInput" 
                                type="file" 
                                accept="image/*" 
                                class="hidden" 
                                @change="onGroupAvatarFileChanged" 
                            />
                        </div>

                        <!-- Admin Actions for Settings -->
                        <div v-if="activeConversation.is_admin" class="flex items-center space-x-2">
                            <button 
                                @click="isEditGroupSettingsOpen = true"
                                class="px-3.5 py-2 rounded-xl bg-brand-50 hover:bg-brand-100 text-brand-600 border border-brand-200 text-xs font-bold flex items-center gap-1.5 transition hover:scale-105"
                            >
                                <Settings class="w-3.5 h-3.5" />
                                <span>Edit Group</span>
                            </button>
                        </div>
                    </div>

                    <!-- Group Title & Bio -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                                {{ activeConversation.title }}
                            </h2>
                        </div>
                        <p v-if="activeConversation.description" class="text-xs text-slate-600 whitespace-pre-wrap leading-relaxed">
                            {{ activeConversation.description }}
                        </p>
                        <p v-else class="text-xs text-slate-400 italic">
                            No group description added yet.
                        </p>
                    </div>

                    <!-- Shareable Invite Link Card -->
                    <div class="mt-4 p-3 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-700 flex items-center gap-1.5">
                                <LinkIcon class="w-3.5 h-3.5 text-brand-600" />
                                Group Invite Link
                            </span>
                            <button 
                                v-if="activeConversation.is_admin"
                                @click="resetGroupInviteLink"
                                class="text-[10px] font-bold text-slate-400 hover:text-brand-600 transition"
                                title="Generate a new link"
                            >
                                Reset Link
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input 
                                :value="activeConversation.invite_url" 
                                readonly 
                                class="flex-1 text-xs bg-white px-3 py-2 rounded-xl border border-slate-200 text-slate-700 select-all focus:outline-none"
                            />
                            <button 
                                @click="copyGroupInviteLink"
                                class="px-3 py-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-500 hover:to-brand-600 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm transition"
                            >
                                <Check v-if="copiedInviteLink" class="w-3.5 h-3.5 text-emerald-300" />
                                <Copy v-else class="w-3.5 h-3.5" />
                                <span>{{ copiedInviteLink ? 'Copied!' : 'Copy' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs (Members vs Join Requests) -->
                <div class="px-6 pt-2 border-b border-slate-100 flex space-x-4 flex-shrink-0">
                    <button 
                        @click="groupInfoTab = 'members'"
                        :class="[
                            'pb-2.5 text-xs font-bold border-b-2 transition flex items-center gap-2',
                            groupInfoTab === 'members' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        <span>Members</span>
                        <span class="px-1.5 py-0.5 rounded-full bg-slate-100 text-[10px] text-slate-600">
                            {{ activeConversation.participants?.length || 0 }}
                        </span>
                    </button>
                    
                    <button 
                        v-if="activeConversation.is_admin && !activeConversation.is_public"
                        @click="groupInfoTab = 'requests'"
                        :class="[
                            'pb-2.5 text-xs font-bold border-b-2 transition flex items-center gap-2',
                            groupInfoTab === 'requests' ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-800'
                        ]"
                    >
                        <span>Join Requests</span>
                        <span 
                            class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                            :class="activeConversation.join_requests?.length > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ activeConversation.join_requests?.length || 0 }}
                        </span>
                    </button>
                </div>

                <!-- Tab Content Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-4 custom-scrollbar space-y-2">
                    
                    <!-- TAB 1: MEMBERS LIST -->
                    <div v-if="groupInfoTab === 'members'" class="space-y-2">
                        <!-- Add Members Button (Admins only) -->
                        <div v-if="activeConversation.is_admin" class="mb-3">
                            <button 
                                @click="openAddMembersModal"
                                class="w-full py-2.5 px-4 rounded-2xl bg-brand-50 hover:bg-brand-100 text-brand-600 border border-brand-200/80 text-xs font-bold flex items-center justify-center gap-2 transition"
                            >
                                <UserPlus class="w-4 h-4" />
                                <span>Add Participants</span>
                            </button>
                        </div>

                        <!-- Member Rows -->
                        <div 
                            v-for="member in activeConversation.participants" 
                            :key="member.id"
                            class="p-2.5 rounded-2xl hover:bg-slate-50 border border-transparent hover:border-slate-200/60 flex items-center justify-between transition relative group/member"
                        >
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="relative flex-shrink-0">
                                    <img :src="member.avatar_url" alt="Avatar" class="w-10 h-10 rounded-2xl object-cover ring-1 ring-slate-200" />
                                    <span 
                                        v-if="isUserOnline(member.id)"
                                        class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white glow-emerald"
                                    ></span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs font-bold text-slate-800 truncate">
                                            {{ member.name }}
                                            <span v-if="member.id === currentUser?.id" class="text-slate-400 font-normal">(You)</span>
                                        </p>
                                        <!-- Group Admin Badge -->
                                        <span 
                                            v-if="member.role === 'admin'"
                                            class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold flex items-center gap-1"
                                        >
                                            <Crown class="w-2.5 h-2.5 text-emerald-600" /> Admin
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 truncate">
                                        {{ isUserOnline(member.id) ? 'Online now' : formatLastActive(member.last_active_at, member.last_active_human) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Admin 3-Dot Dropdown Options for other members -->
                            <div v-if="activeConversation.is_admin && member.id !== currentUser?.id" class="relative">
                                <button 
                                    @click="activeMemberActionMenuId = activeMemberActionMenuId === member.id ? null : member.id"
                                    class="p-1.5 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition"
                                >
                                    <MoreVertical class="w-4 h-4" />
                                </button>

                                <!-- Dropdown menu -->
                                <div 
                                    v-if="activeMemberActionMenuId === member.id"
                                    class="absolute right-0 top-8 w-44 bg-white border border-slate-200 rounded-2xl shadow-xl py-1 z-50 animate-in fade-in zoom-in-95 duration-100"
                                >
                                    <!-- Make Admin / Dismiss Admin -->
                                    <button 
                                        v-if="member.role !== 'admin'"
                                        @click="changeMemberRole(member.id, 'admin')"
                                        class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-600 flex items-center gap-2 transition"
                                    >
                                        <Crown class="w-3.5 h-3.5 text-amber-500" />
                                        <span>Make Group Admin</span>
                                    </button>
                                    <button 
                                        v-else
                                        @click="changeMemberRole(member.id, 'member')"
                                        class="w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2 transition"
                                    >
                                        <UserMinus class="w-3.5 h-3.5 text-slate-400" />
                                        <span>Dismiss as Admin</span>
                                    </button>

                                    <!-- Remove Member -->
                                    <button 
                                        @click="removeGroupMember(member.id)"
                                        class="w-full px-3.5 py-2 text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition"
                                    >
                                        <Trash2 class="w-3.5 h-3.5 text-rose-500" />
                                        <span>Remove from Group</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: JOIN REQUESTS (Private Groups) -->
                    <div v-else-if="groupInfoTab === 'requests'" class="space-y-2">
                        <div v-if="!activeConversation.join_requests || activeConversation.join_requests.length === 0" class="p-8 text-center text-xs text-slate-400">
                            No pending join requests.
                        </div>

                        <div 
                            v-for="req in activeConversation.join_requests" 
                            :key="req.id"
                            class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl flex items-center justify-between"
                        >
                            <div class="flex items-center space-x-3 min-w-0">
                                <img :src="req.user.avatar_url" alt="Avatar" class="w-10 h-10 rounded-2xl object-cover ring-1 ring-slate-200" />
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800 truncate">{{ req.user.name }}</p>
                                    <p class="text-[10px] text-slate-400 truncate">{{ req.user.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-1.5">
                                <button 
                                    @click="handleApproveRequest(req.id)"
                                    class="p-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-500 hover:to-brand-600 text-white rounded-xl text-xs font-bold flex items-center gap-1 shadow-md shadow-brand-500/25 transition"
                                    title="Approve"
                                >
                                    <Check class="w-3.5 h-3.5" />
                                </button>
                                <button 
                                    @click="handleRejectRequest(req.id)"
                                    class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold flex items-center gap-1 border border-rose-200 transition"
                                    title="Reject"
                                >
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer: Leave Group Button -->
                <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between flex-shrink-0">
                    <button 
                        @click="leaveCurrentGroup"
                        class="px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-2xl border border-rose-200 flex items-center gap-2 transition"
                    >
                        <LogOut class="w-3.5 h-3.5" />
                        <span>Exit Group</span>
                    </button>
                    <button 
                        @click="isGroupInfoModalOpen = false"
                        class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold rounded-2xl transition"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- 7. Add Participants Modal - YB - 26-08-2026 -->
        <div 
            v-if="isAddMembersModalOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="isAddMembersModalOpen = false"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl flex flex-col max-h-[80vh]">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Add Participants</h3>
                        <p class="text-xs text-slate-500">Select users to add to {{ activeConversation?.title }}</p>
                    </div>
                    <button @click="isAddMembersModalOpen = false" class="text-slate-400 hover:text-slate-700 p-1.5 rounded-xl hover:bg-slate-100 transition">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Search Input -->
                <div class="p-4 border-b border-slate-100">
                    <div class="relative">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input 
                            v-model="userSearchQuery"
                            @input="searchUsers"
                            type="text" 
                            placeholder="Search contacts..." 
                            class="w-full glass-input text-slate-800 text-xs rounded-xl pl-10 pr-4 py-2.5 border border-slate-200 focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Users List (Excluding existing members) -->
                <div class="flex-1 overflow-y-auto p-2 custom-scrollbar space-y-1">
                    <div 
                        v-for="u in availableUsers.filter(u => !activeConversation?.participants?.some(p => p.id === u.id))" 
                        :key="u.id"
                        @click="toggleNewMemberSelection(u.id)"
                        class="p-2.5 hover:bg-slate-50 rounded-2xl cursor-pointer flex items-center justify-between transition"
                    >
                        <div class="flex items-center space-x-3 min-w-0">
                            <img :src="u.avatar_url" alt="Avatar" class="w-10 h-10 rounded-xl object-cover ring-1 ring-slate-200" />
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-800 truncate">{{ u.name }}</p>
                                <p class="text-[11px] text-slate-400 truncate">{{ u.email }}</p>
                            </div>
                        </div>

                        <!-- Checkbox -->
                        <div 
                            class="w-5 h-5 rounded-lg border flex items-center justify-center transition"
                            :class="selectedNewMembers.includes(u.id) ? 'bg-brand-600 border-brand-600 text-white' : 'border-slate-300 bg-white'"
                        >
                            <Check v-if="selectedNewMembers.includes(u.id)" class="w-3.5 h-3.5" />
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-slate-500">{{ selectedNewMembers.length }} selected</p>
                    <div class="flex items-center space-x-2">
                        <button @click="isAddMembersModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 rounded-xl transition">
                            Cancel
                        </button>
                        <button 
                            @click="submitAddMembers"
                            :disabled="selectedNewMembers.length === 0"
                            class="px-4 py-2 bg-brand-600 hover:bg-brand-700 disabled:opacity-40 text-white text-xs font-bold rounded-xl shadow-md transition"
                        >
                            Add Members
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 8. Edit Group Settings Modal - YB - 26-08-2026 -->
        <div 
            v-if="isEditGroupSettingsOpen" 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="isEditGroupSettingsOpen = false"
        >
            <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Group Settings</h3>
                    <button @click="isEditGroupSettingsOpen = false" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Title -->
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700">Group Name</label>
                        <input 
                            v-model="groupSettingsForm.title" 
                            type="text" 
                            class="w-full text-xs rounded-xl px-3.5 py-2.5 border border-slate-200 focus:outline-none" 
                        />
                    </div>

                    <!-- Description -->
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700">Group Description / Bio</label>
                        <textarea 
                            v-model="groupSettingsForm.description" 
                            rows="3" 
                            placeholder="Add group description, guidelines, or topics..." 
                            class="w-full text-xs rounded-xl px-3.5 py-2.5 border border-slate-200 focus:outline-none"
                        ></textarea>
                    </div>

                    <!-- Public / Private Toggle -->
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                        <label class="text-xs font-bold text-slate-700">Group Privacy Mode</label>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="radio" :value="false" v-model="groupSettingsForm.is_public" class="text-brand-600" />
                                <div>
                                    <span class="font-bold">Private</span>
                                    <p class="text-[10px] text-slate-400">People with link must be approved by admin</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="radio" :value="true" v-model="groupSettingsForm.is_public" class="text-brand-600" />
                                <div>
                                    <span class="font-bold">Public</span>
                                    <p class="text-[10px] text-slate-400">Anyone with the invite link can join directly</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button @click="isEditGroupSettingsOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 rounded-xl">
                        Cancel
                    </button>
                    <button @click="saveGroupSettings" class="px-5 py-2 bg-gradient-to-r from-brand-600 to-brand-700 text-white text-xs font-bold rounded-xl shadow-md">
                        Save Settings
                    </button>
                </div>
            </div>
        </div>

        <!-- 9. WhatsApp Style Message Info Modal (Who has seen & Who has received) - YB - 26-08-2026 -->
        <div 
            v-if="isMessageInfoModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-200"
            @click.self="isMessageInfoModalOpen = false"
        >
            <div 
                class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-200/80 space-y-4 animate-in zoom-in-95 duration-150"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <Info class="w-4 h-4 text-brand-600" />
                        Message Info
                    </h3>
                    <button @click="isMessageInfoModalOpen = false" class="text-slate-400 hover:text-slate-700 p-1 rounded-xl">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div v-if="loadingMessageInfo" class="py-12 text-center text-slate-400 text-xs flex flex-col items-center gap-2">
                    <span class="w-6 h-6 border-2 border-brand-600 border-t-transparent rounded-full animate-spin"></span>
                    <span>Loading delivery receipts...</span>
                </div>

                <div v-else-if="selectedMessageInfo" class="space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar pr-1">
                    <!-- Message Preview -->
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80 text-xs">
                        <p class="text-slate-800 font-medium whitespace-pre-wrap">{{ selectedMessageInfo.body || (selectedMessageInfo.attachment_name ? '📎 ' + selectedMessageInfo.attachment_name : 'Message') }}</p>
                        <p class="text-[10px] text-slate-400 mt-1.5 flex items-center justify-end">
                            Sent {{ selectedMessageInfo.created_at_formatted || formatMessageTime(selectedMessageInfo.created_at) }}
                        </p>
                    </div>

                    <!-- 1-on-1 Direct Chat WhatsApp-Style Delivery/Read Card - YB - 26-08-2026 -->
                    <template v-if="selectedMessageInfo.is_direct">
                        <div class="bg-slate-50/80 rounded-2xl border border-slate-200/80 divide-y divide-slate-100 overflow-hidden">
                            <!-- Read Timestamp -->
                            <div class="p-3.5 flex items-start gap-3">
                                <div class="p-2 rounded-xl bg-sky-50 text-sky-500 border border-sky-100 mt-0.5">
                                    <CheckCheck class="w-4 h-4 stroke-[2.5]" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-slate-800">Read</p>
                                    <p v-if="selectedMessageInfo.direct_read_at" class="text-[11px] text-slate-600 mt-0.5">
                                        {{ selectedMessageInfo.direct_read_at.formatted }}
                                        <span class="text-slate-400 ml-1">({{ selectedMessageInfo.direct_read_at.human_time }})</span>
                                    </p>
                                    <p v-else class="text-[11px] text-slate-400 mt-0.5 italic">
                                        Not read yet
                                    </p>
                                </div>
                            </div>

                            <!-- Delivered Timestamp -->
                            <div class="p-3.5 flex items-start gap-3">
                                <div class="p-2 rounded-xl bg-slate-100 text-slate-500 border border-slate-200 mt-0.5">
                                    <CheckCheck class="w-4 h-4 stroke-[2]" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-slate-800">Delivered</p>
                                    <p v-if="selectedMessageInfo.direct_delivered_at" class="text-[11px] text-slate-600 mt-0.5">
                                        {{ selectedMessageInfo.direct_delivered_at.formatted }}
                                        <span class="text-slate-400 ml-1">({{ selectedMessageInfo.direct_delivered_at.human_time }})</span>
                                    </p>
                                    <p v-else class="text-[11px] text-slate-400 mt-0.5 italic">
                                        Sent to server (Awaiting delivery)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Group Chat Multi-Participant Breakdown -->
                    <template v-else>
                        <!-- Read By Section (Double Blue Tick) -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold text-sky-600">
                                <span class="flex items-center gap-1.5">
                                    <CheckCheck class="w-4 h-4 text-sky-500 stroke-[2.5]" />
                                    Read by ({{ selectedMessageInfo.read_by?.length || 0 }})
                                </span>
                            </div>
                            <div v-if="!selectedMessageInfo.read_by || selectedMessageInfo.read_by.length === 0" class="p-3 text-center text-[11px] text-slate-400 bg-slate-50/50 rounded-xl">
                                No one has read this message yet.
                            </div>
                            <div v-else class="space-y-1.5">
                                <div 
                                    v-for="u in selectedMessageInfo.read_by" 
                                    :key="u.user?.id"
                                    class="p-2.5 bg-slate-50 border border-slate-200/60 rounded-xl flex items-center justify-between text-xs"
                                >
                                    <div class="flex items-center space-x-2.5 min-w-0">
                                        <img :src="u.user?.avatar_url || 'https://ui-avatars.com/api/?name=User&background=D91A8D&color=fff'" alt="Avatar" class="w-7 h-7 rounded-xl object-cover" />
                                        <p class="font-bold text-slate-800 truncate">{{ u.user?.name }}</p>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-medium">{{ u.human_time }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Delivered To Section (Double Grey Tick) -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-600">
                                <span class="flex items-center gap-1.5">
                                    <CheckCheck class="w-4 h-4 text-slate-400 stroke-[2]" />
                                    Delivered to ({{ selectedMessageInfo.delivered_to?.length || 0 }})
                                </span>
                            </div>
                            <div v-if="!selectedMessageInfo.delivered_to || selectedMessageInfo.delivered_to.length === 0" class="p-3 text-center text-[11px] text-slate-400 bg-slate-50/50 rounded-xl">
                                No additional pending deliveries.
                            </div>
                            <div v-else class="space-y-1.5">
                                <div 
                                    v-for="u in selectedMessageInfo.delivered_to" 
                                    :key="u.user?.id"
                                    class="p-2.5 bg-slate-50 border border-slate-200/60 rounded-xl flex items-center justify-between text-xs"
                                >
                                    <div class="flex items-center space-x-2.5 min-w-0">
                                        <img :src="u.user?.avatar_url || 'https://ui-avatars.com/api/?name=User&background=D91A8D&color=fff'" alt="Avatar" class="w-7 h-7 rounded-xl object-cover" />
                                        <p class="font-bold text-slate-800 truncate">{{ u.user?.name }}</p>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-medium">{{ u.human_time }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- 10. Reaction Details Modal - YB - 26-08-2026 -->
        <div 
            v-if="showReactionDetailsModal && selectedReactionMessage"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-in fade-in duration-200"
            @click.self="showReactionDetailsModal = false"
        >
            <div 
                class="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-slate-200/80 space-y-4 animate-in zoom-in-95 duration-150"
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <Smile class="w-4 h-4 text-brand-600" />
                        Message Reactions
                    </h3>
                    <button @click="showReactionDetailsModal = false" class="text-slate-400 hover:text-slate-700 p-1 rounded-xl">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="space-y-3 max-h-[60vh] overflow-y-auto custom-scrollbar pr-1">
                    <div v-for="r in selectedReactionMessage.reactions" :key="r.emoji" class="space-y-1.5 p-2 bg-slate-50 rounded-2xl border border-slate-100">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                            <AppleEmoji :emoji="r.emoji" size="1.5rem" />
                            <span class="text-slate-500 font-normal">({{ r.count }})</span>
                        </div>
                        <div class="space-y-1 pl-2">
                            <div v-for="u in r.users" :key="u.id" class="text-xs text-slate-700 font-semibold flex items-center justify-between py-0.5">
                                <span>{{ u.name }}</span>
                                <span v-if="u.id === currentUser?.id" class="text-[10px] text-brand-600 font-bold bg-brand-50 px-1.5 py-0.2 rounded-md">You</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
