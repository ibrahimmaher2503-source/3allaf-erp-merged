<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class SaveUserAction
{
    /** @param array<string, mixed> $data @param array<int, int> $roleIds @param array<int, int> $branchIds @param array<int, int> $storeIds */
    public function execute(array $data, array $roleIds, array $branchIds, array $storeIds, ?User $user = null): User
    {
        Gate::authorize($user === null ? 'users_roles_permissions.create' : 'users_roles_permissions.edit');

        $status = (string) ($data['status'] ?? 'active');
        if (! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => __('The user status is invalid.')]);
        }

        try {
            return DB::transaction(function () use ($data, $roleIds, $branchIds, $storeIds, $user, $status): User {
                $attributes = [
                    'name' => trim((string) $data['name']),
                    'email' => strtolower(trim((string) $data['email'])),
                    'status' => $status,
                ];
                if (filled($data['password'] ?? null)) {
                    $attributes['password'] = Hash::make((string) $data['password']);
                }

                if ($user === null) {
                    $saved = User::query()->create($attributes);
                    $event = 'create_user';
                    $before = null;
                } else {
                    $saved = User::query()->lockForUpdate()->findOrFail($user->id);
                    $before = $saved->only(['name', 'email', 'status']);
                    $saved->update($attributes);
                    $event = 'update_user';
                }

                app(SaveUserAuthorizationAction::class)->execute($saved, $roleIds, $branchIds, $storeIds, $status);

                app(RecordAuditEvent::class)->execute(
                    category: 'authorization',
                    event: $event,
                    source: $saved,
                    before: $before,
                    after: $saved->fresh()->only(['name', 'email', 'status']),
                );

                return $saved->fresh();
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'users_email_unique')) {
                throw ValidationException::withMessages(['email' => __('This email address is already in use.')]);
            }

            throw $exception;
        }
    }
}
