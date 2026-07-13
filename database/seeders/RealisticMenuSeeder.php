<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RealisticMenuSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Menu Categories
        $categories = [
            ['name' => 'Món Khai Vị', 'sort_order' => 1],
            ['name' => 'Món Chính - Bò & Gà', 'sort_order' => 2],
            ['name' => 'Món Chính - Hải Sản', 'sort_order' => 3],
            ['name' => 'Lẩu & Nướng', 'sort_order' => 4],
            ['name' => 'Món Chay & Rau', 'sort_order' => 5],
            ['name' => 'Tráng Miệng', 'sort_order' => 6],
            ['name' => 'Thức Uống', 'sort_order' => 7],
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
            // Khai vị
            [
                'category' => 'Món Khai Vị',
                'code' => 'KV01',
                'name' => 'Gỏi Ngó Sen Tôm Thịt',
                'description' => 'Gỏi ngó sen giòn rụm, tôm sú tươi ngon và thịt ba chỉ thái mỏng, hòa quyện với nước mắm chua ngọt.',
                'price' => 125000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/07/Goi_cuon_01.jpg/800px-Goi_cuon_01.jpg',
            ],
            [
                'category' => 'Món Khai Vị',
                'code' => 'KV02',
                'name' => 'Chả Giò Truyền Thống',
                'description' => 'Chả giò chiên giòn tan, nhân thịt heo băm, nấm mèo, khoai môn và gia vị đậm đà.',
                'price' => 95000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/Ch%E1%BA%A3_gi%C3%B2.jpg/800px-Ch%E1%BA%A3_gi%C3%B2.jpg',
            ],
            [
                'category' => 'Món Khai Vị',
                'code' => 'KV03',
                'name' => 'Gỏi Cuốn Tôm Thịt',
                'description' => 'Bánh tráng mỏng cuốn tôm tươi, thịt luộc, bún tươi và rau thơm, chấm tương đen đậu phộng.',
                'price' => 65000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/07/Goi_cuon_01.jpg/800px-Goi_cuon_01.jpg',
            ],
            [
                'category' => 'Món Khai Vị',
                'code' => 'KV04',
                'name' => 'Salad Dầu Giấm',
                'description' => 'Rau xà lách tươi sạch trộn dầu giấm chua ngọt thanh mát.',
                'price' => 55000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/14/Greek_salad_-_Athens.jpg/800px-Greek_salad_-_Athens.jpg',
            ],

            // Bò & Gà
            [
                'category' => 'Món Chính - Bò & Gà',
                'code' => 'BG01',
                'name' => 'Bò Lúc Lắc Khoai Tây Chiên',
                'description' => 'Thịt thăn bò mềm mọng thái hạt lựu, áp chảo lửa lớn xém cạnh, ăn kèm khoai tây chiên giòn.',
                'price' => 185000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1a/Bo_luc_lac_and_fried_rice.jpg/800px-Bo_luc_lac_and_fried_rice.jpg',
            ],
            [
                'category' => 'Món Chính - Bò & Gà',
                'code' => 'BG02',
                'name' => 'Gà Nướng Mật Ong',
                'description' => 'Đùi gà nướng mềm tẩm ướp mật ong rừng, da giòn thơm lừng.',
                'price' => 165000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/e/eb/Roasted_chicken_with_honey.jpg/800px-Roasted_chicken_with_honey.jpg',
            ],
            [
                'category' => 'Món Chính - Bò & Gà',
                'code' => 'BG03',
                'name' => 'Cơm Tấm Sườn Bì Chả',
                'description' => 'Cơm tấm Sài Gòn ăn kèm sườn cốt lết nướng than hoa, bì heo thái chỉ và chả trứng hấp.',
                'price' => 85000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/cd/C%C6%A1m_t%E1%BA%A5m.jpg/800px-C%C6%A1m_t%E1%BA%A5m.jpg',
            ],
            [
                'category' => 'Món Chính - Bò & Gà',
                'code' => 'BG04',
                'name' => 'Phở Bò Đặc Biệt',
                'description' => 'Phở bò nước dùng hầm xương 24h, kèm tái, nạm, gầu, gân, bò viên.',
                'price' => 75000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/53/Pho-Beef-Noodles-2008.jpg/800px-Pho-Beef-Noodles-2008.jpg',
            ],

            // Hải Sản
            [
                'category' => 'Món Chính - Hải Sản',
                'code' => 'HS01',
                'name' => 'Tôm Sú Nướng Muối Ớt',
                'description' => 'Tôm sú loại lớn nướng than hồng với muối ớt xanh cay nồng.',
                'price' => 245000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a2/Grilled_shrimp.jpg/800px-Grilled_shrimp.jpg',
            ],
            [
                'category' => 'Món Chính - Hải Sản',
                'code' => 'HS02',
                'name' => 'Mực Chiên Nước Mắm',
                'description' => 'Mực ống tươi chiên vàng, rim với nước mắm Phú Quốc sánh đặc, đậm vị.',
                'price' => 195000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4b/Fried_calamari.jpg/800px-Fried_calamari.jpg',
            ],
            [
                'category' => 'Món Chính - Hải Sản',
                'code' => 'HS03',
                'name' => 'Cá Chẽm Hấp Xì Dầu',
                'description' => 'Cá chẽm tươi nguyên con hấp với gừng, hành hoa và xì dầu Hongkong thượng hạng.',
                'price' => 350000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b2/Steamed_fish_with_soy_sauce.jpg/800px-Steamed_fish_with_soy_sauce.jpg',
            ],

            // Lẩu & Nướng
            [
                'category' => 'Lẩu & Nướng',
                'code' => 'LN01',
                'name' => 'Lẩu Thái Hải Sản (Size Vừa)',
                'description' => 'Nước lẩu Tom Yum Thái Lan chua cay chuẩn vị, ăn kèm hải sản tươi sống và rau nấm.',
                'price' => 380000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/54/Hot_pot_at_Haidilao.jpg/800px-Hot_pot_at_Haidilao.jpg',
            ],
            [
                'category' => 'Lẩu & Nướng',
                'code' => 'LN02',
                'name' => 'Lẩu Bò Nhúng Dấm',
                'description' => 'Lẩu bò nhúng dấm chua thanh, kèm bắp bò, gân bò và bún tươi.',
                'price' => 320000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/86/Beef_hot_pot.jpg/800px-Beef_hot_pot.jpg',
            ],
            [
                'category' => 'Lẩu & Nướng',
                'code' => 'LN03',
                'name' => 'BBQ Thập Cẩm Mộc Sen',
                'description' => 'Khay nướng thập cẩm khổng lồ với sườn bò Mỹ, bạch tuộc, tôm sú và xúc xích.',
                'price' => 590000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/0/00/Korean_BBQ.jpg/800px-Korean_BBQ.jpg',
            ],

            // Rau
            [
                'category' => 'Món Chay & Rau',
                'code' => 'RC01',
                'name' => 'Rau Muống Xào Tỏi',
                'description' => 'Rau muống xanh mướt xào tỏi đập dập thơm lừng.',
                'price' => 65000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3f/Stir_fried_water_spinach.jpg/800px-Stir_fried_water_spinach.jpg',
            ],
            [
                'category' => 'Món Chay & Rau',
                'code' => 'RC02',
                'name' => 'Đậu Hũ Tứ Xuyên (Chay)',
                'description' => 'Đậu hũ non sốt nấm cay nồng phong cách Tứ Xuyên.',
                'price' => 85000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Mapo_doufu.jpg/800px-Mapo_doufu.jpg',
            ],

            // Tráng Miệng
            [
                'category' => 'Tráng Miệng',
                'code' => 'TM01',
                'name' => 'Bánh Flan Caramel',
                'description' => 'Bánh flan mềm mịn, béo ngậy với lớp caramel cháy thơm đắng nhẹ.',
                'price' => 35000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/57/Flan_%28creme_caramel%29.jpg/800px-Flan_%28creme_caramel%29.jpg',
            ],
            [
                'category' => 'Tráng Miệng',
                'code' => 'TM02',
                'name' => 'Chè Khúc Bạch',
                'description' => 'Chè khúc bạch hạnh nhân, vải thiều và nhãn lồng thanh mát.',
                'price' => 45000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/10/Ch%C3%A8_kh%C3%BAc_b%E1%BA%A1ch.jpg/800px-Ch%C3%A8_kh%C3%BAc_b%E1%BA%A1ch.jpg',
            ],

            // Thức Uống
            [
                'category' => 'Thức Uống',
                'code' => 'TU01',
                'name' => 'Cà Phê Sữa Đá',
                'description' => 'Cà phê pha phin truyền thống Việt Nam kết hợp sữa đặc ngọt ngào.',
                'price' => 35000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1e/Ca_phe_sua_da_2.jpg/800px-Ca_phe_sua_da_2.jpg',
            ],
            [
                'category' => 'Thức Uống',
                'code' => 'TU02',
                'name' => 'Nước Ép Dưa Hấu',
                'description' => 'Nước ép dưa hấu nguyên chất mát lạnh, không thêm đường.',
                'price' => 45000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/Watermelon_juice.jpg/800px-Watermelon_juice.jpg',
            ],
            [
                'category' => 'Thức Uống',
                'code' => 'TU03',
                'name' => 'Trà Đào Cam Sả',
                'description' => 'Trà đen pha cùng siro đào, sả tươi đập dập và vài lát cam.',
                'price' => 55000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/68/Iced_tea.jpg/800px-Iced_tea.jpg',
            ],
            [
                'category' => 'Thức Uống',
                'code' => 'TU04',
                'name' => 'Bia Heineken (Lon)',
                'description' => 'Bia Heineken lon 330ml ướp lạnh.',
                'price' => 45000,
                'img_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ae/Heineken_beer.jpg/800px-Heineken_beer.jpg',
            ],
        ];

        foreach ($items as $item) {
            $existing = DB::table('menu_items')->where('code', $item['code'])->first();
            if (! $existing) {
                $itemId = DB::table('menu_items')->insertGetId([
                    'category_id' => $categoryIds[$item['category']],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'img_url' => $item['img_url'],
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
            } else {
                DB::table('menu_items')->where('code', $item['code'])->update([
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'img_url' => $item['img_url'],
                    'category_id' => $categoryIds[$item['category']],
                ]);
                DB::table('menu_item_prices')->where('item_id', $existing->item_id)->update([
                    'price' => $item['price'],
                ]);
            }
        }
    }
}
