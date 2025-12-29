<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\Server;
use App\Models\ServerMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvitationService
{
    /**
     * Create a new invitation
     */
    public function createInvitation(Server $server, User $inviter, array $data): Invitation
    {
        // Enforce max pending invitations per server limit if needed
        // $pendingCount = Invitation::where('server_id', $server->id)->where('accepted_at', null)->where('expires_at', '>', now())->count();
        // if ($pendingCount >= config('invitation.max_pending_per_server')) ...

        $days = $data['expires_in_days'] ?? config('invitation.default_expiration_days', 7);
        $expiresAt = $days ? Carbon::now()->addDays($days) : null;
        
        return Invitation::create([
            'server_id' => $server->id,
            'token' => Invitation::generateToken(),
            'email' => $data['email'] ?? null,
            'role' => $data['role'] ?? 'viewer',
            'invited_by' => $inviter->id,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Get pending invitations for a server
     */
    public function getPendingInvitations(Server $server): Collection
    {
        return $server->invitations()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->whereNull('accepted_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate an invitation token
     * Returns ['valid' => bool, 'reason' => string|null, 'invitation' => Invitation|null]
     */
    public function validateToken(string $token): array
    {
        $invitation = Invitation::where('token', $token)
            ->with(['server.owner', 'inviter'])
            ->first();

        if (!$invitation) {
            return ['valid' => false, 'reason' => 'invalid_token', 'invitation' => null];
        }

        if ($invitation->trashed()) {
             return ['valid' => false, 'reason' => 'revoked', 'invitation' => null];
        }

        if ($invitation->isAccepted()) {
            return ['valid' => false, 'reason' => 'already_used', 'invitation' => null];
        }

        if ($invitation->isExpired()) {
            return ['valid' => false, 'reason' => 'expired', 'invitation' => null];
        }

        return ['valid' => true, 'reason' => null, 'invitation' => $invitation];
    }

    /**
     * Accept an invitation
     */
    public function acceptInvitation(string $token, User $user): ServerMember
    {
        $validation = $this->validateToken($token);

        if (!$validation['valid']) {
            throw new \Exception("Invalid invitation: " . $validation['reason']);
        }

        $invitation = $validation['invitation'];

        return DB::transaction(function () use ($invitation, $user) {
            // Check if already a member
            $existingMember = ServerMember::where('user_id', $user->id)
                ->where('server_id', $invitation->server_id)
                ->first();

            if ($existingMember) {
                // Already a member, maybe update role? For now just return existing.
                // Or we could consider this "accepting" logic successful if they are just re-joining.
                // But usually we mark the invite as used.
                $invitation->update(['accepted_at' => Carbon::now()]);
                return $existingMember;
            }

            // Create membership
            $member = ServerMember::create([
                'user_id' => $user->id,
                'server_id' => $invitation->server_id,
                'role' => $invitation->role,
                'invited_by' => $invitation->invited_by,
            ]);

            // Mark invitation as accepted
            $invitation->update(['accepted_at' => Carbon::now()]);
            
            // Delete invitation (soft delete) to clean up? Or just leave marked as accepted.
            // Requirement said "Soft delete or mark invitation as used".
            // Since we have accepted_at, we can keep it for history or soft delete.
            // Soft deleting might be cleaner if we filter by "active" usually.
            $invitation->delete();

            return $member;
        });
    }

    /**
     * Revoke an invitation
     */
    public function revokeInvitation(Invitation $invitation): bool
    {
        return $invitation->delete();
    }
}
