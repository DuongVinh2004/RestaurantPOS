<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevCoreSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::transaction(function () use ($now): void {
            foreach (['Admin', 'Staff', 'Customer'] as $name) {
                DB::table('roles')->updateOrInsert(
                    ['role_name' => $name],
                    ['role_name' => $name]
                );
            }

            $roleAdmin = (int) DB::table('roles')->where('role_name', 'Admin')->value('role_id');
            $roleStaff = (int) DB::table('roles')->where('role_name', 'Staff')->value('role_id');
            $roleCustomer = (int) DB::table('roles')->where('role_name', 'Customer')->value('role_id');

            DB::table('users')->updateOrInsert(
                ['username' => 'admin'],
                [
                    'password_hash' => Hash::make('password'),
                    'full_name' => 'Admin Dev',
                    'email' => 'admin@example.local',
                    'phone' => '0900000001',
                    'role_id' => $roleAdmin,
                    'language_pref' => 'vn',
                    'is_deleted' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'row_version' => 1,
                ]
            );

            DB::table('users')->updateOrInsert(
                ['username' => 'staff1'],
                [
                    'password_hash' => Hash::make('password'),
                    'full_name' => 'Nhân viên Dev',
                    'email' => 'staff1@example.local',
                    'phone' => '0900000002',
                    'role_id' => $roleStaff,
                    'language_pref' => 'vn',
                    'is_deleted' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'row_version' => 1,
                ]
            );

            DB::table('users')->updateOrInsert(
                ['username' => 'customer1'],
                [
                    'password_hash' => Hash::make('password'),
                    'full_name' => 'Khách hàng Dev',
                    'email' => 'customer1@example.local',
                    'phone' => '0900000003',
                    'role_id' => $roleCustomer,
                    'language_pref' => 'vn',
                    'is_deleted' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'row_version' => 1,
                ]
            );

            $staffId = (int) DB::table('users')->where('username', 'staff1')->value('user_id');
            $customerId = (int) DB::table('users')->where('username', 'customer1')->value('user_id');

            foreach ([
                'Món chính' => ['description' => 'Các món chính', 'sort_order' => 1],
                'Đồ uống' => ['description' => 'Thức uống', 'sort_order' => 2],
            ] as $name => $payload) {
                DB::table('menu_categories')->updateOrInsert(
                    ['name' => $name],
                    [
                        'description' => $payload['description'],
                        'sort_order' => $payload['sort_order'],
                        'is_deleted' => 0,
                    ]
                );
            }

            $catMain = (int) DB::table('menu_categories')->where('name', 'Món chính')->value('category_id');
            $catDrink = (int) DB::table('menu_categories')->where('name', 'Đồ uống')->value('category_id');

            DB::table('menu_items')->updateOrInsert(
                ['code' => 'FOOD-001'],
                [
                    'category_id' => $catMain,
                    'name' => 'Bò lúc lắc',
                    'description' => 'Bò xào sốt tiêu đen, dùng kèm rau và khoai chiên.',
                    'img_url' => null,
                    'is_available' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            DB::table('menu_items')->updateOrInsert(
                ['code' => 'DRINK-001'],
                [
                    'category_id' => $catDrink,
                    'name' => 'Trà đào',
                    'description' => 'Trà đào mát lạnh với miếng đào ngâm.',
                    'img_url' => null,
                    'is_available' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $itemFood = (int) DB::table('menu_items')->where('code', 'FOOD-001')->value('item_id');
            $itemDrink = (int) DB::table('menu_items')->where('code', 'DRINK-001')->value('item_id');

            DB::table('menu_item_prices')->where('item_id', $itemFood)->delete();
            DB::table('menu_item_prices')->where('item_id', $itemDrink)->delete();

            DB::table('menu_item_prices')->insert([
                [
                    'item_id' => $itemFood,
                    'price' => '120000.00',
                    'currency' => 'VND',
                    'effective_from' => $now->copy()->subDay(),
                    'effective_to' => null,
                ],
                [
                    'item_id' => $itemDrink,
                    'price' => '45000.00',
                    'currency' => 'VND',
                    'effective_from' => $now->copy()->subDay(),
                    'effective_to' => null,
                ],
            ]);

            DB::table('table_templates')->updateOrInsert(
                ['template_code' => 'TBL-2'],
                ['seats' => 2, 'description' => 'Bàn 2 chỗ']
            );
            DB::table('table_templates')->updateOrInsert(
                ['template_code' => 'TBL-4'],
                ['seats' => 4, 'description' => 'Bàn 4 chỗ']
            );

            $tpl2 = (int) DB::table('table_templates')->where('template_code', 'TBL-2')->value('template_id');

            for ($i = 1; $i <= 5; $i++) {
                DB::table('restaurant_tables')->updateOrInsert(
                    ['table_code' => sprintf('A%02d', $i)],
                    [
                        'template_id' => $tpl2,
                        'zone' => 'A',
                        'pos_x' => $i * 10,
                        'pos_y' => 10,
                        'status' => 'Available',
                        'description' => 'Bàn khu A',
                        'created_at' => $now,
                        'updated_at' => $now,
                        'is_deleted' => 0,
                        'row_version' => 1,
                        'price' => null,
                    ]
                );
            }

            $tableId = (int) DB::table('restaurant_tables')->where('table_code', 'A01')->value('table_id');

            $reservationCode = 'RSV-DEV-001';
            $existingReservationId = DB::table('reservations')->where('reservation_code', $reservationCode)->value('reservation_id');
            if ($existingReservationId) {
                DB::table('reservations')->where('reservation_id', (int) $existingReservationId)->delete();
            }

            $start = $now->copy()->setTime(19, 0, 0);
            $end = $now->copy()->setTime(21, 0, 0);

            $reservationId = (int) DB::table('reservations')->insertGetId([
                'user_id' => $customerId,
                'reservation_code' => $reservationCode,
                'reserved_at' => $now,
                'start_time' => $start,
                'end_time' => $end,
                'guest_count' => 2,
                'status' => 'Confirmed',
                'notes' => 'Đặt bàn mẫu cho môi trường dev',
                'created_at' => $now,
                'updated_at' => $now,
                'row_version' => 1,
            ]);

            DB::table('reservation_tables')->updateOrInsert(
                ['reservation_id' => $reservationId, 'table_id' => $tableId],
                ['reservation_id' => $reservationId, 'table_id' => $tableId]
            );

            $orderId = (int) DB::table('reservation_orders')->insertGetId([
                'reservation_id' => $reservationId,
                'order_type' => 'PreOrder',
                'status' => 'Active',
                'created_at' => $now,
                'created_by' => $staffId,
                'notes' => 'Đơn đặt trước mẫu',
            ]);

            DB::table('reservation_order_items')->insert([
                [
                    'order_id' => $orderId,
                    'item_id' => $itemFood,
                    'quantity' => 1,
                    'status' => 'Ordered',
                    'notes' => null,
                    'created_at' => $now,
                ],
                [
                    'order_id' => $orderId,
                    'item_id' => $itemDrink,
                    'quantity' => 2,
                    'status' => 'Ordered',
                    'notes' => null,
                    'created_at' => $now,
                ],
            ]);

            DB::table('payments')->insert([
                'reservation_id' => $reservationId,
                'amount' => '210000.00',
                'currency' => 'VND',
                'payment_method' => 'Cash',
                'status' => 'Pending',
                'transaction_code' => null,
                'paid_at' => null,
                'created_at' => $now,
                'created_by' => $staffId,
                'notes' => 'Thanh toán mẫu',
            ]);

            DB::table('conversations')
                ->where('conversation_id', '11111111-1111-1111-1111-111111111111')
                ->update(['user_id' => $customerId]);
        });
    }
}
