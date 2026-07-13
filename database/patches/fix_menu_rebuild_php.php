<?php

/**
 * Fix: Re-insert all 66 menu items using pure PHP (no TEMPORARY TABLE).
 * Safe to run multiple times (updateOrInsert = upsert by code).
 * Run via: php artisan tinker --execute="require 'database/patches/fix_menu_rebuild_php.php';"
 *
 * Root cause of original failure:
 * TiDB utf8mb4_unicode_ci is accent+case insensitive, so the SQL patch's
 * UPDATE is_deleted=1 WHERE name IN ('Khai vi','Mon chinh',...) accidentally
 * deleted the newly inserted clean-name categories.
 * This script re-activates them by known category_id.
 */

use Illuminate\Support\Facades\DB;

echo '=== Menu Rebuild Fix (PHP) v2 ==='.PHP_EOL;

// ---------------------------------------------------------------------------
// 1a. Hide old conflicting categories (Vietnamese diacritics → clean names)
// ---------------------------------------------------------------------------
// id=2: 'Đồ Uống' → replaced by id=9 'Do Uong'
// id=1: 'Món Chính' → replaced by id=1 renamed to 'Mon Chinh'
DB::table('menu_categories')->where('category_id', 2)->update(['is_deleted' => 1]);
echo '  Hidden: Đồ Uống (id=2)'.PHP_EOL;

// ---------------------------------------------------------------------------
// 1b. Re-activate needed categories by known ID (bypass UNIQUE key issue)
// The SQL patch accidentally set is_deleted=1 on these via accent-insensitive
// WHERE name IN ('Khai vi','Mon chinh','Rau & chay','Trang miem','Do uong')
// ---------------------------------------------------------------------------
$reactivations = [
    1 => ['name' => 'Mon Chinh',  'sort_order' => 30],  // was 'Món Chính'
    3 => ['name' => 'Khai Vi',    'sort_order' => 10],
    7 => ['name' => 'Rau & Chay', 'sort_order' => 50],
    8 => ['name' => 'Trang Miem', 'sort_order' => 60],
    9 => ['name' => 'Do Uong',    'sort_order' => 70],
];
foreach ($reactivations as $id => $meta) {
    $rows = DB::table('menu_categories')->where('category_id', $id)->update([
        'name'       => $meta['name'],
        'is_deleted' => 0,
        'sort_order' => $meta['sort_order'],
    ]);
    echo ($rows ? '  RE-ACTIVATED' : '  NOT FOUND').": {$meta['name']} (id=$id)".PHP_EOL;
}

// ---------------------------------------------------------------------------
// 1c. Load final category ID map
// ---------------------------------------------------------------------------
$catMap = DB::table('menu_categories')
    ->where('is_deleted', 0)
    ->pluck('category_id', 'name')
    ->toArray();

echo PHP_EOL.'Active categories ('.count($catMap).'):'.PHP_EOL;
foreach ($catMap as $name => $id) {
    echo "  $id | $name".PHP_EOL;
}

// Sanity check
$missing = array_diff(['Khai Vi','Canh & Sup','Mon Chinh','Com & Mi','Rau & Chay','Trang Miem','Do Uong','Set & Combo'], array_keys($catMap));
if (! empty($missing)) {
    echo PHP_EOL.'  ⚠️ MISSING categories: '.implode(', ', $missing).PHP_EOL;
    exit(1);
}

