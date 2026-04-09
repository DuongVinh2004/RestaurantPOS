<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            1 => 'Admin',
            2 => 'Staff',
            3 => 'Customer',
        ] as $roleId => $roleName) {
            DB::table('roles')->updateOrInsert(
                ['role_id' => $roleId],
                ['role_name' => $roleName]
            );
        }
    }
}
