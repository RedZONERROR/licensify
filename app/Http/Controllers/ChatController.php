<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    /**
     * Rate limiting configuration
     */
    private const RATE_LIMIT_MESSAGES = 60; // messages per minute
    private const RATE_LIMIT_POLLS = 120; // polls per minute
    private const SLOW_MODE_DELAY = 5; // seconds between messages in slow mode

    /**
     * Display the chat interface
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $conversations = $this->getUserConversations($user);
        
        return view('chat.index', compact('conversations'));
    }

    /**
     * Get conversations for a user based on their role
     */
    private function getUserConversations(User $user): array
    {
        $conversations = [];
        
        switch ($user->role) {
            case Role::USER:
                // Users can chat with their reseller and admins
                if ($user->reseller) {
                    $conversations[] = [
                        'user' => $user->reseller,
                        'unread_count' => $this->getUnreadCount($user->id, $user->reseller->id)
                    ];
                }
                
                // Add admin conversations
                $admins = User::where('role', Role::ADMIN)->get();
                foreach ($admins as $admin) {
                    $conversations[] = [
                        'user' => $admin,
                        'unread_count' => $this->getUnreadCount($user->id, $admin->id)
                    ];
                }
                break;
                
            case Role::RESELLER:
                // Resellers can chat with their managed users and admins
                $managedUsers = $user->managedUsers;
                foreach ($managedUsers as $managedUser) {
                    $conversations[] = [
                        'user' => $managedUser,
                        'unread_count' => $this->getUnreadCount($user->id, $managedUser->id)
                    ];
                }
                
                // Add admin conversations
                $admins = User::where('role', Role::ADMIN)->get();
                foreach ($admins as $admin) {
                    $conversations[] = [
                        'user' => $admin,
                        'unread_count' => $this->getUnreadCount($user->id, $admin->id)
                    ];
                }
                break;
                
            case Role::ADMIN:
            case Role::DEVELOPER:
                // Admins and developers can chat with everyone
                $users = User::where('id', '!=', $user->id)->get();
                foreach ($users as $otherUser) {
                    $conversations[] = [
                        'user' => $otherUser,
                        'unread_count' => $this->getUnreadCount($user->id, $otherUser->id)
                    ];
                }
                break;
        }
        
        return $conversations;
    }

    /**
     * Get unread message count between two users
     */
    private function getUnreadCount(int $userId, int $otherUserId): int
    {
        return ChatMessage::where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get messages between current user and another user
     */
    public function getMessages(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        // Check if users can communicate
        if (!$this->canCommunicate($currentUser, $user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Rate limiting for polling
        $key = 'chat_poll:' . $currentUser->id;
        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_POLLS)) {
            return response()->json(['error' => 'Too many requests'], 429);
        }
        RateLimiter::hit($key, 60);

        $lastMessageId = $request->get('last_message_id', 0);
        
        $messages = ChatMessage::betweenUsers($currentUser->id, $user->id)
            ->where('id', '>', $lastMessageId)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        // Mark messages as read if they were sent to current user
        $unreadMessages = $messages->where('receiver_id', $currentUser->id)->where('read_at', null);
        if ($unreadMessages->count() > 0) {
            ChatMessage::whereIn('id', $unreadMessages->pluck('id'))
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'messages' => $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender ? $message->sender->name : 'System',
                    'body' => $message->getFormattedBody(),
                    'message_type' => $message->message_type,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at->toISOString(),
                    'is_own' => $message->sender_id === Auth::id(),
                ];
            }),
            'last_message_id' => $messages->last()?->id ?? $lastMessageId,
        ]);
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request, User $user): JsonResponse
    {
        $sender = Auth::user();
        $receiver = $user;
        
        // Check if users can communicate
        if (!$this->canCommunicate($sender, $receiver)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Rate limiting for sending messages
        $key = 'chat_send:' . $sender->id;
        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MESSAGES)) {
            return response()->json(['error' => 'Too many messages sent'], 429);
        }

        // Check for slow mode
        if ($this->isSlowModeActive($sender, $receiver)) {
            $lastMessage = ChatMessage::where('sender_id', $sender->id)
                ->where('receiver_id', $receiver->id)
                ->latest()
                ->first();
                
            if ($lastMessage && $lastMessage->created_at->diffInSeconds(now()) < self::SLOW_MODE_DELAY) {
                return response()->json([
                    'error' => 'Slow mode active. Please wait before sending another message.'
                ], 429);
            }
        }

        // Validate message
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
            'message_type' => 'sometimes|in:text,file',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if sender is blocked
        if ($this->isUserBlocked($sender, $receiver)) {
            return response()->json(['error' => 'You are blocked from sending messages to this user'], 403);
        }

        // Create message
        $message = ChatMessage::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => $request->body,
            'message_type' => $request->get('message_type', 'text'),
            'metadata' => $request->get('metadata', []),
        ]);

        RateLimiter::hit($key, 60);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'sender_name' => $sender->name,
                'body' => $message->getFormattedBody(),
                'message_type' => $message->message_type,
                'metadata' => $message->metadata,
                'created_at' => $message->created_at->toISOString(),
                'is_own' => true,
            ]
        ], 201);
    }

    /**
     * Check if two users can communicate based on role hierarchy
     */
    private function canCommunicate(User $user1, User $user2): bool
    {
        // Admins and developers can communicate with everyone
        if ($user1->isAdmin() || $user1->isDeveloper()) {
            return true;
        }

        // Users can communicate with their reseller and admins
        if ($user1->isUser()) {
            return $user2->isAdmin() || 
                   $user2->isDeveloper() || 
                   ($user1->reseller_id && $user1->reseller_id === $user2->id);
        }

        // Resellers can communicate with their managed users and admins
        if ($user1->isReseller()) {
            return $user2->isAdmin() || 
                   $user2->isDeveloper() || 
                   $user2->reseller_id === $user1->id;
        }

        return false;
    }

    /**
     * Check if slow mode is active for a conversation
     */
    private function isSlowModeActive(User $sender, User $receiver): bool
    {
        $key = "slow_mode:{$sender->id}:{$receiver->id}";
        return Cache::has($key);
    }

    /**
     * Enable slow mode for a conversation
     */
    public function enableSlowMode(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        // Only admins and developers can enable slow mode
        if (!$currentUser->isAdmin() && !$currentUser->isDeveloper()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $duration = $request->get('duration', 300); // 5 minutes default
        $key = "slow_mode:{$user->id}:{$currentUser->id}";
        
        Cache::put($key, true, $duration);

        // Send system message
        ChatMessage::createSystemMessage(
            $user->id,
            'Slow mode has been enabled for this conversation.',
            ['duration' => $duration, 'enabled_by' => $currentUser->id]
        );

        return response()->json(['message' => 'Slow mode enabled']);
    }

    /**
     * Disable slow mode for a conversation
     */
    public function disableSlowMode(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        // Only admins and developers can disable slow mode
        if (!$currentUser->isAdmin() && !$currentUser->isDeveloper()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $key = "slow_mode:{$user->id}:{$currentUser->id}";
        Cache::forget($key);

        // Send system message
        ChatMessage::createSystemMessage(
            $user->id,
            'Slow mode has been disabled for this conversation.',
            ['disabled_by' => $currentUser->id]
        );

        return response()->json(['message' => 'Slow mode disabled']);
    }

    /**
     * Check if a user is blocked
     */
    private function isUserBlocked(User $sender, User $receiver): bool
    {
        $key = "blocked:{$sender->id}:{$receiver->id}";
        return Cache::has($key);
    }

    /**
     * Block a user from sending messages
     */
    public function blockUser(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        // Only admins and developers can block users
        if (!$currentUser->isAdmin() && !$currentUser->isDeveloper()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $duration = $request->get('duration', 3600); // 1 hour default
        $key = "blocked:{$user->id}:{$currentUser->id}";
        
        Cache::put($key, true, $duration);

        // Send system message
        ChatMessage::createSystemMessage(
            $user->id,
            'You have been temporarily blocked from sending messages.',
            ['duration' => $duration, 'blocked_by' => $currentUser->id]
        );

        return response()->json(['message' => 'User blocked']);
    }

    /**
     * Unblock a user
     */
    public function unblockUser(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        // Only admins and developers can unblock users
        if (!$currentUser->isAdmin() && !$currentUser->isDeveloper()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $key = "blocked:{$user->id}:{$currentUser->id}";
        Cache::forget($key);

        // Send system message
        ChatMessage::createSystemMessage(
            $user->id,
            'You have been unblocked and can now send messages.',
            ['unblocked_by' => $currentUser->id]
        );

        return response()->json(['message' => 'User unblocked']);
    }

    /**
     * Get conversation history with pagination
     */
    public function getConversationHistory(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        // Check if users can communicate
        if (!$this->canCommunicate($currentUser, $user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $page = $request->get('page', 1);
        $perPage = min($request->get('per_page', 20), 50); // Max 50 messages per page

        $messages = ChatMessage::betweenUsers($currentUser->id, $user->id)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ]
        ]);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();
        
        ChatMessage::where('sender_id', $user->id)
            ->where('receiver_id', $currentUser->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marked as read']);
    }

    /**
     * Get unread message counts for all conversations
     */
    public function getUnreadCounts(): JsonResponse
    {
        $currentUser = Auth::user();
        $conversations = $this->getUserConversations($currentUser);
        
        $unreadCounts = [];
        foreach ($conversations as $conversation) {
            $unreadCounts[$conversation['user']->id] = $conversation['unread_count'];
        }

        return response()->json(['unread_counts' => $unreadCounts]);
    }
}