// ---------------------------------------------------------------------------
// 2. All 66 items
// Format: [code, cat, name, desc, price, cmp_price, img, avail, preorder, bestseller, combo, quota, cutoff, ssize]
// ---------------------------------------------------------------------------
$items = [
    // KHAI VI
    ['MS-GOI-CUON-TOM-THIT','Khai Vi','Goi cuon tom thit','Tom su tuoi, thit ba chi luoc, bun manh, rau song va nuoc sot dau phong rang thom. 2 cuon/phan.',65000,null,'/customer-web/menu/goi-cuon-tom-thit.jpg',1,1,1,0,90,0,2],
    ['MS-NEM-SEN-GION','Khai Vi','Nem sen gion','Nem chien gion nhan thit heo, nam meo, mien va cu sen - linh hon cua Moc Sen. Dung kem nuoc mam chua ngot.',75000,null,'/customer-web/menu/nem-sen-gion.jpg',1,1,1,0,80,0,3],
    ['MS-GOI-NGO-SEN-BO-TAI','Khai Vi','Goi ngo sen bo tai','Ngo sen gion, bo tai chanh, hanh tay, me rang va sot Moc Sen dac biet - mon ky hieu thuong hieu.',89000,null,'/customer-web/menu/goi-ngo-sen-bo-tai.jpg',1,1,1,0,60,0,2],
    ['MS-GOI-GA-BAP-CHUOI','Khai Vi','Goi ga bap chuoi','Ga xe phay, bap chuoi non thai mong, rau ram, hanh phi va nuoc mam chua ngot dac trung.',79000,null,'/customer-web/menu/goi-ga-bap-chuoi.jpg',1,1,0,0,70,0,2],
    ['MS-CHA-GIO-HAI-SAN','Khai Vi','Cha gio hai san','Cuon hai san thap cam chien gion, nhan tom muc cua. An kem rau song va sot Moc Sen.',89000,null,'/customer-web/menu/cha-gio-hai-san.jpg',1,1,0,0,60,0,3],
    ['MS-SALAD-XOAI-TOM','Khai Vi','Salad xoai tom','Xoai xanh thai soi, tom su ap chao, dau phong rang, rau thom va sot chua ngot kieu Thai.',79000,null,'/customer-web/menu/salad-xoai-tom.jpg',1,1,0,0,70,0,2],
    ['MS-CHA-MUC-MINI','Khai Vi','Cha muc mini Moc Sen','Cha muc gia tay thu cong, khong chat bao quan, chien vang deu. Cham tuong ot Moc Sen tu lam.',95000,null,'/customer-web/menu/cha-muc-mini.jpg',1,1,0,0,50,0,4],
    ['MS-DAU-HU-RANG-MUOI','Khai Vi','Dau hu rang muoi sa','Dau hu non ao bot mong, rang vang voi muoi sa ot - gion ngoai, mem trong. Phu hop an chay.',55000,null,'/customer-web/menu/dau-hu-rang-muoi.jpg',1,1,0,0,80,0,2],
    // CANH & SUP
    ['MS-CANH-CHUA-CA-LOC','Canh & Sup','Canh chua ca loc','Nuoc dung chua thanh tu me, ca loc phi-le tuoi, ca chua, thom, gia do va ngo - dam chat mien Tay.',69000,null,'/customer-web/menu/canh-chua-ca-loc.jpg',1,1,1,0,60,0,1],
    ['MS-CANH-KHO-QUA-NHOI','Canh & Sup','Canh kho qua nhoi thit','Kho qua tuoi nhoi thit heo xay, nau nuoc dung trong - dang diu, ngot sau, thanh loc.',65000,null,'/customer-web/menu/canh-kho-qua-nhoi.jpg',1,1,0,0,60,0,1],
    ['MS-SUP-BI-DO-TOM','Canh & Sup','Sup bi do tom tuoi','Bi do xay min sanh vang, tom su tuoi nguyen con, kem tuoi va hanh la - sup dau bua tinh te.',59000,null,'/customer-web/menu/sup-bi-do-tom.jpg',1,1,0,0,70,0,1],
    ['MS-CANH-RAU-NGOT-THIT','Canh & Sup','Canh rau ngot thit heo','Rau ngot tuoi hai ngay, thit heo xay, nuoc dung ngot nhe - bua com thuan Viet chuan vi nha.',45000,null,'/customer-web/menu/canh-rau-ngot-thit.jpg',1,1,0,0,80,0,1],
    ['MS-CANH-RAU-CU-HAT-SEN','Canh & Sup','Canh rau cu hat sen','Hat sen tuoi, ca rot, su hao, nam huong - nuoc dung rau cu nhe, phu hop nguoi an chay.',55000,null,'/customer-web/menu/canh-rau-cu-hat-sen.jpg',1,1,0,0,70,0,1],
    // MON CHINH
    ['MS-COM-GA-LA-SEN','Mon Chinh','Com ga la sen','Ga ta ap chao da gion, com deo nau voi nuoc cot ga, sot gung mat ong, rau cu theo mua. Dac trung Moc Sen.',95000,null,'/customer-web/menu/com-ga-la-sen.jpg',1,1,1,0,90,0,1],
    ['MS-BUN-BO-MOC-SEN','Mon Chinh','Bun bo Moc Sen','Nuoc dung ham xuong 6 gio, thit bo bap mem, gio heo, mam ruoc va sa te tu lam. Signature dish.',99000,null,'/customer-web/menu/bun-bo-moc-sen.jpg',1,1,1,0,90,0,1],
    ['MS-CA-KHO-NIEU-DAT','Mon Chinh','Ca kho nieu dat','Ca loc kho nuoc mau dua, tieu hat, nuoc mam nguyen chat trong nieu dat nung - an kem com trang nong.',119000,null,'/customer-web/menu/ca-kho-nieu-dat.jpg',1,1,1,0,50,30,1],
    ['MS-BO-LUC-LAC-SOT-TIEU','Mon Chinh','Bo luc lac sot tieu xanh','Than bo Uc ap chao, khoai tay chien bo, salad ca chua va sot tieu xanh Madagascar - bistro chuan.',149000,null,'/customer-web/menu/bo-luc-lac-sot-tieu.jpg',1,1,0,0,60,0,1],
    ['MS-GA-NUONG-MAT-ONG','Mon Chinh','Ga nuong mat ong nghe','Nua ga ta uop mat ong, nghe tuoi, xa, nuong than hoa deu lua. Rau cu nuong kem, sot tac mat ong.',135000,null,'/customer-web/menu/ga-nuong-mat-ong.jpg',1,1,0,0,55,60,1],
    ['MS-TOM-SOT-ME','Mon Chinh','Tom sot me Moc Sen','Tom su lon ap chao voi bo toi, sot me chua ngot tu lam, hanh phi gion va ot tuoi.',159000,null,'/customer-web/menu/tom-sot-me.jpg',1,1,0,0,50,0,1],
    ['MS-SUON-NON-RIM-MAM','Mon Chinh','Suon non rim mam toi','Suon non heo rim mam toi ngot man hai hoa, an kem dua leo ngam chua va com trang nong.',129000,null,'/customer-web/menu/suon-non-rim-mam.jpg',1,1,0,0,60,30,1],
    ['MS-VIT-AP-CHAO-SOT-ME','Mon Chinh','Vit ap chao sot me','Uc vit ap chao da gion ron, sot me chua ngot dac, rau thom va hanh tay ngam giam.',165000,null,'/customer-web/menu/vit-ap-chao-sot-me.jpg',1,1,0,0,40,0,1],
    ['MS-MUC-XAO-SA-TE','Mon Chinh','Muc xao sa te rau cu','Muc ong cat khoanh xao lua to voi ca chua, hanh tay, ot chuong va sa te tu lam - cay nhe thom.',145000,null,'/customer-web/menu/muc-xao-sa-te.jpg',1,1,0,0,55,0,1],
    ['MS-CA-CHIEN-MAM-XOAI','Mon Chinh','Ca chien mam xoai','Ca dieu hong phi-le chien gion, cham mam xoai xanh tu ngam, rau song an kem.',145000,null,'/customer-web/menu/ca-chien-mam-xoai.jpg',1,1,0,0,55,0,1],
    ['MS-BO-KHO-BANH-MI','Mon Chinh','Bo kho banh mi nong','Bo bap ham sa te hoa hoi, ca rot mem vua mieng, nuoc sot sanh do - an kem banh mi lo nong gion.',99000,null,'/customer-web/menu/bo-kho-banh-mi.jpg',1,1,0,0,70,0,1],
    ['MS-SUON-BO-NUONG-BISTRO','Mon Chinh','Suon bo nuong kieu bistro','Suon bo nuong lo, bo thao moc, khoai tay nghien bo sua, nam sot vang do - mon premium toi cuoi tuan.',189000,null,'/customer-web/menu/suon-bo-nuong-bistro.jpg',1,1,0,0,30,120,1],
    // COM & MI
    ['MS-PHO-GA-THAO-MOC','Com & Mi','Pho ga thao moc','Nuoc dung ham ga ta voi thao moc, ga xe mem, banh pho soi manh, rau thom va chanh ot tuoi.',79000,null,'/customer-web/menu/pho-ga-thao-moc.jpg',1,1,1,0,80,0,1],
    ['MS-BUN-CHA-HA-NOI','Com & Mi','Bun cha Ha Noi','Thit cha nuong than, thit heo vien, nuoc cham pha chuan Ha Noi - an kem bun tuoi va rau song.',89000,null,'/customer-web/menu/bun-cha-ha-noi.jpg',1,1,1,0,90,0,1],
    ['MS-COM-SUON-MAT-ONG','Com & Mi','Com suon mat ong trung op','Suon cot-let nuong mat ong, com trang, trung op-la, dua leo va do chua Moc Sen - com dia van phong dinh.',99000,null,'/customer-web/menu/com-suon-mat-ong.jpg',1,1,0,0,80,0,1],
    ['MS-MI-XAO-BO-RAU-CU','Com & Mi','Mi xao bo rau cu','Mi trung xao lua to, bo thai mong, ot chuong, hanh tay, ca rot - sot hau hai vi dam da.',95000,null,'/customer-web/menu/mi-xao-bo-rau-cu.jpg',1,1,0,0,80,0,1],
    ['MS-BUN-THIT-NUONG','Com & Mi','Bun thit nuong dac biet','Thit nuong than mo hanh, cha gio chien, bun, rau song day du, do chua va nuoc mam Moc Sen.',89000,null,'/customer-web/menu/bun-thit-nuong.jpg',1,1,0,0,90,0,1],
    ['MS-COM-BO-XAO-SATE','Com & Mi','Com bo xao sa te','Bo Uc thai lat xao sa te cay nhe, com trang, dua leo, do chua - bua trua du chat.',109000,null,'/customer-web/menu/com-bo-xao-sate.jpg',1,1,0,0,70,0,1],
    ['MS-MIEN-GA-NAM','Com & Mi','Mien ga nam huong','Mien dong dai, ga ta xe, nam huong kho ngam no, nuoc dung ga thanh ngot, hanh la va tieu.',79000,null,'/customer-web/menu/mien-ga-nam.jpg',1,1,0,0,75,0,1],
    ['MS-COM-CHIEN-HAI-SAN','Com & Mi','Com chien hai san Moc Sen','Com chien tom muc, trung ga, hanh la, ca rot - chien lua to, hat com roi, hai san ngot tuoi.',95000,null,'/customer-web/menu/com-chien-hai-san.jpg',1,1,0,0,75,0,1],
    // RAU & CHAY
    ['MS-RAU-CU-XAO-TOI','Rau & Chay','Rau cu xao toi','Rau theo mua (cai ngot, bong cai, dau co-ve) xao toi bam thom, lua to giu gion.',55000,null,'/customer-web/menu/rau-cu-xao-toi.jpg',1,1,0,0,90,0,2],
    ['MS-DAU-HU-SOT-NAM','Rau & Chay','Dau hu non sot nam','Dau hu non cat mieng, sot nam dong co va nam rom tuoi - dam thuc vat giau dinh duong.',65000,null,'/customer-web/menu/dau-hu-sot-nam.jpg',1,1,0,0,70,0,2],
    ['MS-NAM-KHO-TIEU','Rau & Chay','Nam kho tieu den','Nam bao ngu, nam dong co kho tuong hoisin, tieu den va hanh boa-ro - an kem com nong.',69000,null,'/customer-web/menu/nam-kho-tieu.jpg',1,1,0,0,70,0,2],
    ['MS-GOI-RAU-MAM-BO','Rau & Chay','Goi rau mam bo ap chao','Rau mam tuoi, bo thai mong ap chao tai hong, sot me rang va dau hao - salad A tinh te.',89000,null,'/customer-web/menu/goi-rau-mam-bo.jpg',1,1,0,0,70,0,2],
    ['MS-CA-TIM-NUONG-MO-HANH','Rau & Chay','Ca tim nuong mo hanh','Ca tim nuong mem thom, mo hanh phi vang, dau phong rang gia tho, nuoc mam chay chua ngot.',59000,null,'/customer-web/menu/ca-tim-nuong-mo-hanh.jpg',1,1,0,0,70,0,1],
    ['MS-DAU-BAP-XAO-TOI','Rau & Chay','Dau bap xao toi','Dau bap non chon loc xao toi lua to, giu do gion va vi ngot tu nhien cua rau.',52000,null,'/customer-web/menu/dau-bap-xao-toi.jpg',1,1,0,0,80,0,2],
    ['MS-NOM-HOA-CHUOI-CHAY','Rau & Chay','Nom hoa chuoi chay','Hoa chuoi thai, dau phong, me, rau thom va nuoc tron chua ngot chay - thanh mat giai nhiet.',59000,null,'/customer-web/menu/nom-hoa-chuoi-chay.jpg',1,1,0,0,75,0,2],
    // TRANG MIEM
    ['MS-CHE-SEN-LONG-NHAN','Trang Miem','Che sen long nhan','Hat sen nau mem vua mieng, long nhan ngot thanh, duong phen - dung lanh. Dac san Moc Sen.',49000,null,'/customer-web/menu/che-sen-long-nhan.jpg',1,1,1,0,90,0,1],
    ['MS-PANNA-COTTA-DUA','Trang Miem','Panna cotta dua xoai','Kem dua Thai sanh min, sot xoai chin chua nhe va lat xoai tuoi - fusion Viet-Au tinh te.',55000,null,'/customer-web/menu/panna-cotta-dua.jpg',1,1,0,0,70,0,1],
    ['MS-BANH-FLAN-CA-PHE','Trang Miem','Banh flan ca phe','Flan min pha ca phe robusta dam, lop caramel chay nhe - ket thuc hoan hao cho bua toi.',45000,null,'/customer-web/menu/banh-flan-ca-phe.jpg',1,1,1,0,90,0,1],
    ['MS-KEM-DUA-NON','Trang Miem','Kem dua non','Kem dua tuoi trong trai dua non, kem thach dua, dau phong rang - giai nhiet mua he.',59000,null,'/customer-web/menu/kem-dua-non.jpg',1,1,0,0,60,0,1],
    ['MS-SUA-CHUA-NEP-CAM','Trang Miem','Sua chua nep cam mat ong','Sua chua Hy Lap min, nep cam deo tim, mat ong hoa nhan va hat chia - lanh manh, ngon mieng.',49000,null,'/customer-web/menu/sua-chua-nep-cam.jpg',1,1,0,0,80,0,1],
    ['MS-BANH-CHUOI-NUONG','Trang Miem','Banh chuoi nuong nuoc cot dua','Chuoi su chin nuong than thom, chan nuoc cot dua am, me rang va duong thot not.',49000,null,'/customer-web/menu/banh-chuoi-nuong.jpg',1,1,0,0,75,0,1],
    ['MS-TAU-HU-NUOC-DUONG','Trang Miem','Tau hu nuoc duong gung','Tau hu non min, nuoc duong gung am thom, tran chau nho va la pandan - chay 100%.',42000,null,'/customer-web/menu/tau-hu-nuoc-duong.jpg',1,1,0,0,90,0,1],
    // DO UONG
    ['MS-TRA-SEN-LANH','Do Uong','Tra sen uop tuoi lanh','Tra oolong uop hoa sen tuoi cat buoi sang, pha nguoi va rot da - linh hon cua Moc Sen.',39000,null,'/customer-web/menu/tra-sen-lanh.jpg',1,1,1,0,150,0,1],
    ['MS-TRA-SEN-NONG','Do Uong','Tra sen nong','Tra oolong uop sen pha binh, dung nong - thich hop mua dong hay khong khi may lanh.',35000,null,'/customer-web/menu/tra-sen-nong.jpg',1,1,0,0,120,0,1],
    ['MS-CA-PHE-SUA-DA','Do Uong','Ca phe sua da','Ca phe phin robusta rang dam, sua dac ong Tho, da vien - chuan ca phe Viet truyen thong.',39000,null,'/customer-web/menu/ca-phe-sua-da.jpg',1,1,0,0,120,0,1],
    ['MS-CA-PHE-DEN-DA','Do Uong','Ca phe den da','Ca phe phin nguyen chat, khong sua, pha loang vua du, da to giu lanh lau.',29000,null,'/customer-web/menu/ca-phe-den-da.jpg',1,1,0,0,120,0,1],
    ['MS-NUOC-CHANH-SA','Do Uong','Nuoc chanh sa mat ong','Chanh vat tuoi, sa dap, mat ong hoa nhan - thanh mat, giai nhiet, nhe ngot tu nhien.',42000,null,'/customer-web/menu/nuoc-chanh-sa.jpg',1,1,0,0,110,0,1],
    ['MS-TRA-TAC-MAT-ONG','Do Uong','Tra tac mat ong da','Tra xanh pha voi tac (quat) tuoi vat, mat ong, da vien - chua diu, thom mat.',42000,null,'/customer-web/menu/tra-tac-mat-ong.jpg',1,1,0,0,110,0,1],
    ['MS-SINH-TO-XOAI','Do Uong','Sinh to xoai cat','Xoai cat Hoa Loc chin, sua chua tuoi, da xay - dac quanh, ngot diu, khong pha them duong.',59000,null,'/customer-web/menu/sinh-to-xoai.jpg',1,1,0,0,80,0,1],
    ['MS-SINH-TO-BO-SUA','Do Uong','Sinh to bo sua','Bo sap Dak Lak chin, sua tuoi khong duong, duong phen nhe, da bao - beo min, no lau.',65000,null,'/customer-web/menu/sinh-to-bo-sua.jpg',1,1,0,0,70,0,1],
    ['MS-NUOC-EP-CAM-CA-ROT','Do Uong','Nuoc ep cam ca rot tuoi','Cam sanh va ca rot ep ngay khi order, khong duong khong bao quan - vitamin C tu nhien.',52000,null,'/customer-web/menu/nuoc-ep-cam-ca-rot.jpg',1,1,0,0,90,0,1],
    ['MS-TRA-DAO-CAM-SA','Do Uong','Tra dao cam sa','Dao ngam, cam tuoi, sa thom, tra xanh Thai Nguyen, da vien - nhe nhang, thom trai cay.',49000,null,'/customer-web/menu/tra-dao-cam-sa.jpg',1,1,0,0,100,0,1],
    ['MS-NUOC-DUA-TUOI','Do Uong','Nuoc dua tuoi','Dua xiem cat phuc vu nguyen trai - mat lanh tu nhien, ngot thanh dac trung.',45000,null,'/customer-web/menu/nuoc-dua-tuoi.jpg',1,1,0,0,80,0,1],
    ['MS-NUOC-SUOI','Do Uong','Nuoc suoi / Soda','Nuoc suoi Vinh Hao hoac soda lanh - lua chon don gian di kem bua an.',22000,null,'/customer-web/menu/nuoc-suoi-soda.jpg',1,0,0,0,200,0,1],
    ['MS-BIA-CHAI','Do Uong','Bia Sai Gon lanh','Bia Sai Gon chai 330ml uop lanh - di kem bua toi hoac gap go ban be.',25000,null,'/customer-web/menu/bia-chai.jpg',1,0,0,0,200,0,1],
    // SET & COMBO
    ['MS-SET-TRUA-VP','Set & Combo','Set Trua Van Phong','1 mon com/mi tu chon + 1 to canh nho + 1 tra sen lanh. Giao tu 11h. Thay doi theo ngay.',149000,185000,'/customer-web/menu/set-trua-van-phong.jpg',1,1,1,1,60,0,1],
    ['MS-SET-GIA-DINH','Set & Combo','Set Gia Dinh Moc Sen (4 nguoi)','4 mon chinh (ga/ca/bo/tom luan phien), 1 canh, 1 rau xao, 1 trang miem + 4 do uong. Phuc vu 4 nguoi.',449000,580000,'/customer-web/menu/set-gia-dinh-moc-sen.jpg',1,1,0,1,30,60,4],
    ['MS-SET-HEN-HO','Set & Combo','Set Hen Ho Ben Cua So (2 nguoi)','Khai vi chia se, 2 mon chinh premium, 2 do uong signature Moc Sen, 1 trang miem. Ban Window Zone.',329000,415000,'/customer-web/menu/set-hen-ho-ben-cua-so.jpg',1,1,0,1,25,60,2],
    ['MS-SET-BEP-TRUONG','Set & Combo','Set Bep Truong De Xuat (4 nguoi)','Combo cao cap 7 mon theo mua do bep truong lua chon. Thay doi theo tuan. Dat truoc 2 gio.',499000,650000,'/customer-web/menu/set-bep-truong-de-xuat.jpg',1,1,0,1,20,120,4],
    ['MS-SET-CUOI-TUAN','Set & Combo','Set Cuoi Tuan Dai Gia Dinh (6 nguoi)','6 mon chinh, 2 rau xao, 1 canh, 2 trang miem + 6 do uong. Phuc vu 6 nguoi. Dat truoc 3 gio.',799000,1050000,'/customer-web/menu/set-cuoi-tuan.jpg',1,1,0,1,15,180,6],
    ['MS-SET-CHAY','Set & Combo','Set Chay Moc Sen (2 nguoi)','1 khai vi chay, 2 mon chay tu chon, 1 canh rau, 1 trang miem chay + 2 tra sen nong. 100% thuc vat.',249000,310000,'/customer-web/menu/set-chay-moc-sen.jpg',1,1,0,1,25,0,2],
];

