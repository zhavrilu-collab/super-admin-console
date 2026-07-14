<?php

namespace App\Services\Admin;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SuperAdminUserService
{
    /**
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::query()
            ->where('is_super_admin', true)
            ->orderBy('name')
            ->get();
    }

    public function create(User $actor, string $name, string $email, string $password): User
    {
        $this->assertEmailAvailable($email);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->log($actor, AuditAction::SuperAdminCreated, $user);

        return $user;
    }

    public function update(User $actor, User $target, string $name, string $email, ?string $password = null): User
    {
        $this->assertSuperAdmin($target);

        if ($email !== $target->email) {
            $this->assertEmailAvailable($email, $target->id);
        }

        $target->fill([
            'name' => $name,
            'email' => $email,
        ]);

        if (is_string($password) && $password !== '') {
            $target->password = Hash::make($password);
        }

        $target->save();

        $this->log($actor, AuditAction::SuperAdminUpdated, $target);

        return $target->fresh();
    }

    public function delete(User $actor, User $target): void
    {
        $this->assertSuperAdmin($target);
        $this->assertCanDelete($actor, $target);

        $this->log($actor, AuditAction::SuperAdminDeleted, $target);

        $target->delete();
    }

    public function assertSuperAdmin(User $user): void
    {
        if (! $user->isSuperAdmin()) {
            abort(404);
        }
    }

    private function assertCanDelete(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'super_admin' => ['Ne možete obrisati vlastiti račun.'],
            ]);
        }

        $remaining = User::query()->where('is_super_admin', true)->count();

        if ($remaining <= 1) {
            throw ValidationException::withMessages([
                'super_admin' => ['Mora postojati barem jedan super-admin.'],
            ]);
        }
    }

    private function assertEmailAvailable(string $email, ?int $exceptUserId = null): void
    {
        $exists = User::query()
            ->where('email', $email)
            ->when($exceptUserId !== null, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => ['E-mail adresa je već u upotrebi.'],
            ]);
        }
    }

    private function log(User $actor, AuditAction $action, User $target): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'application_id' => null,
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $target->id,
            'properties' => [
                'email' => $target->email,
                'name' => $target->name,
            ],
        ]);
    }
}
