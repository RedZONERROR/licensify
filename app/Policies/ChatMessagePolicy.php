<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ChatMessage;
use App\Models\User;

class ChatMessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('chat_access');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ChatMessage $chatMessage): bool
    {
        // Users can view messages they sent or received
        return $chatMessage->sender_id === $user->id || 
               $chatMessage->receiver_id === $user->id ||
               $user->hasRole(Role::ADMIN); // Admins can view all messages for moderation
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('chat_access');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ChatMessage $chatMessage): bool
    {
        // Users can only edit their own messages within a time limit
        if ($chatMessage->sender_id !== $user->id) {
            return false;
        }

        // Allow editing within 5 minutes of sending
        return $chatMessage->created_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ChatMessage $chatMessage): bool
    {
        // Users can delete their own messages
        if ($chatMessage->sender_id === $user->id) {
            return true;
        }

        // Admins can delete any message for moderation
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ChatMessage $chatMessage): bool
    {
        // Only admins can restore deleted messages
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ChatMessage $chatMessage): bool
    {
        // Only admins can permanently delete messages
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can send a message to another user.
     */
    public function sendTo(User $user, User $recipient): bool
    {
        // Must have chat access
        if (!$user->hasPermission('chat_access')) {
            return false;
        }

        // Users can message their reseller
        if ($user->hasRole(Role::USER) && $recipient->id === $user->reseller_id) {
            return true;
        }

        // Resellers can message their assigned users and admins
        if ($user->hasRole(Role::RESELLER)) {
            return $recipient->reseller_id === $user->id || $recipient->hasRole(Role::ADMIN);
        }

        // Developers and admins can message anyone
        if ($user->hasPermissionLevel(Role::DEVELOPER)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can moderate chat (block users, etc.).
     */
    public function moderate(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can view chat statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->hasPermissionLevel(Role::DEVELOPER);
    }

    /**
     * Determine whether the user can export chat data.
     */
    public function export(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Determine whether the user can block another user from messaging them.
     */
    public function blockUser(User $user, User $targetUser): bool
    {
        // Users can block others (except their reseller/admin)
        if ($targetUser->hasPermissionLevel(Role::RESELLER) && $user->hasRole(Role::USER)) {
            return false; // Users cannot block their reseller or admins
        }

        return $user->hasPermission('chat_access');
    }
}