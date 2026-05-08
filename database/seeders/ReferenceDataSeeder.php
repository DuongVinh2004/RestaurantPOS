<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            1 => 'Admin',
            2 => 'Staff',
            3 => 'Customer',
            4 => 'Server',
            5 => 'Waiter',
            6 => 'Cashier',
            7 => 'Kitchen',
            8 => 'Manager',
        ];

        DB::transaction(function () use ($roles): void {
            foreach ($roles as $roleId => $roleName) {
                $this->upsertCanonicalRole((int) $roleId, $roleName);
            }
        });
    }

    private function upsertCanonicalRole(int $roleId, string $roleName): void
    {
        $existingByName = DB::table('roles')
            ->where('role_name', $roleName)
            ->first(['role_id']);

        if ($existingByName !== null && (int) $existingByName->role_id !== $roleId) {
            $this->moveExistingRoleToCanonicalId((int) $existingByName->role_id, $roleId, $roleName);

            return;
        }

        DB::table('roles')->updateOrInsert(
            ['role_id' => $roleId],
            ['role_name' => $roleName]
        );
    }

    private function moveExistingRoleToCanonicalId(int $existingRoleId, int $canonicalRoleId, string $roleName): void
    {
        $canonicalRoleExists = DB::table('roles')
            ->where('role_id', $canonicalRoleId)
            ->exists();

        if ($canonicalRoleExists) {
            $this->moveUserReferencesToCanonicalRole($existingRoleId, $canonicalRoleId);

            DB::table('roles')
                ->where('role_id', $existingRoleId)
                ->delete();

            DB::table('roles')
                ->where('role_id', $canonicalRoleId)
                ->update(['role_name' => $roleName]);

            return;
        }

        DB::table('roles')
            ->where('role_id', $existingRoleId)
            ->update(['role_name' => $this->legacyRoleName($roleName, $existingRoleId)]);

        DB::table('roles')->insert([
            'role_id' => $canonicalRoleId,
            'role_name' => $roleName,
        ]);

        $this->moveUserReferencesToCanonicalRole($existingRoleId, $canonicalRoleId);

        DB::table('roles')
            ->where('role_id', $existingRoleId)
            ->delete();
    }

    private function moveUserReferencesToCanonicalRole(int $existingRoleId, int $canonicalRoleId): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role_id')) {
            return;
        }

        DB::table('users')
            ->where('role_id', $existingRoleId)
            ->update(['role_id' => $canonicalRoleId]);
    }

    private function legacyRoleName(string $roleName, int $existingRoleId): string
    {
        return mb_substr(sprintf('%s legacy %d', $roleName, $existingRoleId), 0, 50);
    }
}