// ---------------------------------------------------------------------------
// 3. Upsert items
// ---------------------------------------------------------------------------
$inserted = 0;
$updated = 0;
$skipped = 0;

foreach ($items as [$code,$cat,$name,$desc,$price,$cmpPrice,$img,$avail,$preorder,$bs,$combo,$quota,$cutoff,$ssize]) {
    if (! isset($catMap[$cat])) {
        echo "  SKIP (no cat): $code → $cat".PHP_EOL;
        $skipped++;
        continue;
    }
    $catId = $catMap[$cat];

    $exists = DB::table('menu_items')->where('code', $code)->first();
    $data = [
        'category_id' => $catId,
        'name' => $name,
        'description' => $desc,
        'img_url' => $img,
        'is_available' => $avail,
        'is_preorder_enabled' => $preorder,
        'is_best_seller' => $bs,
        'is_combo' => $combo,
        'preorder_quota_per_day' => $quota,
        'preorder_cutoff_minutes' => $cutoff,
        'compare_at_price_amount' => $cmpPrice,
        'serving_size' => $ssize,
    ];

    if ($exists) {
        DB::table('menu_items')->where('code', $code)->update($data);
        $updated++;
    } else {
        DB::table('menu_items')->insert(array_merge($data, ['code' => $code]));
        $inserted++;
    }
}

