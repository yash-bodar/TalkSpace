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
    // Chat Routes
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat', [ChatController::class, 'storeConversation'])->name('chat.store');
    Route::post('/chat/{conversation}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::post('/chat/{conversation}/read', [ChatController::class, 'markAsRead'])->name('chat.read');
    Route::post('/chat/{conversation}/delivered', [ChatController::class, 'markAsDelivered'])->name('chat.delivered');
    Route::post('/chat/{conversation}/typing', [ChatController::class, 'typing'])->name('chat.typing');
    Route::post('/chat/{conversation}/call/signal', [ChatController::class, 'signalCall'])->name('chat.call.signal');
    Route::post('/chat/{conversation}/call/log', [ChatController::class, 'logCall'])->name('chat.call.log');
    Route::post('/chat/heartbeat', [ChatController::class, 'heartbeat'])->name('chat.heartbeat');
    Route::patch('/chat/messages/{message}', [ChatController::class, 'updateMessage'])->name('chat.messages.update');
    Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.delete');
    Route::get('/chat-users/search', [ChatController::class, 'searchUsers'])->name('chat.users.search');

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

