<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
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
    Volume2
} from 'lucide-vue-next';
import { format, isToday, isYesterday, parseISO } from 'date-fns';

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

// Browser Desktop Push Notification
const showDesktopNotification = (title, body, icon = null) => {
    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification(title, { body, icon: icon || '/logos/favicon.ico' });
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification(title, { body, icon: icon || '/logos/favicon.ico' });
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
        selectedProfileUser.value = user;
    } else if (props.activeConversation?.direct_recipient) {
        selectedProfileUser.value = props.activeConversation.direct_recipient;
    } else {
        selectedProfileUser.value = currentUser.value;
    }
    isProfileModalOpen.value = true;
    isHeaderMenuOpen.value = false;
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

// YB - 25-08-2026 - Check if user is online with type-safe Number normalization & heartbeat check
const isUserOnline = (userId) => {
    if (!userId) return false;
    const numId = Number(userId);
    if (onlineUserIds.value.has(numId)) return true;
    
    // Also check active heartbeat within last 20 seconds
    const lastPing = userHeartbeats.value.get(numId);
    if (lastPing && (Date.now() - lastPing < 20000)) {
        return true;
    }
    return false;
};

// Check if direct conversation partner is online
const isConversationOnline = (conv) => {
    if (!conv || conv.type !== 'direct') return false;
    
    // Check direct_recipient first
    if (conv.direct_recipient?.id) {
        return isUserOnline(conv.direct_recipient.id);
    }

    // Fallback: check participants excluding current user
    if (Array.isArray(conv.participants) && currentUser.value) {
        const partner = conv.participants.find(p => Number(p.id) !== Number(currentUser.value.id));
        if (partner) {
            return isUserOnline(partner.id);
        }
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
    if (msg.deleted_for_user_ids && currentUser.value && msg.deleted_for_user_ids.includes(currentUser.value.id)) {
        return '🚫 You deleted this message';
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

    // Reset input immediately for snappy UX
    messageInput.value = '';
    clearSelectedFile();

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
    editingMessage.value = msg;
    editInput.value = msg.body;
    activeMessageMenuId.value = null;
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
        } else {
            // Delete for me: remove from local state immediately
            messageList.value = messageList.value.filter(m => m.id !== msgId);
        }

        closeDeleteModal();
    } catch (err) {
        console.error('Failed to delete message:', err);
    }
};