echo "Items: $inserted inserted, $updated updated, $skipped skipped".PHP_EOL;

// ---------------------------------------------------------------------------
// 4. Insert prices (only where no active price exists)
// ---------------------------------------------------------------------------
$priceInserted = 0;
foreach ($items as [$code,$cat,$name,$desc,$price]) {
    $item = DB::table('menu_items')->where('code', $code)->first(['item_id']);
    if (! $item) continue;
    $hasPrice = DB::table('menu_item_prices')
        ->where('item_id', $item->item_id)
        ->where('currency', 'VND')
        ->whereNull('effective_to')
        ->exists();
    if (! $hasPrice) {
        DB::table('menu_item_prices')->insert([
            'item_id' => $item->item_id,
            'price' => $price,
            'currency' => 'VND',
            'effective_from' => '2026-06-20 00:00:00',
            'effective_to' => null,
        ]);
        $priceInserted++;
    }
}
echo 'Prices inserted: '.$priceInserted.PHP_EOL;

// ---------------------------------------------------------------------------
// 5. Combo components (INSERT IGNORE equivalent)
// ---------------------------------------------------------------------------
function insertComboComponents(string $comboCode, array $componentCodes, array $quantities = []): int {
    $combo = DB::table('menu_items')->where('code', $comboCode)->first(['item_id']);
    if (! $combo) return 0;
    $count = 0;
    foreach ($componentCodes as $cpCode) {
        $cp = DB::table('menu_items')->where('code', $cpCode)->first(['item_id']);
        if (! $cp) continue;
        $qty = $quantities[$cpCode] ?? 1;
        $exists = DB::table('menu_item_combo_components')
            ->where('combo_item_id', $combo->item_id)
            ->where('component_item_id', $cp->item_id)
            ->exists();
        if (! $exists) {
            DB::table('menu_item_combo_components')->insert([
                'combo_item_id' => $combo->item_id,
                'component_item_id' => $cp->item_id,
                'quantity' => $qty,
            ]);
            $count++;
        }
    }
    return $count;
}

