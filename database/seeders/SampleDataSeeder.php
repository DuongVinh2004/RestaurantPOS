<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Menu Categories
        $categories = [
            ['name' => 'Món Khai Vị', 'sort_order' => 1],
            ['name' => 'Món Chính', 'sort_order' => 2],
            ['name' => 'Thức Uống', 'sort_order' => 3],
        ];
        
        $categoryIds = [];
        foreach ($categories as $cat) {
            $existing = DB::table('menu_categories')->where('name', $cat['name'])->first();
            if (! $existing) {
                $categoryIds[$cat['name']] = DB::table('menu_categories')->insertGetId([
                    'name' => $cat['name'],
                    'sort_order' => $cat['sort_order'],
                ]);
            } else {
                $categoryIds[$cat['name']] = $existing->category_id;
            }
        }

        // 2. Menu Items & Prices
        $items = [
            [
                'category' => 'Món Khai Vị',
                'code' => 'KV01',
                'name' => 'Gỏi Ngó Sen Tôm Thịt',
                'description' => 'Gỏi ngó sen tôm thịt chua ngọt',
                'price' => 85000,
            ],
            [
                'category' => 'Món Khai Vị',
                'code' => 'KV02',
                'name' => 'Chả Giò Hải Sản',
                'description' => 'Chả giò chiên giòn nhân hải sản',
                'price' => 95000,
            ],
            [
                'category' => 'Món Chính',
                'code' => 'MC01',
                'name' => 'Lẩu Thái Hải Sản',
                'description' => 'Lẩu Thái chua cay thơm ngon',
                'price' => 350000,
            ],
            [
                'category' => 'Món Chính',
                'code' => 'MC02',
                'name' => 'Bò Lúc Lắc Khoai Tây Chiên',
                'description' => 'Bò lúc lắc mềm ngọt kèm khoai tây chiên giòn',
                'price' => 180000,
            ],
            [
                'category' => 'Thức Uống',
                'code' => 'TU01',
                'name' => 'Nước Ép Dưa Hấu',
                'description' => 'Nước ép dưa hấu tươi mát',
                'price' => 45000,
            ],
            [
                'category' => 'Thức Uống',
                'code' => 'TU02',
                'name' => 'Bia Heineken',
                'description' => 'Bia Heineken lon 330ml',
                'price' => 35000,
            ]
        ];

        foreach ($items as $item) {
            $existing = DB::table('menu_items')->where('code', $item['code'])->first();
            if (! $existing) {
                $itemId = DB::table('menu_items')->insertGetId([
                    'category_id' => $categoryIds[$item['category']],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'is_available' => 1,
                    'is_preorder_enabled' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('menu_item_prices')->insert([
                    'item_id' => $itemId,
                    'price' => $item['price'],
                    'currency' => 'VND',
                    'effective_from' => now()->subYear(),
                ]);
            }
        }

        // 3. Restaurant Table Templates
        $templates = [
            ['capacity' => 2, 'code' => '2-SEATS'],
            ['capacity' => 4, 'code' => '4-SEATS'],
            ['capacity' => 8, 'code' => '8-SEATS'],
            ['capacity' => 12, 'code' => '12-SEATS'],
        ];
        $templateIds = [];
        foreach ($templates as $tpl) {
            $existing = DB::table('table_templates')->where('seats', $tpl['capacity'])->first();
            if (! $existing) {
                $templateIds[$tpl['capacity']] = DB::table('table_templates')->insertGetId([
                    'template_code' => $tpl['code'],
                    'seats' => $tpl['capacity'],
                    'description' => 'Bàn ' . $tpl['capacity'] . ' người',
                ]);
            } else {
                $templateIds[$tpl['capacity']] = $existing->template_id;
            }
        }

        // 4. Restaurant Tables for all existing branches
        $branches = DB::table('branches')->get();
        foreach ($branches as $branch) {
            // Check if tables already exist for this branch
            $tableCount = DB::table('restaurant_tables')->where('branch_id', $branch->branch_id)->count();
            if ($tableCount === 0) {
                $tables = [
                    ['code' => 'T1', 'zone' => 'Tầng 1', 'capacity' => 2],
                    ['code' => 'T2', 'zone' => 'Tầng 1', 'capacity' => 4],
                    ['code' => 'T3', 'zone' => 'Tầng 1', 'capacity' => 4],
                    ['code' => 'V1', 'zone' => 'VIP', 'capacity' => 8],
                    ['code' => 'V2', 'zone' => 'VIP', 'capacity' => 12],
                ];

                foreach ($tables as $tbl) {
                    DB::table('restaurant_tables')->insert([
                        'table_code' => $tbl['code'] . '-' . $branch->branch_code,
                        'branch_id' => $branch->branch_id,
                        'template_id' => $templateIds[$tbl['capacity']],
                        'zone' => $tbl['zone'],
                        'status' => 'Available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
