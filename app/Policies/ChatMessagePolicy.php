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
        return true; // All authenticated users can access chat
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ChatMessage $chatMessage): bool
    {
        // Admins and developers can view all messages for moderation
        if ($user->isAdmin() || $user->isDeveloper()) {
            return true;
        }

        // Users can view messages they sent or received
        return $chatMessage->sender_id === $user->id || 
               $chatMessage->receiver_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create messages
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ChatMessage $chatMessage): bool
    {
        // Admins and developers can update any message
        if ($user->isAdmin() || $user->isDeveloper()) {
            return true;
        }

        // System messages can only be updated by admins/developers
        if ($chatMessage->isSystemMessage()) {
            return false;
        }

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
        // Admins and developers can delete any message for moderation
        if ($user->isAdmin() || $user->isDeveloper()) {
            return true;
        }

        // System messages can only be deleted by admins/developers
        if ($chatMessage->isSystemMessage()) {
            return false;
        }

        // Users can delete their own messages within a time limit
        if ($chatMessage->sender_id === $user->id) {
            // Allow deletion within 5 minutes of sending
            return $chatMessage->created_at->diffInMinutes(now()) <= 5;
        }

        return false;
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
        // Users can message their reseller
        if ($user->isUser() && $recipient->id === $user->reseller_id) {
            return true;
        }

        // Resellers can message their assigned users and admins
        if ($user->isReseller()) {
            return $recipient->reseller_id === $user->id || $recipient->isAdmin();
        }

        // Developers and admins can message anyone
        if ($user->isDeveloper() || $user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can moderate chat (block users, etc.).
     */
    public function moderate(User $user): bool
    {
        return $user->isAdmin() || $user->isDeveloper();
    }

    /**
     * Determine whether the user can view chat statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->isDeveloper() || $user->isAdmin();
    }

    /**
     * Determine whether the user can export chat data.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can block another user from messaging them.
     */
    public function blockUser(User $user, User $targetUser): bool
    {
        // Users can block others (except their reseller/admin)
        if (($targetUser->isReseller() || $targetUser->isAdmin()) && $user->isUser()) {
            return false; // Users cannot block their reseller or admins
        }

        return true; // All users can block others
    }
}