$compCount = 0;
$compCount += insertComboComponents('MS-SET-TRUA-VP', [
    'MS-COM-SUON-MAT-ONG','MS-CANH-RAU-NGOT-THIT','MS-TRA-SEN-LANH'
]);
$compCount += insertComboComponents('MS-SET-GIA-DINH', [
    'MS-COM-GA-LA-SEN','MS-BO-LUC-LAC-SOT-TIEU','MS-TOM-SOT-ME','MS-CA-KHO-NIEU-DAT',
    'MS-CANH-CHUA-CA-LOC','MS-RAU-CU-XAO-TOI','MS-CHE-SEN-LONG-NHAN','MS-TRA-SEN-LANH'
], ['MS-CHE-SEN-LONG-NHAN'=>2,'MS-TRA-SEN-LANH'=>4]);
$compCount += insertComboComponents('MS-SET-HEN-HO', [
    'MS-GOI-NGO-SEN-BO-TAI','MS-BO-LUC-LAC-SOT-TIEU','MS-VIT-AP-CHAO-SOT-ME','MS-PANNA-COTTA-DUA','MS-TRA-SEN-LANH'
], ['MS-TRA-SEN-LANH'=>2]);
$compCount += insertComboComponents('MS-SET-BEP-TRUONG', [
    'MS-NEM-SEN-GION','MS-GOI-CUON-TOM-THIT','MS-CA-KHO-NIEU-DAT',
    'MS-SUON-BO-NUONG-BISTRO','MS-TOM-SOT-ME','MS-CANH-CHUA-CA-LOC','MS-CHE-SEN-LONG-NHAN'
]);
$compCount += insertComboComponents('MS-SET-CUOI-TUAN', [
    'MS-COM-GA-LA-SEN','MS-BO-LUC-LAC-SOT-TIEU','MS-TOM-SOT-ME','MS-VIT-AP-CHAO-SOT-ME',
    'MS-GA-NUONG-MAT-ONG','MS-MUC-XAO-SA-TE','MS-CANH-CHUA-CA-LOC','MS-RAU-CU-XAO-TOI',
    'MS-NAM-KHO-TIEU','MS-CHE-SEN-LONG-NHAN','MS-BANH-FLAN-CA-PHE','MS-TRA-SEN-LANH'
], ['MS-TRA-SEN-LANH'=>6,'MS-CHE-SEN-LONG-NHAN'=>2]);
$compCount += insertComboComponents('MS-SET-CHAY', [
    'MS-DAU-HU-RANG-MUOI','MS-DAU-HU-SOT-NAM','MS-NAM-KHO-TIEU',
    'MS-CANH-RAU-CU-HAT-SEN','MS-TAU-HU-NUOC-DUONG','MS-TRA-SEN-NONG'
], ['MS-TRA-SEN-NONG'=>2]);
echo 'Combo components inserted: '.$compCount.PHP_EOL;

