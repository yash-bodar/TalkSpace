<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('chat.index') : redirect()->route('login');
});

Route::get('/dashboard', function () {
    return redirect()->route('chat.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Chat Routes - YB - 27-08-2026 Rate limited & secured
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat', [ChatController::class, 'storeConversation'])->name('chat.store');
    Route::post('/chat/{conversation}/messages', [ChatController::class, 'storeMessage'])->middleware('throttle:60,1')->name('chat.messages.store');
    Route::post('/chat/{conversation}/read', [ChatController::class, 'markAsRead'])->name('chat.read');
    Route::post('/chat/{conversation}/delivered', [ChatController::class, 'markAsDelivered'])->name('chat.delivered');
    Route::post('/chat/{conversation}/typing', [ChatController::class, 'typing'])->middleware('throttle:60,1')->name('chat.typing');
    Route::post('/chat/{conversation}/call/signal', [ChatController::class, 'signalCall'])->name('chat.call.signal');
    Route::post('/chat/{conversation}/call/log', [ChatController::class, 'logCall'])->name('chat.call.log');
    Route::post('/chat/heartbeat', [ChatController::class, 'heartbeat'])->middleware('throttle:30,1')->name('chat.heartbeat');
    Route::patch('/chat/messages/{message}', [ChatController::class, 'updateMessage'])->name('chat.messages.update');
    Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.delete');
    Route::post('/chat/messages/{message}/reactions', [ChatController::class, 'toggleReaction'])->name('chat.messages.reactions.toggle');
    Route::post('/chat/messages/{message}/pin', [ChatController::class, 'togglePinMessage'])->name('chat.messages.pin.toggle');
    Route::get('/chat/messages/{message}/info', [ChatController::class, 'getMessageDeliveryInfo'])->name('chat.messages.info');
    Route::get('/chat-users/search', [ChatController::class, 'searchUsers'])->middleware('throttle:30,1')->name('chat.users.search');

    // Group Management Routes - YB - 26-08-2026
    Route::post('/chat/{conversation}/members', [ChatController::class, 'addMembers'])->name('chat.groups.members.add');
    Route::delete('/chat/{conversation}/members/{user}', [ChatController::class, 'removeMember'])->name('chat.groups.members.remove');
    Route::patch('/chat/{conversation}/members/{user}/role', [ChatController::class, 'updateMemberRole'])->name('chat.groups.members.role');
    Route::post('/chat/{conversation}/leave', [ChatController::class, 'leaveGroup'])->name('chat.groups.leave');
    Route::post('/chat/{conversation}/settings', [ChatController::class, 'updateGroupSettings'])->name('chat.groups.settings');
    Route::post('/chat/{conversation}/invite-code/reset', [ChatController::class, 'resetInviteCode'])->name('chat.groups.invite.reset');
    Route::get('/chat/join/{invite_code}', [ChatController::class, 'joinGroup'])->name('chat.join');
    Route::post('/chat/{conversation}/join-requests/{request}/approve', [ChatController::class, 'approveJoinRequest'])->name('chat.groups.requests.approve');
    Route::post('/chat/{conversation}/join-requests/{request}/reject', [ChatController::class, 'rejectJoinRequest'])->name('chat.groups.requests.reject');

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
