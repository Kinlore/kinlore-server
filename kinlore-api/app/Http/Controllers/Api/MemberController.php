<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMemberRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only member management (docs/reference/api.md section 4).
 * All routes are behind the `can:admin` middleware, so the caller is the one admin.
 */
class MemberController extends Controller
{
    /**
     * Other tables whose rows are deleted with their user (cascade), so they move to the admin.
     */
    private const array REASSIGNED_ON_DELETE = [
        'media' => 'user_id',
        'invitations' => 'invited_by',
    ];

    public function index(): JsonResponse
    {
        $members = User::withCount('uploadedMoments')->orderBy('id')->get();

        return ApiResponse::success($members->map(fn (User $member) => $this->item($member)));
    }

    /**
     * Change a member's role, permissions or password. `role=admin` on another user transfers the admin role.
     */
    public function update(UpdateMemberRequest $request, User $user): JsonResponse
    {
        $admin = $request->user();
        $validated = $request->validated();
        $role = isset($validated['role']) ? Role::from($validated['role']) : null;

        // The admin can only give the role away, so there is always exactly one admin.
        if ($user->is($admin) && $role !== null && $role !== Role::Admin) {
            return ApiResponse::error('You cannot change your own role. Make another member admin instead.', 422);
        }

        DB::transaction(function () use ($admin, $user, $role, $validated): void {
            if ($role !== null && ! $user->is($admin)) {
                $user->role = $role;
                if ($role === Role::Admin) {
                    // A parallel transfer may have won since the admin check; the lock makes the second one wait and see it.
                    $adminIds = User::where('role', Role::Admin)->lockForUpdate()->pluck('id');
                    abort_unless($adminIds->contains($admin->id), 409, 'You are no longer the admin.');
                    $admin->update(['role' => Role::Partner]);
                }
            }

            foreach ($validated['permissions'] ?? [] as $permission => $value) {
                $user->setAttribute("can_{$permission}", $value);
            }

            // A new password signs the member out everywhere; the admin keeps the session that made the change.
            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
                $keep = $user->is($admin) ? $admin->currentAccessToken()?->id : null;
                $user->tokens()->when($keep, fn ($q) => $q->where('id', '!=', $keep))->delete();
            }

            $user->save();
        });

        return ApiResponse::success($this->item($user->loadCount('uploadedMoments')), 'Member updated');
    }

    /**
     * Remove a member. Their kids move to the admin, their moments stay without uploader,
     * their likes, comments and tokens are deleted.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $admin = $request->user();

        if ($user->is($admin)) {
            return ApiResponse::error('You cannot remove yourself.', 422);
        }

        DB::transaction(function () use ($admin, $user): void {
            // kids.user_id cascades on delete, so move the kids first. updated_at makes the apps sync the change.
            DB::table('kids')->where('user_id', $user->id)->update(['user_id' => $admin->id, 'updated_at' => now()]);
            DB::table('moments')->where('user_id', $user->id)->update(['user_id' => null, 'updated_at' => now()]);

            foreach (self::REASSIGNED_ON_DELETE as $table => $column) {
                DB::table($table)->where($column, $user->id)->update([$column => $admin->id]);
            }

            // A query delete fires no Touches, so bump the moments by hand: phones then sync the new counts.
            DB::table('moments')->whereIn('id', $user->interactions()->select('moment_id'))->update(['updated_at' => now()]);
            $user->interactions()->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        return ApiResponse::success(null, 'Member removed');
    }

    /** @return array<string, mixed> */
    private function item(User $member): array
    {
        return $member->toPayload() + ['moments_count' => $member->uploaded_moments_count];
    }
}