// Update conversation in sidebar
const updateConversationLatestMessage = (convId, msg) => {
    const idx = localConversations.value.findIndex(c => c.id === convId);
    if (idx !== -1) {
        const conv = { ...localConversations.value[idx] };
        conv.latest_message = msg;
        conv.last_message_at = msg.created_at;
        localConversations.value.splice(idx, 1);
        localConversations.value.unshift(conv);
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

    // 2. Heartbeat Channel: Guaranteed real-time active pings across all users
    window.Echo.channel('online-heartbeats')
        .listen('.user.heartbeat', (event) => {
            if (event.user_id && Number(event.user_id) !== Number(currentUser.value?.id)) {
                onlineUserIds.value = new Set([...onlineUserIds.value, Number(event.user_id)]);
                // Track last heartbeat timestamp
                userHeartbeats.value.set(Number(event.user_id), Date.now());
            }
        });

    // 3. Personal Private Channel: Notifications & inbox updates
    window.Echo.private(`user.${currentUser.value.id}`)
        .listen('.message.sent', (event) => {
            const incomingMsg = event.message;

            // Automatically acknowledge delivery back to sender so sender gets Double Tick immediately
            if (incomingMsg.conversation_id) {
                window.axios.post(route('chat.delivered', incomingMsg.conversation_id)).catch(() => {});
            }

            if (!props.activeConversation || props.activeConversation.id !== incomingMsg.conversation_id) {
                // Play notification chime & desktop notification
                playNotificationSound();
                showDesktopNotification(
                    `New message from ${incomingMsg.sender?.name || 'TalkSpace'}`,
                    incomingMsg.body || (incomingMsg.attachment_name ? '📎 Attachment' : 'Sent you a message')
                );

                // Update unread count and latest message in sidebar
                const conv = localConversations.value.find(c => c.id === incomingMsg.conversation_id);
                if (conv) {
                    conv.unread_count = (conv.unread_count || 0) + 1;
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
            if (!messageList.value.some(m => m.id === incomingMsg.id)) {
                messageList.value.push(incomingMsg);
                scrollToBottom(true);

                if (incomingMsg.sender_id !== currentUser.value?.id) {
                    playNotificationSound();
                }
            }
            updateConversationLatestMessage(props.activeConversation.id, incomingMsg);
            
            // Mark as read immediately since user is actively in the chat
            window.axios.post(route('chat.read', props.activeConversation.id));
        })
        .listen('.message.delivered', (event) => {
            if (event.user_id !== currentUser.value.id) {
                messageList.value.forEach(m => {
                    if (m.sender_id === currentUser.value.id && !m.delivered_at) {
                        m.delivered_at = event.delivered_at;
                    }
                });
            }
        })
        .listen('.message.updated', (event) => {
            const updatedMsg = event.message;
            const idx = messageList.value.findIndex(m => m.id === updatedMsg.id);
            if (idx !== -1) {
                messageList.value[idx] = updatedMsg;
            }
        })
        .listen('.user.typing', (event) => {
            if (event.user_id !== currentUser.value.id) {
                if (event.is_typing) {
                    typingUsers.value.set(event.user_id, event.user_name);
                } else {
                    typingUsers.value.delete(event.user_id);
                }
            }
        })
        .listen('.message.read', (event) => {
            if (event.user_id !== currentUser.value.id) {
                messageList.value.forEach(m => {
                    if (m.sender_id === currentUser.value.id) {
                        m.read_at = event.read_at;
                        m.delivered_at = event.read_at;
                    }
                });
            }
        })
        .listen('.call.signaled', async (event) => {
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

onMounted(() => {
    setupEchoListeners();
    scrollToBottom();

    // Send initial heartbeat immediately
    sendHeartbeat();

    // Send heartbeat every 5 seconds while user is actively browsing
    heartbeatInterval = setInterval(sendHeartbeat, 5000);

    // Prune users whose heartbeat has expired (> 15 seconds)
    pruneHeartbeatInterval = setInterval(() => {
        const now = Date.now();
        let changed = false;
        const currentSet = new Set(onlineUserIds.value);

        userHeartbeats.value.forEach((timestamp, uid) => {
            if (now - timestamp > 15000) {
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
    }, 10000);
});

onUnmounted(() => {
    clearInterval(heartbeatInterval);
    clearInterval(pruneHeartbeatInterval);

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
        <div class="h-[calc(100vh-4rem)] bg-[#F8FAFC] text-slate-800 flex overflow-hidden font-sans relative">
            
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
                            isSidebarCollapsed ? 'p-2 justify-center' : 'p-3 space-x-3',
                            activeConversation?.id === conv.id 
                                ? 'bg-brand-50/90 border-brand-200 shadow-xs ring-1 ring-brand-500/10' 
                                : 'hover:bg-slate-50/90 border-transparent hover:border-slate-200/60'
                        ]"
                        :title="isSidebarCollapsed ? conv.title : ''"
                    >
                        <!-- Avatar & Status Dot -->
                        <div class="relative flex-shrink-0">
                            <img 
                                :src="conv.avatar_url" 
                                alt="Avatar" 
                                :class="[
                                    'rounded-2xl object-cover ring-1 ring-slate-200 shadow-xs transition group-hover:scale-105',
                                    isSidebarCollapsed ? 'w-10 h-10' : 'w-12 h-12'
                                ]"
                            />
                            <span 
                                v-if="isConversationOnline(conv)"
                                class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white glow-emerald"
                                title="Online"
                            ></span>
                            <span 
                                v-else-if="conv.type === 'group'"
                                class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-brand-600 rounded-full border-2 border-white flex items-center justify-center text-[8px] text-white"
                            >
                                <Users class="w-2 h-2" />
                            </span>

                            <!-- Collapsed Unread dot badge -->
                            <span 
                                v-if="isSidebarCollapsed && conv.unread_count > 0 && activeConversation?.id !== conv.id"
                                class="absolute -top-1 -right-1 w-4 h-4 bg-brand-600 rounded-full text-white text-[9px] font-bold flex items-center justify-center border-2 border-white animate-pulse"
                            >
                                {{ conv.unread_count > 9 ? '9+' : conv.unread_count }}
                            </span>
                        </div>

                        <!-- Details (Hidden when sidebar is collapsed) -->
                        <div v-if="!isSidebarCollapsed" class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-1.5 min-w-0 pr-1">
                                    <Pin 
                                        v-if="pinnedConversationIds.has(conv.id)" 
                                        class="w-3 h-3 text-brand-600 fill-brand-600 flex-shrink-0" 
                                        title="Pinned conversation"
                                    />
                                    <h3 class="text-sm font-bold text-slate-900 truncate flex items-center gap-1">
                                        <span class="truncate">{{ conv.title }}</span>
                                    </h3>
                                </div>
                                <span class="text-[11px] text-slate-400 font-medium flex-shrink-0">
                                    {{ formatConversationTime(conv.last_message_at) }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <p class="text-xs text-slate-500 truncate pr-2">
                                    <span v-if="conv.latest_message && conv.latest_message.sender_id === currentUser?.id" class="text-brand-600 font-bold">You: </span>
                                    <span v-else-if="conv.type === 'group' && conv.latest_message?.sender?.name" class="text-slate-700 font-semibold">{{ conv.latest_message.sender.name.split(' ')[0] }}: </span>
                                    <span>{{ getLatestMessagePreview(conv) }}</span>
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
                    'flex-1 flex flex-col bg-[#F1F5F9] transition-all duration-300',
                    !isMobileChatOpen && !activeConversation ? 'hidden md:flex' : 'flex'
                ]"
            >
                <!-- Active Conversation Header with generous padding -->
                <div 
                    v-if="activeConversation"
                    class="h-20 py-3.5 px-6 md:px-8 bg-white/95 backdrop-blur-xl border-b border-slate-200/80 flex items-center justify-between flex-shrink-0 z-10 shadow-xs"
                >
                    <div class="flex items-center space-x-3.5 cursor-pointer group" @click="openUserProfile(activeConversation.direct_recipient || null)">
                        <button 
                            @click.stop="isMobileChatOpen = false" 
                            class="md:hidden p-2 -ml-2 text-slate-500 hover:text-slate-900 rounded-xl hover:bg-slate-100 transition"
                        >
                            <ArrowLeft class="w-5 h-5" />
                        </button>

                        <div class="relative">
                            <img 
                                :src="activeConversation.avatar_url" 
                                alt="Avatar" 
                                class="w-11 h-11 rounded-2xl object-cover ring-2 ring-brand-500/20 shadow-xs group-hover:ring-brand-500/50 transition"
                            />
                            <span 
                                v-if="isConversationOnline(activeConversation)"
                                class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white glow-emerald"
                            ></span>
                        </div>

                        <div>
                            <h2 class="font-bold text-slate-900 text-sm md:text-base flex items-center gap-2 group-hover:text-brand-600 transition">
                                {{ activeConversation.title }}
                                <span v-if="activeConversation.type === 'group'" class="text-[11px] font-semibold bg-brand-50 text-brand-600 border border-brand-200 px-2 py-0.5 rounded-lg">
                                    {{ activeConversation.participants?.length || 0 }} members
                                </span>
                            </h2>

                            <!-- Dynamic Real-time Status / Typing indicator / Last Active -->
                            <div class="text-xs flex items-center gap-1.5 mt-0.5">
                                <span v-if="typingUsers.size > 0" class="text-brand-600 font-bold animate-pulse flex items-center gap-1.5">
                                    <span class="inline-block w-2 h-2 bg-brand-600 rounded-full animate-ping"></span>
                                    {{ Array.from(typingUsers.values()).join(', ') }} typing...
                                </span>
                                <span v-else-if="isConversationOnline(activeConversation)" class="text-emerald-600 font-semibold flex items-center gap-1.5">
                                    <span class="w-2 h-2 bg-emerald-500 rounded-full glow-emerald"></span>
                                    Active now
                                </span>
                                <span v-else class="text-slate-500 font-medium flex items-center gap-1.5">
                                    <span v-if="activeConversation.type === 'direct'">
                                        last seen {{ activeConversation.direct_recipient?.last_active_human || 'recently' }}
                                    </span>
                                    <span v-else>
                                        {{ activeConversation.participants?.length || 0 }} members
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Quick Actions -->
                    <div class="relative flex items-center space-x-1.5 text-slate-500">
                        <button 
                            @click="initiateCall('audio')" 
                            title="Start Voice Call"
                            class="p-2.5 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-all hover:scale-105 active:scale-95 border border-transparent hover:border-brand-200"
                        >
                            <Phone class="w-4 h-4" />
                        </button>
                        <button 
                            @click="initiateCall('video')" 
                            title="Start Video Call"
                            class="p-2.5 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-all hover:scale-105 active:scale-95 border border-transparent hover:border-brand-200"
                        >
                            <Video class="w-4 h-4" />
                        </button>
                        <div class="h-5 w-px bg-slate-200 mx-1"></div>
                        <button 
                            @click="isHeaderMenuOpen = !isHeaderMenuOpen" 
                            title="More options"
                            class="p-2.5 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-all hover:scale-105 active:scale-95 border border-transparent hover:border-brand-200"
                        >
                            <MoreVertical class="w-4 h-4" />
                        </button>

                        <!-- Header Options Dropdown -->
                        <div 
                            v-if="isHeaderMenuOpen" 
                            class="absolute right-0 top-12 w-52 bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 z-50 animate-in fade-in zoom-in-95 duration-150"
                        >
                            <button 
                                @click="openUserProfile(activeConversation.direct_recipient || null); isHeaderMenuOpen = false;"
                                class="w-full px-4 py-2.5 text-left text-sm font-semibold text-slate-700 hover:text-brand-600 hover:bg-brand-50 flex items-center gap-2.5 transition"
                            >
                                <User class="w-4 h-4 text-brand-600" />
                                <span>{{ activeConversation.type === 'group' ? 'Group Details' : 'View Profile' }}</span>
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

                        <!-- Message Bubbles -->
                        <div 
                            v-for="msg in group.messages" 
                            :key="msg.id"
                            :class="[
                                'flex items-end gap-2.5 max-w-xl group',
                                msg.sender_id === currentUser?.id ? 'ml-auto flex-row-reverse' : 'mr-auto flex-row'
                            ]"
                        >
                            <!-- Sender Avatar (for incoming messages) -->
                            <img 
                                v-if="msg.sender_id !== currentUser?.id"
                                :src="msg.sender?.avatar_url || 'https://ui-avatars.com/api/?name=User&background=D91A8D&color=fff'" 
                                alt="Avatar" 
                                class="w-8 h-8 rounded-xl object-cover flex-shrink-0 ring-1 ring-slate-200 shadow-xs mb-1"
                            />

                            <!-- Message Content Bubble -->
                            <div class="space-y-1 max-w-lg">
                                <!-- Sender name in group -->
                                <p 
                                    v-if="activeConversation.type === 'group' && msg.sender_id !== currentUser?.id"
                                    class="text-[11px] font-bold text-brand-600 ml-1"
                                >
                                    {{ msg.sender?.name }}
                                </p>

                                <div 
                                    :class="[
                                        'rounded-2xl px-4 py-2.5 text-sm break-words relative leading-relaxed',
                                        msg.sender_id === currentUser?.id 
                                            ? 'chat-bubble-outgoing text-white' 
                                            : 'chat-bubble-incoming text-slate-800'
                                    ]"
                                >
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
                                        v-if="msg.type === 'system'"
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

                                    <!-- Text Body -->
                                    <div v-else-if="msg.body">
                                        <p 
                                            :class="[
                                                'whitespace-pre-wrap text-[13.5px]',
                                                msg.is_deleted_for_everyone ? 'italic text-slate-400 opacity-90' : ''
                                            ]"
                                        >
                                            {{ msg.body }}
                                        </p>
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
                                            <!-- Blue Tick (Read by recipient) -->
                                            <span v-if="msg.read_at" class="text-sky-300 flex items-center" title="Read">
                                                <CheckCheck class="w-3.5 h-3.5 stroke-[2.5]" />
                                            </span>
                                            <!-- Double Gray Tick (Delivered to recipient / Notification received) -->
                                            <span v-else-if="msg.delivered_at || isConversationOnline(activeConversation)" class="opacity-80 flex items-center text-slate-200" title="Delivered">
                                                <CheckCheck class="w-3.5 h-3.5 stroke-[2]" />
                                            </span>
                                            <!-- Single Gray Tick (Sent to server, awaiting delivery) -->
                                            <span v-else class="opacity-75 flex items-center text-slate-300" title="Sent">
                                                <Check class="w-3.5 h-3.5 stroke-[2]" />
                                            </span>
                                        </template>
                                    </div>

                                    <!-- WhatsApp Style Message Options Trigger (Hover menu) -->
                                    <div 
                                        v-if="!msg.is_deleted_for_everyone"
                                        class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                    >
                                        <button 
                                            @click.stop="activeMessageMenuId = activeMessageMenuId === msg.id ? null : msg.id"
                                            class="p-1 rounded-lg bg-black/20 hover:bg-black/40 text-white/80 hover:text-white transition"
                                            title="Message options"
                                        >
                                            <MoreVertical class="w-3.5 h-3.5" />
                                        </button>

                                        <!-- Dropdown Context Menu -->
                                        <div 
                                            v-if="activeMessageMenuId === msg.id"
                                            class="absolute right-0 top-full mt-1 w-36 bg-white rounded-xl shadow-xl border border-slate-200 py-1.5 z-50 animate-in fade-in zoom-in-95 text-slate-700"
                                            @click.stop
                                        >
                                            <!-- Edit (only for sender & text messages) -->
                                            <button 
                                                v-if="msg.sender_id === currentUser?.id && msg.type === 'text'"
                                                @click="startEditMessage(msg)"
                                                class="w-full px-3 py-1.5 text-xs text-left hover:bg-slate-50 flex items-center gap-2 font-medium"
                                            >
                                                <Edit3 class="w-3.5 h-3.5 text-brand-600" />
                                                <span>Edit</span>
                                            </button>

                                            <!-- Delete -->
                                            <button 
                                                @click="openDeleteModal(msg)"
                                                class="w-full px-3 py-1.5 text-xs text-left hover:bg-rose-50 text-rose-600 flex items-center gap-2 font-medium"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modern Message Composer Bar -->
                <div 
                    v-if="activeConversation"
                    class="p-4 bg-white/95 backdrop-blur-xl border-t border-slate-200/80 flex-shrink-0 shadow-sm"
                >
                    <!-- Active Edit Banner (WhatsApp Style) -->
                    <div 
                        v-if="editingMessage" 
                        class="mb-3 px-3.5 py-2 bg-brand-50/80 rounded-xl border border-brand-200 flex items-center justify-between text-xs animate-in fade-in"
                    >
                        <div class="flex items-center space-x-2.5 truncate">
                            <Edit3 class="w-4 h-4 text-brand-600 flex-shrink-0" />
                            <div class="truncate">
                                <span class="text-brand-700 font-bold">Edit Message:</span>
                                <span class="text-slate-600 ml-1.5 truncate">{{ editingMessage.body }}</span>
                            </div>
                        </div>
                        <button 
                            @click="cancelEditMessage" 
                            class="p-1 hover:bg-brand-100 text-slate-500 hover:text-slate-800 rounded-lg transition"
                            title="Cancel editing"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Attachment Preview Banner -->
                    <div 
                        v-if="selectedFile" 
                        class="mb-3 px-3.5 py-2.5 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between text-xs animate-in fade-in"
                    >
                        <div class="flex items-center space-x-2.5 truncate">
                            <Paperclip class="w-4 h-4 text-brand-600 flex-shrink-0" />
                            <span class="text-slate-800 truncate font-semibold">{{ selectedFile.name }}</span>
                            <span class="text-slate-500 text-[10px]">({{ (selectedFile.size / 1024).toFixed(1) }} KB)</span>
                        </div>
                        <button 
                            @click="clearSelectedFile" 
                            class="p-1 hover:bg-slate-200 text-slate-400 hover:text-slate-700 rounded-lg transition"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Form when editing -->
                    <form v-if="editingMessage" @submit.prevent="saveEditMessage" class="flex items-end space-x-2.5">
                        <div class="flex-1 relative">
                            <textarea 
                                v-model="editInput"
                                @keydown.enter.exact.prevent="saveEditMessage"
                                rows="1"
                                placeholder="Edit your message..."
                                class="w-full glass-input text-slate-800 placeholder-slate-400 text-sm rounded-2xl px-4 py-3 border border-brand-300 focus:outline-none transition resize-none max-h-32 shadow-xs ring-2 ring-brand-500/20"
                            ></textarea>
                        </div>
                        <button 
                            type="button" 
                            @click="cancelEditMessage"
                            class="px-3.5 py-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-2xl border border-slate-200"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="!editInput.trim() || isEditing"
                            class="p-3 bg-gradient-to-tr from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-40 text-white rounded-2xl shadow-md shadow-brand-600/25 transition-all duration-200 hover:scale-105 active:scale-95 disabled:hover:scale-100 flex items-center justify-center flex-shrink-0"
                            title="Save Changes"
                        >
                            <Check class="w-5 h-5" />
                        </button>
                    </form>

                    <!-- Normal Send Form -->
                    <form v-else @submit.prevent="sendMessage" class="flex items-end space-x-2.5">
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
                            class="p-3 text-slate-500 hover:text-brand-600 hover:bg-brand-50 rounded-2xl transition-all flex-shrink-0 border border-slate-200 bg-slate-50 shadow-xs"
                            title="Attach File or Image"
                        >
                            <Paperclip class="w-5 h-5" />
                        </button>

                        <!-- Auto-expanding Textarea Input -->
                        <div class="flex-1 relative">
                            <textarea 
                                v-model="messageInput"
                                @input="handleTypingInput"
                                @keydown.enter.exact.prevent="sendMessage"
                                rows="1" 
                                placeholder="Type your message... (Press Enter to send)"
                                class="w-full glass-input text-slate-800 placeholder-slate-400 text-sm rounded-2xl px-4 py-3 border border-slate-200 focus:outline-none resize-none transition max-h-36 custom-scrollbar shadow-xs"
                            ></textarea>
                        </div>

                        <!-- Send Button with Brand Glow -->
                        <button 
                            type="submit" 
                            :disabled="(!messageInput.trim() && !selectedFile) || isSending"
                            class="p-3 bg-gradient-to-tr from-brand-600 via-brand-700 to-brand-800 hover:from-brand-500 hover:to-brand-700 disabled:opacity-40 text-white rounded-2xl shadow-md shadow-brand-600/25 transition-all duration-200 flex items-center justify-center flex-shrink-0 hover:scale-105 active:scale-95"
                        >
                            <Send class="w-5 h-5" />
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
                            <template v-else-if="selectedProfileUser?.last_active_human">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <p class="text-xs text-slate-500">Last active {{ selectedProfileUser.last_active_human }}</p>
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
    </AuthenticatedLayout>
</template>