// ---------------------------------------------------------------------------
// 6. Final counts
// NOTE: Step 1a already hid old conflicting categories (id=1, id=2).
//       Do NOT run any further cleanup on id=1 here — it is now 'Mon Chinh'.
// ---------------------------------------------------------------------------
$finalItems = DB::table('menu_items')->where('is_available', 1)->count();
$finalBS = DB::table('menu_items')->where('is_best_seller', 1)->count();
$finalCombo = DB::table('menu_items')->where('is_combo', 1)->count();
$finalComps = DB::table('menu_item_combo_components')->count();
$finalCats = DB::table('menu_categories')->where('is_deleted', 0)->count();
$finalPrices = DB::table('menu_item_prices')
    ->join('menu_items', 'menu_item_prices.item_id', '=', 'menu_items.item_id')
    ->where('menu_items.is_available', 1)
    ->whereNull('menu_item_prices.effective_to')
    ->count();

echo PHP_EOL;
echo '=== FINAL COUNTS ==='.PHP_EOL;
echo "  Items available   : $finalItems  (expect 66)".PHP_EOL;
echo "  Best sellers      : $finalBS  (expect 13)".PHP_EOL;
echo "  Set/Combo         : $finalCombo  (expect 6)".PHP_EOL;
echo "  Combo components  : $finalComps  (expect 41)".PHP_EOL;
echo "  Active categories : $finalCats  (expect 8)".PHP_EOL;
echo "  Active prices     : $finalPrices  (expect 66)".PHP_EOL;

if ($finalItems >= 60 && $finalBS >= 9 && $finalCombo === 6 && $finalComps >= 30 && $finalCats === 8) {
    echo PHP_EOL.'  ✅ SUCCESS'.PHP_EOL;
} else {
    echo PHP_EOL.'  ⚠️  CHECK ABOVE — some counts off'.PHP_EOL;
}
