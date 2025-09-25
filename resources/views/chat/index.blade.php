@extends('layouts.app')

@section('title', 'Chat')

@section('content')
<div class="min-h-screen bg-gray-50" x-data="chatApp()" x-init="init()">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden" style="height: calc(100vh - 8rem);">
            <div class="flex h-full">
                <!-- Conversations Sidebar -->
                <div class="w-1/3 border-r border-gray-200 flex flex-col">
                    <!-- Header -->
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Conversations</h2>
                        <p class="text-sm text-gray-600">{{ auth()->user()->role->value }} - {{ auth()->user()->name }}</p>
                    </div>

                    <!-- Search -->
                    <div class="p-4 border-b border-gray-200">
                        <input 
                            type="text" 
                            x-model="searchQuery"
                            @input="filterConversations()"
                            placeholder="Search conversations..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                    </div>

                    <!-- Conversations List -->
                    <div class="flex-1 overflow-y-auto">
                        <template x-for="conversation in filteredConversations" :key="conversation.user.id">
                            <div 
                                @click="selectConversation(conversation.user)"
                                :class="selectedUser && selectedUser.id === conversation.user.id ? 'bg-blue-50 border-r-2 border-blue-500' : 'hover:bg-gray-50'"
                                class="p-4 border-b border-gray-100 cursor-pointer transition-colors duration-150"
                            >
                                <div class="flex items-center space-x-3">
                                    <!-- Avatar -->
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700" x-text="conversation.user.name.charAt(0).toUpperCase()"></span>
                                        </div>
                                    </div>
                                    
                                    <!-- User Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="conversation.user.name"></p>
                                            <span 
                                                x-show="conversation.unread_count > 0"
                                                x-text="conversation.unread_count"
                                                class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-500 rounded-full"
                                            ></span>
                                        </div>
                                        <p class="text-xs text-gray-500 capitalize" x-text="conversation.user.role"></p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Empty State -->
                        <div x-show="filteredConversations.length === 0" class="p-8 text-center text-gray-500">
                            <p>No conversations found</p>
                        </div>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="flex-1 flex flex-col">
                    <!-- Chat Header -->
                    <div x-show="selectedUser" class="p-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-gray-700" x-text="selectedUser ? selectedUser.name.charAt(0).toUpperCase() : ''"></span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900" x-text="selectedUser ? selectedUser.name : ''"></h3>
                                    <p class="text-sm text-gray-500 capitalize" x-text="selectedUser ? selectedUser.role : ''"></p>
                                </div>
                            </div>

                            <!-- Moderation Controls (Admin/Developer only) -->
                            @if(auth()->user()->isAdmin() || auth()->user()->isDeveloper())
                            <div class="flex items-center space-x-2">
                                <button 
                                    @click="toggleSlowMode()"
                                    :class="isSlowModeActive ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'"
                                    class="px-3 py-1 text-xs font-medium rounded-md hover:bg-opacity-80 transition-colors"
                                >
                                    <span x-text="isSlowModeActive ? 'Slow Mode ON' : 'Slow Mode OFF'"></span>
                                </button>
                                <button 
                                    @click="toggleBlockUser()"
                                    :class="isUserBlocked ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'"
                                    class="px-3 py-1 text-xs font-medium rounded-md hover:bg-opacity-80 transition-colors"
                                >
                                    <span x-text="isUserBlocked ? 'Unblock' : 'Block'"></span>
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Messages Area -->
                    <div x-show="selectedUser" class="flex-1 overflow-y-auto p-4 space-y-4" x-ref="messagesContainer">
                        <template x-for="message in messages" :key="message.id">
                            <div :class="message.is_own ? 'flex justify-end' : 'flex justify-start'">
                                <div :class="message.is_own ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-900'" 
                                     class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg">
                                    <!-- System Message -->
                                    <div x-show="message.message_type === 'system'" class="text-center text-sm italic text-gray-600">
                                        <span x-text="message.body"></span>
                                    </div>
                                    
                                    <!-- Regular Message -->
                                    <div x-show="message.message_type !== 'system'">
                                        <div x-show="!message.is_own" class="text-xs font-medium mb-1" x-text="message.sender_name"></div>
                                        <div class="text-sm" x-text="message.body"></div>
                                        <div :class="message.is_own ? 'text-blue-100' : 'text-gray-500'" 
                                             class="text-xs mt-1" 
                                             x-text="formatTime(message.created_at)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Loading indicator -->
                        <div x-show="isLoading" class="flex justify-center py-4">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                        </div>
                    </div>

                    <!-- Message Input -->
                    <div x-show="selectedUser" class="p-4 border-t border-gray-200 bg-white">
                        <form @submit.prevent="sendMessage()" class="flex space-x-2">
                            <input 
                                type="text"
                                x-model="newMessage"
                                :disabled="isUserBlocked || (isSlowModeActive && slowModeTimeLeft > 0)"
                                :placeholder="getInputPlaceholder()"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed"
                                maxlength="2000"
                            >
                            <button 
                                type="submit"
                                :disabled="!newMessage.trim() || isUserBlocked || (isSlowModeActive && slowModeTimeLeft > 0)"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                            >
                                Send
                            </button>
                        </form>
                        
                        <!-- Slow mode countdown -->
                        <div x-show="isSlowModeActive && slowModeTimeLeft > 0" class="mt-2 text-sm text-orange-600">
                            Slow mode active. You can send another message in <span x-text="slowModeTimeLeft"></span> seconds.
                        </div>
                        
                        <!-- Character count -->
                        <div class="mt-1 text-xs text-gray-500 text-right">
                            <span x-text="newMessage.length"></span>/2000
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div x-show="!selectedUser" class="flex-1 flex items-center justify-center text-gray-500">
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.955 8.955 0 01-4.126-.98L3 20l1.98-5.874A8.955 8.955 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No conversation selected</h3>
                            <p class="mt-1 text-sm text-gray-500">Choose a conversation from the sidebar to start chatting.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function chatApp() {
    return {
        conversations: @json($conversations),
        filteredConversations: [],
        selectedUser: null,
        messages: [],
        newMessage: '',
        searchQuery: '',
        lastMessageId: 0,
        isLoading: false,
        pollingInterval: null,
        isSlowModeActive: false,
        isUserBlocked: false,
        slowModeTimeLeft: 0,
        slowModeTimer: null,

        init() {
            this.filteredConversations = this.conversations;
            this.startPolling();
        },

        filterConversations() {
            if (!this.searchQuery.trim()) {
                this.filteredConversations = this.conversations;
                return;
            }

            const query = this.searchQuery.toLowerCase();
            this.filteredConversations = this.conversations.filter(conversation => 
                conversation.user.name.toLowerCase().includes(query) ||
                conversation.user.role.toLowerCase().includes(query)
            );
        },

        selectConversation(user) {
            this.selectedUser = user;
            this.messages = [];
            this.lastMessageId = 0;
            this.loadMessages();
            this.markAsRead();
            
            // Update unread count for this conversation
            const conversation = this.conversations.find(c => c.user.id === user.id);
            if (conversation) {
                conversation.unread_count = 0;
            }
        },

        async loadMessages() {
            if (!this.selectedUser) return;

            this.isLoading = true;
            try {
                const response = await fetch(`/chat/messages/${this.selectedUser.id}?last_message_id=${this.lastMessageId}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.messages = [...this.messages, ...data.messages];
                    this.lastMessageId = data.last_message_id;
                    this.scrollToBottom();
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async sendMessage() {
            if (!this.newMessage.trim() || !this.selectedUser) return;

            const messageText = this.newMessage;
            this.newMessage = '';

            try {
                const response = await fetch(`/chat/send/${this.selectedUser.id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        body: messageText,
                        message_type: 'text'
                    })
                });

                if (response.ok) {
                    const data = await response.json();
                    this.messages.push(data.message);
                    this.lastMessageId = data.message.id;
                    this.scrollToBottom();
                    
                    // Start slow mode countdown if active
                    if (this.isSlowModeActive) {
                        this.startSlowModeCountdown();
                    }
                } else {
                    const errorData = await response.json();
                    alert(errorData.error || 'Failed to send message');
                    this.newMessage = messageText; // Restore message
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Failed to send message');
                this.newMessage = messageText; // Restore message
            }
        },

        async markAsRead() {
            if (!this.selectedUser) return;

            try {
                await fetch(`/chat/mark-read/${this.selectedUser.id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });
            } catch (error) {
                console.error('Error marking messages as read:', error);
            }
        },

        async toggleSlowMode() {
            if (!this.selectedUser) return;

            try {
                const endpoint = this.isSlowModeActive ? 'disable-slow-mode' : 'enable-slow-mode';
                const response = await fetch(`/chat/${endpoint}/${this.selectedUser.id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    this.isSlowModeActive = !this.isSlowModeActive;
                    if (!this.isSlowModeActive) {
                        this.clearSlowModeTimer();
                    }
                }
            } catch (error) {
                console.error('Error toggling slow mode:', error);
            }
        },

        async toggleBlockUser() {
            if (!this.selectedUser) return;

            try {
                const endpoint = this.isUserBlocked ? 'unblock' : 'block';
                const response = await fetch(`/chat/${endpoint}/${this.selectedUser.id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    this.isUserBlocked = !this.isUserBlocked;
                }
            } catch (error) {
                console.error('Error toggling user block:', error);
            }
        },

        startPolling() {
            this.pollingInterval = setInterval(() => {
                if (this.selectedUser) {
                    this.loadMessages();
                }
            }, 5000); // Poll every 5 seconds
        },

        startSlowModeCountdown() {
            this.slowModeTimeLeft = 5; // 5 seconds
            this.slowModeTimer = setInterval(() => {
                this.slowModeTimeLeft--;
                if (this.slowModeTimeLeft <= 0) {
                    this.clearSlowModeTimer();
                }
            }, 1000);
        },

        clearSlowModeTimer() {
            if (this.slowModeTimer) {
                clearInterval(this.slowModeTimer);
                this.slowModeTimer = null;
            }
            this.slowModeTimeLeft = 0;
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.messagesContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffInHours = (now - date) / (1000 * 60 * 60);

            if (diffInHours < 24) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else {
                return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
            }
        },

        getInputPlaceholder() {
            if (this.isUserBlocked) {
                return 'You are blocked from sending messages';
            }
            if (this.isSlowModeActive && this.slowModeTimeLeft > 0) {
                return `Slow mode active. Wait ${this.slowModeTimeLeft}s`;
            }
            return 'Type a message...';
        },

        // Cleanup on component destroy
        destroy() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
            }
            this.clearSlowModeTimer();
        }
    }
}
</script>
@endsection