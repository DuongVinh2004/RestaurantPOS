<?php

namespace App\Platform\QualityAssurance;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Platform\Release\Services\SiteBootstrapService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UatPackService
{
    private SiteBootstrapService $bootstrapService;

    public function __construct(SiteBootstrapService $bootstrapService)
    {
        $this->bootstrapService = $bootstrapService;
    }

    public function bootstrap(array $payload): array
    {
        // 1. Run canonical site bootstrap
        $site = $this->bootstrapService->bootstrap([
            'branch_code' => 'UAT-01',
            'branch_name' => 'UAT Branch',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'currency' => 'VND',
            'zones' => 'Main',
            'tables_per_zone' => 5,
            'admin_username' => 'admin_uat@example.com',
            'admin_name' => 'UAT Admin',
            'staff_username' => 'staff_uat@example.com',
            'staff_name' => 'UAT Staff',
            'skip_staff_key' => false,
            'rotate_staff_key' => true,
            'staff_key_label' => 'UAT Staff Key',
            'staff_key_ttl_days' => 1,
        ]);

        $branchId = DB::table('branches')->where('branch_code', 'UAT-01')->value('branch_id') ?? 1;
        $adminUserId = DB::table('users')->where('username', 'admin_uat@example.com')->value('user_id');
        $staffKey = $site['staff_api_key']['plaintext_key'] ?? '';

        // Issue Admin API Key
        $adminKeyPlain = 'sk_test_admin_' . Str::random(32);
        if ($adminUserId) {
            DB::table('staff_api_keys')->insert([
                'user_id' => $adminUserId,
                'label' => 'UAT Admin Key',
                'key_hash' => hash('sha256', $adminKeyPlain),
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Setup Customer User
        $customerEmail = 'customer_uat_' . time() . '@example.com';
        $customerPassword = 'password';
        $customerRoleId = DB::table('roles')->where('role_name', 'Customer')->value('role_id') ?? DB::table('roles')->insertGetId(['role_name' => 'Customer', 'is_staff' => 0, 'created_at' => now(), 'updated_at' => now()]);
        
        $customerId = DB::table('users')->insertGetId([
            'username' => $customerEmail,
            'full_name' => 'UAT Customer',
            'email' => $customerEmail,
            'phone' => '0901234567',
            'password_hash' => Hash::make($customerPassword),
            'is_deleted' => 0,
            'role_id' => $customerRoleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Setup Tables & Menu (from SiteBootstrap)
        $tableId = DB::table('restaurant_tables')->where('branch_id', $branchId)->value('table_id') ?? 1;
        $menuItem = DB::table('menu_items')->where('is_available', true)->first();

        // 4. Create UAT Reservations
        $reservationIds = [];
        $scenarios = [
            'deposit_pending' => ['status' => 'Reserved', 'deposit_status' => 'Pending', 'deposit_required_amount' => 100000],
            'dine_in_checkin' => ['status' => 'Confirmed', 'deposit_status' => 'Paid', 'deposit_required_amount' => 100000],
            'benefits_pending' => ['status' => 'Confirmed', 'deposit_status' => 'Paid', 'deposit_required_amount' => 100000],
            'refund_partial_ready' => ['status' => 'Confirmed', 'deposit_status' => 'Paid', 'deposit_required_amount' => 100000],
            'refund_cancel_ready' => ['status' => 'Cancelled', 'deposit_status' => 'Paid', 'deposit_required_amount' => 100000],
        ];

        $now = now();
        $dayOffset = 1;
        foreach ($scenarios as $key => $data) {
            $reservationId = DB::table('reservations')->insertGetId([
                'user_id' => $customerId,
                'guest_name' => 'UAT Customer',
                'branch_id' => $branchId,
                'reservation_code' => strtoupper('UAT' . Str::random(5)),
                'start_time' => $now->copy()->addDays($dayOffset)->setHour(19)->setMinute(0)->format('Y-m-d H:i:s'),
                'end_time' => $now->copy()->addDays($dayOffset)->setHour(21)->setMinute(0)->format('Y-m-d H:i:s'),
                'guest_count' => 2,
                'status' => $data['status'],
                'checked_in_at' => $data['status'] === 'Reserved' || $key === 'dine_in_checkin' ? $now : null,
                'cancelled_at' => $data['status'] === 'Cancelled' ? $now : null,
                'deposit_required_amount' => $data['deposit_required_amount'],
                'deposit_paid_amount' => $data['deposit_status'] === 'Paid' ? $data['deposit_required_amount'] : 0,
                'deposit_status' => $data['deposit_status'],
                'created_at' => $now,
                'updated_at' => $now,
                'row_version' => 1,
            ]);

            DB::table('reservation_tables')->insert([
                'reservation_id' => $reservationId,
                'table_id' => $tableId,
            ]);

            $reservationIds[$key] = [
                'reservation_id' => $reservationId,
                'row_version' => 1,
            ];
            
            $dayOffset++;
        }

        // Waitlist
        $waitingId = DB::table('waiting_list')->insertGetId([
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'guest_name' => 'UAT Wait',
            'guest_count' => 2,
            'status' => 'Waiting',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Conversation
        $conversationId = Str::uuid()->toString();
        DB::table('conversations')->insert([
            'conversation_id' => $conversationId,
            'branch_id' => $branchId,
            'user_id' => $customerId,
            'status' => 'Open',
            'created_at' => $now,
        ]);

        // 5. Build Manifest JSON
        $manifest = [
            'pack' => [
                'generated_at_utc' => $now->toIso8601String(),
                'base_url' => $payload['base_url'],
                'schema_version' => '1',
            ],
            'branch' => [
                'branch_id' => $branchId,
                'branch_code' => $site['branch']['branch_code'],
                'branch_name' => $site['branch']['branch_name'],
                'currency' => 'VND',
            ],
            'auth' => [
                'customer_primary' => [
                    'username' => $customerEmail,
                    'password' => $customerPassword,
                ],
                'staff' => [
                    'username' => $site['users']['staff']['username'],
                    'password' => 'password',
                    'api_key' => $staffKey,
                ],
                'admin' => [
                    'username' => $site['users']['admin']['username'],
                    'password' => 'password',
                    'api_key' => $adminKeyPlain,
                ],
            ],
            'reservations' => $reservationIds,
            'waiting_list' => [
                'seeded_waiting_entry' => ['waiting_id' => $waitingId]
            ],
            'scenarios' => [
                'availability_hold_reservation' => [
                    'branch_id' => $branchId,
                    'from_utc' => $now->copy()->addDays(10)->setHour(19)->setMinute(0)->toIso8601String(),
                    'to_utc' => $now->copy()->addDays(10)->setHour(21)->setMinute(0)->toIso8601String(),
                    'guest_count' => 2,
                    'preferred_table_ids' => [$tableId],
                    'session_id' => Str::uuid()->toString(),
                ],
                'deposit_self_pay' => [
                    'reservation_id' => $reservationIds['deposit_pending']['reservation_id'],
                    'payment_amount' => 100000,
                    'provider_code' => 'simulated',
                ],
                'dine_in_checkout' => [
                    'reservation_id' => $reservationIds['dine_in_checkin']['reservation_id'],
                    'table_id' => $tableId,
                    'menu_item_ids' => $menuItem ? [$menuItem->item_id] : [],
                ],
                'refund_partial' => [
                    'reservation_id' => $reservationIds['refund_partial_ready']['reservation_id'],
                    'refund_scope' => 'deposit',
                    'refund_amount' => 50000,
                ],
                'refund_cancel' => [
                    'reservation_id' => $reservationIds['refund_cancel_ready']['reservation_id'],
                    'refund_scope' => 'deposit',
                ],
                'waiting_list_lifecycle' => [
                    'branch_id' => $branchId,
                    'customer_user_id' => $customerId,
                    'table_id' => $tableId,
                ],
                'benefits' => [
                    'reservation_id' => $reservationIds['benefits_pending']['reservation_id'],
                    'user_voucher_id' => 1,
                    'loyalty_points' => 100,
                ],
                'conversation_inbox' => [
                    'branch_id' => $branchId,
                    'conversation_id' => $conversationId,
                ],
            ],
        ];

        // 6. Write manifest
        $manifestPath = $payload['manifest_path'];
        $fullPath = base_path($manifestPath);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 7. Return Result
        return [
            'manifest_path' => $manifestPath,
            'summary' => [
                'branch' => [
                    'branch_code' => $site['branch']['branch_code'],
                    'branch_name' => $site['branch']['branch_name'],
                ],
                'users' => [
                    ['username' => $customerEmail, 'role_name' => 'Customer'],
                    ['username' => $site['users']['staff']['username'], 'role_name' => 'Staff'],
                    ['username' => $site['users']['admin']['username'], 'role_name' => 'Admin'],
                ],
                'supported_scenarios' => array_keys($manifest['scenarios']),
            ],
        ];
    }

    public function reset(): array
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        try {
            // Simple destructive cleanup for UAT only
            DB::table('users')->where('username', 'like', 'customer_uat_%')->delete();
            DB::table('users')->where('username', 'like', '%_uat@example.com')->delete();
            
            $branchId = DB::table('branches')->where('branch_code', 'UAT-01')->value('branch_id');
            if ($branchId) {
                DB::table('conversations')->where('branch_id', $branchId)->delete();
                DB::table('waiting_list')->where('branch_id', $branchId)->delete();
                DB::table('reservation_tables')->whereIn('reservation_id', function ($query) use ($branchId) {
                    $query->select('reservation_id')->from('reservations')->where('branch_id', $branchId);
                })->delete();
                DB::table('reservations')->where('branch_id', $branchId)->delete();
                DB::table('restaurant_tables')->where('branch_id', $branchId)->delete();
                DB::table('branches')->where('branch_id', $branchId)->delete();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
        
        $manifestPath = base_path('storage/app/uat/scenario-pack.json');
        if (file_exists($manifestPath)) {
            unlink($manifestPath);
        }

        return ['reset' => true];
    }
}
