<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UatAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            'MS-CG' => 'Mộc Sen Bistro - Cầu Giấy',
            'MS-HK' => 'Mộc Sen Bistro - Hoàn Kiếm',
            'MS-TD' => 'Mộc Sen Bistro - Thảo Điền',
        ];

        // Ensure branches exist
        $branchIds = [];
        foreach ($branches as $code => $name) {
            $branch = DB::table('branches')->where('branch_code', $code)->first();
            if (! $branch) {
                $branchId = DB::table('branches')->insertGetId([
                    'branch_code' => $code,
                    'branch_name' => $name,
                    'is_active' => 1,
                    'is_default' => $code === 'MAIN' ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'row_version' => 1,
                ]);
                $branchIds[$code] = $branchId;
            } else {
                $branchIds[$code] = $branch->branch_id;
            }
        }

        $passwordHash = Hash::make('UatDemo!123');

        $users = [
            // Staff
            ['username' => 'uat.staff.ms-cg', 'branch_code' => 'MS-CG', 'role_id' => 2, 'full_name' => 'Staff MS-CG'],
            ['username' => 'uat.staff.ms-hk', 'branch_code' => 'MS-HK', 'role_id' => 2, 'full_name' => 'Staff MS-HK'],
            ['username' => 'uat.staff.ms-td', 'branch_code' => 'MS-TD', 'role_id' => 2, 'full_name' => 'Staff MS-TD'],

            // Cashier
            ['username' => 'uat.cashier.ms-cg', 'branch_code' => 'MS-CG', 'role_id' => 6, 'full_name' => 'Cashier MS-CG'],
            ['username' => 'uat.cashier.ms-hk', 'branch_code' => 'MS-HK', 'role_id' => 6, 'full_name' => 'Cashier MS-HK'],
            ['username' => 'uat.cashier.ms-td', 'branch_code' => 'MS-TD', 'role_id' => 6, 'full_name' => 'Cashier MS-TD'],

            // Kitchen
            ['username' => 'uat.kitchen.ms-cg', 'branch_code' => 'MS-CG', 'role_id' => 7, 'full_name' => 'Kitchen MS-CG'],
            ['username' => 'uat.kitchen.ms-hk', 'branch_code' => 'MS-HK', 'role_id' => 7, 'full_name' => 'Kitchen MS-HK'],
            ['username' => 'uat.kitchen.ms-td', 'branch_code' => 'MS-TD', 'role_id' => 7, 'full_name' => 'Kitchen MS-TD'],
        ];

        foreach ($users as $userData) {
            $user = DB::table('users')->where('username', $userData['username'])->first();
            if (! $user) {
                $userId = DB::table('users')->insertGetId([
                    'username' => $userData['username'],
                    'password_hash' => $passwordHash,
                    'full_name' => $userData['full_name'],
                    'role_id' => $userData['role_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                    'row_version' => 1,
                ]);
            } else {
                $userId = $user->user_id;
                DB::table('users')->where('user_id', $userId)->update([
                    'password_hash' => $passwordHash,
                    'role_id' => $userData['role_id'],
                ]);
            }

            $branchId = $branchIds[$userData['branch_code']];
            
            $assignment = DB::table('staff_branch_assignments')
                ->where('user_id', $userId)
                ->where('branch_id', $branchId)
                ->first();
                
            if (! $assignment) {
                DB::table('staff_branch_assignments')->insert([
                    'user_id' => $userId,
                    'branch_id' => $branchId,
                    'is_primary' => 1,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
