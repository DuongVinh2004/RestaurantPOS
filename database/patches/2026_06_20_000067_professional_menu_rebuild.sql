-- =============================================================================
-- PATCH 000067: Professional Menu Rebuild - Moc Sen Bistro
-- 66 mon: 8 Khai Vi + 5 Canh & Sup + 12 Mon Chinh + 8 Com & Mi
--       + 7 Rau & Chay + 7 Trang Miem + 13 Do Uong + 6 Set/Combo
-- =============================================================================
SET NAMES utf8mb4;

-- 1. CATEGORIES
INSERT INTO `menu_categories` (`name`, `description`, `sort_order`, `is_deleted`) VALUES
  ('Khai Vi',    'Mon khai vi nhe, de chia se - mo dau hoan hao tai Moc Sen.',          10, 0),
  ('Canh & Sup', 'Canh dan da va sup sanh min - khong the thieu trong bua Viet.',        20, 0),
  ('Mon Chinh',  'Tinh hoa am thuc Viet tu bep Moc Sen.',                                30, 0),
  ('Com & Mi',   'Phan an no quen thuoc - phu hop bua trua van phong va bua toi nhe.',  40, 0),
  ('Rau & Chay', 'Thuc vat tuoi, nam thom va lua chon chay thanh dam.',                  50, 0),
  ('Trang Miem', 'Ket thuc ngot ngao voi dessert Viet va fusion nhe.',                   60, 0),
  ('Do Uong',    'Do uong thu cong dac trung cua Moc Sen - tra sen la linh hon.',        70, 0),
  ('Set & Combo','Bo set duoc bep truong lua chon - tiet kiem thoi gian, tron ven.',    80, 0)
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `sort_order`  = VALUES(`sort_order`),
  `is_deleted`  = VALUES(`is_deleted`);

-- An danh muc cu (ten khac viet thuong)
UPDATE `menu_categories` SET `is_deleted` = 1
WHERE `name` IN ('Mon chinh','Com & bun/pho','Rau & chay','Trang miem','Do uong','Combo','Khai vi','Khai Vo');

-- 2. TEMP TABLE
DROP TEMPORARY TABLE IF EXISTS `tmp_ms_v2`;
CREATE TEMPORARY TABLE `tmp_ms_v2` (
  `code`          varchar(60)   NOT NULL,
  `cat`           varchar(80)   NOT NULL,
  `name`          varchar(200)  NOT NULL,
  `desc`          varchar(500)  NOT NULL,
  `price`         decimal(14,2) NOT NULL,
  `cmp_price`     decimal(14,2) DEFAULT NULL,
  `img`           varchar(255)  NOT NULL,
  `is_avail`      tinyint       NOT NULL DEFAULT 1,
  `is_preorder`   tinyint       NOT NULL DEFAULT 1,
  `is_bestseller` tinyint       NOT NULL DEFAULT 0,
  `is_combo`      tinyint       NOT NULL DEFAULT 0,
  `quota`         int           NOT NULL DEFAULT 50,
  `cutoff`        int           NOT NULL DEFAULT 0,
  `ssize`         int           DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=Memory DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. INSERT MON AN
INSERT INTO `tmp_ms_v2` VALUES
-- KHAI VI
('MS-GOI-CUON-TOM-THIT','Khai Vi','Goi cuon tom thit','Tom su tuoi, thit ba chi luoc, bun manh, rau song va nuoc sot dau phong rang thom. 2 cuon/phan.',65000,NULL,'/customer-web/menu/goi-cuon-tom-thit.jpg',1,1,1,0,90,0,2),
('MS-NEM-SEN-GION','Khai Vi','Nem sen gion','Nem chien gion nhan thit heo, nam meo, mien va cu sen - linh hon cua Moc Sen. Dung kem nuoc mam chua ngot.',75000,NULL,'/customer-web/menu/nem-sen-gion.jpg',1,1,1,0,80,0,3),
('MS-GOI-NGO-SEN-BO-TAI','Khai Vi','Goi ngo sen bo tai','Ngo sen gion, bo tai chanh, hanh tay, me rang va sot Moc Sen dac biet - mon ky hieu thuong hieu.',89000,NULL,'/customer-web/menu/goi-ngo-sen-bo-tai.jpg',1,1,1,0,60,0,2),
('MS-GOI-GA-BAP-CHUOI','Khai Vi','Goi ga bap chuoi','Ga xe phay, bap chuoi non thai mong, rau ram, hanh phi va nuoc mam chua ngot dac trung.',79000,NULL,'/customer-web/menu/goi-ga-bap-chuoi.jpg',1,1,0,0,70,0,2),
('MS-CHA-GIO-HAI-SAN','Khai Vi','Cha gio hai san','Cuon hai san thap cam chien gion, nhan tom muc cua. An kem rau song va sot Moc Sen.',89000,NULL,'/customer-web/menu/cha-gio-hai-san.jpg',1,1,0,0,60,0,3),
('MS-SALAD-XOAI-TOM','Khai Vi','Salad xoai tom','Xoai xanh thai soi, tom su ap chao, dau phong rang, rau thom va sot chua ngot kieu Thai.',79000,NULL,'/customer-web/menu/salad-xoai-tom.jpg',1,1,0,0,70,0,2),
('MS-CHA-MUC-MINI','Khai Vi','Cha muc mini Moc Sen','Cha muc gia tay thu cong, khong chat bao quan, chien vang deu. Cham tuong ot Moc Sen tu lam.',95000,NULL,'/customer-web/menu/cha-muc-mini.jpg',1,1,0,0,50,0,4),
('MS-DAU-HU-RANG-MUOI','Khai Vi','Dau hu rang muoi sa','Dau hu non ao bot mong, rang vang voi muoi sa ot - gion ngoai, mem trong. Phu hop an chay.',55000,NULL,'/customer-web/menu/dau-hu-rang-muoi.jpg',1,1,0,0,80,0,2),
-- CANH & SUP
('MS-CANH-CHUA-CA-LOC','Canh & Sup','Canh chua ca loc','Nuoc dung chua thanh tu me, ca loc phi-le tuoi, ca chua, thom, gia do va ngo - dam chat mien Tay.',69000,NULL,'/customer-web/menu/canh-chua-ca-loc.jpg',1,1,1,0,60,0,1),
('MS-CANH-KHO-QUA-NHOI','Canh & Sup','Canh kho qua nhoi thit','Kho qua tuoi nhoi thit heo xay, nau nuoc dung trong - dang diu, ngot sau, thanh loc.',65000,NULL,'/customer-web/menu/canh-kho-qua-nhoi.jpg',1,1,0,0,60,0,1),
('MS-SUP-BI-DO-TOM','Canh & Sup','Sup bi do tom tuoi','Bi do xay min sanh vang, tom su tuoi nguyen con, kem tuoi va hanh la - sup dau bua tinh te.',59000,NULL,'/customer-web/menu/sup-bi-do-tom.jpg',1,1,0,0,70,0,1),
('MS-CANH-RAU-NGOT-THIT','Canh & Sup','Canh rau ngot thit heo','Rau ngot tuoi hai ngay, thit heo xay, nuoc dung ngot nhe - bua com thuan Viet chuan vi nha.',45000,NULL,'/customer-web/menu/canh-rau-ngot-thit.jpg',1,1,0,0,80,0,1),
('MS-CANH-RAU-CU-HAT-SEN','Canh & Sup','Canh rau cu hat sen','Hat sen tuoi, ca rot, su hao, nam huong - nuoc dung rau cu nhe, phu hop nguoi an chay.',55000,NULL,'/customer-web/menu/canh-rau-cu-hat-sen.jpg',1,1,0,0,70,0,1),
-- MON CHINH
('MS-COM-GA-LA-SEN','Mon Chinh','Com ga la sen','Ga ta ap chao da gion, com deo nau voi nuoc cot ga, sot gung mat ong, rau cu theo mua. Dac trung Moc Sen.',95000,NULL,'/customer-web/menu/com-ga-la-sen.jpg',1,1,1,0,90,0,1),
('MS-BUN-BO-MOC-SEN','Mon Chinh','Bun bo Moc Sen','Nuoc dung ham xuong 6 gio, thit bo bap mem, gio heo, mam ruoc va sa te tu lam. Signature dish.',99000,NULL,'/customer-web/menu/bun-bo-moc-sen.jpg',1,1,1,0,90,0,1),
('MS-CA-KHO-NIEU-DAT','Mon Chinh','Ca kho nieu dat','Ca loc kho nuoc mau dua, tieu hat, nuoc mam nguyen chat trong nieu dat nung - an kem com trang nong.',119000,NULL,'/customer-web/menu/ca-kho-nieu-dat.jpg',1,1,1,0,50,30,1),
('MS-BO-LUC-LAC-SOT-TIEU','Mon Chinh','Bo luc lac sot tieu xanh','Than bo Uc ap chao, khoai tay chien bo, salad ca chua va sot tieu xanh Madagascar - bistro chuan.',149000,NULL,'/customer-web/menu/bo-luc-lac-sot-tieu.jpg',1,1,0,0,60,0,1),
('MS-GA-NUONG-MAT-ONG','Mon Chinh','Ga nuong mat ong nghe','Nua ga ta uop mat ong, nghe tuoi, xa, nuong than hoa deu lua. Rau cu nuong kem, sot tac mat ong.',135000,NULL,'/customer-web/menu/ga-nuong-mat-ong.jpg',1,1,0,0,55,60,1),
('MS-TOM-SOT-ME','Mon Chinh','Tom sot me Moc Sen','Tom su lon ap chao voi bo toi, sot me chua ngot tu lam, hanh phi gion va ot tuoi.',159000,NULL,'/customer-web/menu/tom-sot-me.jpg',1,1,0,0,50,0,1),
('MS-SUON-NON-RIM-MAM','Mon Chinh','Suon non rim mam toi','Suon non heo rim mam toi ngot man hai hoa, an kem dua leo ngam chua va com trang nong.',129000,NULL,'/customer-web/menu/suon-non-rim-mam.jpg',1,1,0,0,60,30,1),
('MS-VIT-AP-CHAO-SOT-ME','Mon Chinh','Vit ap chao sot me','Uc vit ap chao da gion ron, sot me chua ngot dac, rau thom va hanh tay ngam giam.',165000,NULL,'/customer-web/menu/vit-ap-chao-sot-me.jpg',1,1,0,0,40,0,1),
('MS-MUC-XAO-SA-TE','Mon Chinh','Muc xao sa te rau cu','Muc ong cat khoanh xao lua to voi ca chua, hanh tay, ot chuong va sa te tu lam - cay nhe thom.',145000,NULL,'/customer-web/menu/muc-xao-sa-te.jpg',1,1,0,0,55,0,1),
('MS-CA-CHIEN-MAM-XOAI','Mon Chinh','Ca chien mam xoai','Ca dieu hong phi-le chien gion, cham mam xoai xanh tu ngam, rau song an kem.',145000,NULL,'/customer-web/menu/ca-chien-mam-xoai.jpg',1,1,0,0,55,0,1),
('MS-BO-KHO-BANH-MI','Mon Chinh','Bo kho banh mi nong','Bo bap ham sa te hoa hoi, ca rot mem vua mieng, nuoc sot sanh do - an kem banh mi lo nong gion.',99000,NULL,'/customer-web/menu/bo-kho-banh-mi.jpg',1,1,0,0,70,0,1),
('MS-SUON-BO-NUONG-BISTRO','Mon Chinh','Suon bo nuong kieu bistro','Suon bo nuong lo, bo thao moc, khoai tay nghien bo sua, nam sot vang do - mon premium toi cuoi tuan.',189000,NULL,'/customer-web/menu/suon-bo-nuong-bistro.jpg',1,1,0,0,30,120,1),
-- COM & MI
('MS-PHO-GA-THAO-MOC','Com & Mi','Pho ga thao moc','Nuoc dung ham ga ta voi thao moc, ga xe mem, banh pho soi manh, rau thom va chanh ot tuoi.',79000,NULL,'/customer-web/menu/pho-ga-thao-moc.jpg',1,1,1,0,80,0,1),
('MS-BUN-CHA-HA-NOI','Com & Mi','Bun cha Ha Noi','Thit cha nuong than, thit heo vien, nuoc cham pha chuan Ha Noi - an kem bun tuoi va rau song.',89000,NULL,'/customer-web/menu/bun-cha-ha-noi.jpg',1,1,1,0,90,0,1),
('MS-COM-SUON-MAT-ONG','Com & Mi','Com suon mat ong trung op','Suon cot-let nuong mat ong, com trang, trung op-la, dua leo va do chua Moc Sen - com dia van phong dinh.',99000,NULL,'/customer-web/menu/com-suon-mat-ong.jpg',1,1,0,0,80,0,1),
('MS-MI-XAO-BO-RAU-CU','Com & Mi','Mi xao bo rau cu','Mi trung xao lua to, bo thai mong, ot chuong, hanh tay, ca rot - sot hau hai vi dam da.',95000,NULL,'/customer-web/menu/mi-xao-bo-rau-cu.jpg',1,1,0,0,80,0,1),
('MS-BUN-THIT-NUONG','Com & Mi','Bun thit nuong dac biet','Thit nuong than mo hanh, cha gio chien, bun, rau song day du, do chua va nuoc mam Moc Sen.',89000,NULL,'/customer-web/menu/bun-thit-nuong.jpg',1,1,0,0,90,0,1),
('MS-COM-BO-XAO-SATE','Com & Mi','Com bo xao sa te','Bo Uc thai lat xao sa te cay nhe, com trang, dua leo, do chua - bua trua du chat.',109000,NULL,'/customer-web/menu/com-bo-xao-sate.jpg',1,1,0,0,70,0,1),
('MS-MIEN-GA-NAM','Com & Mi','Mien ga nam huong','Mien dong dai, ga ta xe, nam huong kho ngam no, nuoc dung ga thanh ngot, hanh la va tieu.',79000,NULL,'/customer-web/menu/mien-ga-nam.jpg',1,1,0,0,75,0,1),
('MS-COM-CHIEN-HAI-SAN','Com & Mi','Com chien hai san Moc Sen','Com chien tom muc, trung ga, hanh la, ca rot - chien lua to, hat com roi, hai san ngot tuoi.',95000,NULL,'/customer-web/menu/com-chien-hai-san.jpg',1,1,0,0,75,0,1),
-- RAU & CHAY
('MS-RAU-CU-XAO-TOI','Rau & Chay','Rau cu xao toi','Rau theo mua (cai ngot, bong cai, dau co-ve) xao toi bam thom, lua to giu gion.',55000,NULL,'/customer-web/menu/rau-cu-xao-toi.jpg',1,1,0,0,90,0,2),
('MS-DAU-HU-SOT-NAM','Rau & Chay','Dau hu non sot nam','Dau hu non cat mieng, sot nam dong co va nam rom tuoi - dam thuc vat giau dinh duong.',65000,NULL,'/customer-web/menu/dau-hu-sot-nam.jpg',1,1,0,0,70,0,2),
('MS-NAM-KHO-TIEU','Rau & Chay','Nam kho tieu den','Nam bao ngu, nam dong co kho tuong hoisin, tieu den va hanh boa-ro - an kem com nong.',69000,NULL,'/customer-web/menu/nam-kho-tieu.jpg',1,1,0,0,70,0,2),
('MS-GOI-RAU-MAM-BO','Rau & Chay','Goi rau mam bo ap chao','Rau mam tuoi, bo thai mong ap chao tai hong, sot me rang va dau hao - salad A tinh te.',89000,NULL,'/customer-web/menu/goi-rau-mam-bo.jpg',1,1,0,0,70,0,2),
('MS-CA-TIM-NUONG-MO-HANH','Rau & Chay','Ca tim nuong mo hanh','Ca tim nuong mem thom, mo hanh phi vang, dau phong rang gia tho, nuoc mam chay chua ngot.',59000,NULL,'/customer-web/menu/ca-tim-nuong-mo-hanh.jpg',1,1,0,0,70,0,1),
('MS-DAU-BAP-XAO-TOI','Rau & Chay','Dau bap xao toi','Dau bap non chon loc xao toi lua to, giu do gion va vi ngot tu nhien cua rau.',52000,NULL,'/customer-web/menu/dau-bap-xao-toi.jpg',1,1,0,0,80,0,2),
('MS-NOM-HOA-CHUOI-CHAY','Rau & Chay','Nom hoa chuoi chay','Hoa chuoi thai, dau phong, me, rau thom va nuoc tron chua ngot chay - thanh mat giai nhiet.',59000,NULL,'/customer-web/menu/nom-hoa-chuoi-chay.jpg',1,1,0,0,75,0,2),
-- TRANG MIEM
('MS-CHE-SEN-LONG-NHAN','Trang Miem','Che sen long nhan','Hat sen nau mem vua mieng, long nhan ngot thanh, duong phen - dung lanh. Dac san Moc Sen.',49000,NULL,'/customer-web/menu/che-sen-long-nhan.jpg',1,1,1,0,90,0,1),
('MS-PANNA-COTTA-DUA','Trang Miem','Panna cotta dua xoai','Kem dua Thai sanh min, sot xoai chin chua nhe va lat xoai tuoi - fusion Viet-Au tinh te.',55000,NULL,'/customer-web/menu/panna-cotta-dua.jpg',1,1,0,0,70,0,1),
('MS-BANH-FLAN-CA-PHE','Trang Miem','Banh flan ca phe','Flan min pha ca phe robusta dam, lop caramel chay nhe - ket thuc hoan hao cho bua toi.',45000,NULL,'/customer-web/menu/banh-flan-ca-phe.jpg',1,1,1,0,90,0,1),
('MS-KEM-DUA-NON','Trang Miem','Kem dua non','Kem dua tuoi trong trai dua non, kem thach dua, dau phong rang - giai nhiet mua he.',59000,NULL,'/customer-web/menu/kem-dua-non.jpg',1,1,0,0,60,0,1),
('MS-SUA-CHUA-NEP-CAM','Trang Miem','Sua chua nep cam mat ong','Sua chua Hy Lap min, nep cam deo tim, mat ong hoa nhan va hat chia - lanh manh, ngon mieng.',49000,NULL,'/customer-web/menu/sua-chua-nep-cam.jpg',1,1,0,0,80,0,1),
('MS-BANH-CHUOI-NUONG','Trang Miem','Banh chuoi nuong nuoc cot dua','Chuoi su chin nuong than thom, chan nuoc cot dua am, me rang va duong thot not.',49000,NULL,'/customer-web/menu/banh-chuoi-nuong.jpg',1,1,0,0,75,0,1),
('MS-TAU-HU-NUOC-DUONG','Trang Miem','Tau hu nuoc duong gung','Tau hu non min, nuoc duong gung am thom, tran chau nho va la pandan - chay 100%.',42000,NULL,'/customer-web/menu/tau-hu-nuoc-duong.jpg',1,1,0,0,90,0,1),
-- DO UONG
('MS-TRA-SEN-LANH','Do Uong','Tra sen uop tuoi lanh','Tra oolong uop hoa sen tuoi cat buoi sang, pha nguoi va rot da - linh hon cua Moc Sen.',39000,NULL,'/customer-web/menu/tra-sen-lanh.jpg',1,1,1,0,150,0,1),
('MS-TRA-SEN-NONG','Do Uong','Tra sen nong','Tra oolong uop sen pha binh, dung nong - thich hop mua dong hay khong khi may lanh.',35000,NULL,'/customer-web/menu/tra-sen-nong.jpg',1,1,0,0,120,0,1),
('MS-CA-PHE-SUA-DA','Do Uong','Ca phe sua da','Ca phe phin robusta rang dam, sua dac ong Tho, da vien - chuan ca phe Viet truyen thong.',39000,NULL,'/customer-web/menu/ca-phe-sua-da.jpg',1,1,0,0,120,0,1),
('MS-CA-PHE-DEN-DA','Do Uong','Ca phe den da','Ca phe phin nguyen chat, khong sua, pha loang vua du, da to giu lanh lau.',29000,NULL,'/customer-web/menu/ca-phe-den-da.jpg',1,1,0,0,120,0,1),
('MS-NUOC-CHANH-SA','Do Uong','Nuoc chanh sa mat ong','Chanh vat tuoi, sa dap, mat ong hoa nhan - thanh mat, giai nhiet, nhe ngot tu nhien.',42000,NULL,'/customer-web/menu/nuoc-chanh-sa.jpg',1,1,0,0,110,0,1),
('MS-TRA-TAC-MAT-ONG','Do Uong','Tra tac mat ong da','Tra xanh pha voi tac (quat) tuoi vat, mat ong, da vien - chua diu, thom mat.',42000,NULL,'/customer-web/menu/tra-tac-mat-ong.jpg',1,1,0,0,110,0,1),
('MS-SINH-TO-XOAI','Do Uong','Sinh to xoai cat','Xoai cat Hoa Loc chin, sua chua tuoi, da xay - dac quanh, ngot diu, khong pha them duong.',59000,NULL,'/customer-web/menu/sinh-to-xoai.jpg',1,1,0,0,80,0,1),
('MS-SINH-TO-BO-SUA','Do Uong','Sinh to bo sua','Bo sap Dak Lak chin, sua tuoi khong duong, duong phen nhe, da bao - beo min, no lau.',65000,NULL,'/customer-web/menu/sinh-to-bo-sua.jpg',1,1,0,0,70,0,1),
('MS-NUOC-EP-CAM-CA-ROT','Do Uong','Nuoc ep cam ca rot tuoi','Cam sanh va ca rot ep ngay khi order, khong duong khong bao quan - vitamin C tu nhien.',52000,NULL,'/customer-web/menu/nuoc-ep-cam-ca-rot.jpg',1,1,0,0,90,0,1),
('MS-TRA-DAO-CAM-SA','Do Uong','Tra dao cam sa','Dao ngam, cam tuoi, sa thom, tra xanh Thai Nguyen, da vien - nhe nhang, thom trai cay.',49000,NULL,'/customer-web/menu/tra-dao-cam-sa.jpg',1,1,0,0,100,0,1),
('MS-NUOC-DUA-TUOI','Do Uong','Nuoc dua tuoi','Dua xiem cat phuc vu nguyen trai - mat lanh tu nhien, ngot thanh dac trung.',45000,NULL,'/customer-web/menu/nuoc-dua-tuoi.jpg',1,1,0,0,80,0,1),
('MS-NUOC-SUOI','Do Uong','Nuoc suoi / Soda','Nuoc suoi Vinh Hao hoac soda lanh - lua chon don gian di kem bua an.',22000,NULL,'/customer-web/menu/nuoc-suoi-soda.jpg',1,0,0,0,200,0,1),
('MS-BIA-CHAI','Do Uong','Bia Sai Gon lanh','Bia Sai Gon chai 330ml uop lanh - di kem bua toi hoac gap go ban be.',25000,NULL,'/customer-web/menu/bia-chai.jpg',1,0,0,0,200,0,1),
-- SET & COMBO
('MS-SET-TRUA-VP','Set & Combo','Set Trua Van Phong','1 mon com/mi tu chon + 1 to canh nho + 1 tra sen lanh. Giao tu 11h. Thay doi theo ngay.',149000,185000,'/customer-web/menu/set-trua-van-phong.jpg',1,1,1,1,60,0,1),
('MS-SET-GIA-DINH','Set & Combo','Set Gia Dinh Moc Sen (4 nguoi)','4 mon chinh (ga/ca/bo/tom luan phien), 1 canh, 1 rau xao, 1 trang miem + 4 do uong. Phuc vu 4 nguoi.',449000,580000,'/customer-web/menu/set-gia-dinh-moc-sen.jpg',1,1,0,1,30,60,4),
('MS-SET-HEN-HO','Set & Combo','Set Hen Ho Ben Cua So (2 nguoi)','Khai vi chia se, 2 mon chinh premium, 2 do uong signature Moc Sen, 1 trang miem. Ban Window Zone.',329000,415000,'/customer-web/menu/set-hen-ho-ben-cua-so.jpg',1,1,0,1,25,60,2),
('MS-SET-BEP-TRUONG','Set & Combo','Set Bep Truong De Xuat (4 nguoi)','Combo cao cap 7 mon theo mua do bep truong lua chon. Thay doi theo tuan. Dat truoc 2 gio.',499000,650000,'/customer-web/menu/set-bep-truong-de-xuat.jpg',1,1,0,1,20,120,4),
('MS-SET-CUOI-TUAN','Set & Combo','Set Cuoi Tuan Dai Gia Dinh (6 nguoi)','6 mon chinh, 2 rau xao, 1 canh, 2 trang miem + 6 do uong. Phuc vu 6 nguoi. Dat truoc 3 gio.',799000,1050000,'/customer-web/menu/set-cuoi-tuan.jpg',1,1,0,1,15,180,6),
('MS-SET-CHAY','Set & Combo','Set Chay Moc Sen (2 nguoi)','1 khai vi chay, 2 mon chay tu chon, 1 canh rau, 1 trang miem chay + 2 tra sen nong. 100% thuc vat.',249000,310000,'/customer-web/menu/set-chay-moc-sen.jpg',1,1,0,1,25,0,2);

-- 4. UPSERT vao menu_items
INSERT INTO `menu_items` (
  `category_id`,`code`,`name`,`description`,`img_url`,
  `is_available`,`is_preorder_enabled`,`is_best_seller`,`is_combo`,
  `preorder_quota_per_day`,`preorder_cutoff_minutes`,`compare_at_price_amount`,`serving_size`
)
SELECT c.`category_id`, m.`code`, m.`name`, m.`desc`, m.`img`,
  m.`is_avail`, m.`is_preorder`, m.`is_bestseller`, m.`is_combo`,
  m.`quota`, m.`cutoff`, m.`cmp_price`, m.`ssize`
FROM `tmp_ms_v2` m
JOIN `menu_categories` c ON c.`name` = m.`cat` AND c.`is_deleted` = 0
ON DUPLICATE KEY UPDATE
  `category_id`             = VALUES(`category_id`),
  `name`                    = VALUES(`name`),
  `description`             = VALUES(`description`),
  `img_url`                 = VALUES(`img_url`),
  `is_available`            = VALUES(`is_available`),
  `is_preorder_enabled`     = VALUES(`is_preorder_enabled`),
  `is_best_seller`          = VALUES(`is_best_seller`),
  `is_combo`                = VALUES(`is_combo`),
  `preorder_quota_per_day`  = VALUES(`preorder_quota_per_day`),
  `preorder_cutoff_minutes` = VALUES(`preorder_cutoff_minutes`),
  `compare_at_price_amount` = VALUES(`compare_at_price_amount`),
  `serving_size`            = VALUES(`serving_size`);

-- 5. GIA MOI (chi insert neu chua co gia hieu luc)
INSERT INTO `menu_item_prices` (`item_id`,`price`,`currency`,`effective_from`,`effective_to`)
SELECT mi.`item_id`, m.`price`, 'VND', '2026-06-20 00:00:00.000000', NULL
FROM `tmp_ms_v2` m
JOIN `menu_items` mi ON mi.`code` = m.`code`
WHERE NOT EXISTS (
  SELECT 1 FROM `menu_item_prices` p
  WHERE p.`item_id` = mi.`item_id` AND p.`currency` = 'VND' AND p.`effective_to` IS NULL
);

-- 6. COMBO COMPONENTS
INSERT IGNORE INTO `menu_item_combo_components` (`combo_item_id`,`component_item_id`,`quantity`)
SELECT co.`item_id`, cp.`item_id`, 1 FROM `menu_items` co JOIN `menu_items` cp
ON co.`code` = 'MS-SET-TRUA-VP' AND cp.`code` IN ('MS-COM-SUON-MAT-ONG','MS-CANH-RAU-NGOT-THIT','MS-TRA-SEN-LANH');

INSERT IGNORE INTO `menu_item_combo_components` (`combo_item_id`,`component_item_id`,`quantity`)
SELECT co.`item_id`, cp.`item_id`, CASE cp.`code` WHEN 'MS-CHE-SEN-LONG-NHAN' THEN 2 WHEN 'MS-TRA-SEN-LANH' THEN 4 ELSE 1 END
FROM `menu_items` co JOIN `menu_items` cp ON co.`code` = 'MS-SET-GIA-DINH'
AND cp.`code` IN ('MS-COM-GA-LA-SEN','MS-BO-LUC-LAC-SOT-TIEU','MS-TOM-SOT-ME','MS-CA-KHO-NIEU-DAT','MS-CANH-CHUA-CA-LOC','MS-RAU-CU-XAO-TOI','MS-CHE-SEN-LONG-NHAN','MS-TRA-SEN-LANH');

INSERT IGNORE INTO `menu_item_combo_components` (`combo_item_id`,`component_item_id`,`quantity`)
SELECT co.`item_id`, cp.`item_id`, CASE cp.`code` WHEN 'MS-TRA-SEN-LANH' THEN 2 ELSE 1 END
FROM `menu_items` co JOIN `menu_items` cp ON co.`code` = 'MS-SET-HEN-HO'
AND cp.`code` IN ('MS-GOI-NGO-SEN-BO-TAI','MS-BO-LUC-LAC-SOT-TIEU','MS-VIT-AP-CHAO-SOT-ME','MS-PANNA-COTTA-DUA','MS-TRA-SEN-LANH');

INSERT IGNORE INTO `menu_item_combo_components` (`combo_item_id`,`component_item_id`,`quantity`)
SELECT co.`item_id`, cp.`item_id`, 1
FROM `menu_items` co JOIN `menu_items` cp ON co.`code` = 'MS-SET-BEP-TRUONG'
AND cp.`code` IN ('MS-NEM-SEN-GION','MS-GOI-CUON-TOM-THIT','MS-CA-KHO-NIEU-DAT','MS-SUON-BO-NUONG-BISTRO','MS-TOM-SOT-ME','MS-CANH-CHUA-CA-LOC','MS-CHE-SEN-LONG-NHAN');

INSERT IGNORE INTO `menu_item_combo_components` (`combo_item_id`,`component_item_id`,`quantity`)
SELECT co.`item_id`, cp.`item_id`, CASE cp.`code` WHEN 'MS-TRA-SEN-LANH' THEN 6 WHEN 'MS-CHE-SEN-LONG-NHAN' THEN 2 ELSE 1 END
FROM `menu_items` co JOIN `menu_items` cp ON co.`code` = 'MS-SET-CUOI-TUAN'
AND cp.`code` IN ('MS-COM-GA-LA-SEN','MS-BO-LUC-LAC-SOT-TIEU','MS-TOM-SOT-ME','MS-VIT-AP-CHAO-SOT-ME','MS-GA-NUONG-MAT-ONG','MS-MUC-XAO-SA-TE','MS-CANH-CHUA-CA-LOC','MS-RAU-CU-XAO-TOI','MS-NAM-KHO-TIEU','MS-CHE-SEN-LONG-NHAN','MS-BANH-FLAN-CA-PHE','MS-TRA-SEN-LANH');

INSERT IGNORE INTO `menu_item_combo_components` (`combo_item_id`,`component_item_id`,`quantity`)
SELECT co.`item_id`, cp.`item_id`, CASE cp.`code` WHEN 'MS-TRA-SEN-NONG' THEN 2 ELSE 1 END
FROM `menu_items` co JOIN `menu_items` cp ON co.`code` = 'MS-SET-CHAY'
AND cp.`code` IN ('MS-DAU-HU-RANG-MUOI','MS-DAU-HU-SOT-NAM','MS-NAM-KHO-TIEU','MS-CANH-RAU-CU-HAT-SEN','MS-TAU-HU-NUOC-DUONG','MS-TRA-SEN-NONG');

-- 7. AN MON CU TRUNG TEN
UPDATE `menu_items` SET `is_available` = 0
WHERE `code` IN ('MS-SET-TRUA-VAN-PHONG','MS-SET-GIA-DINH-MOC-SEN','MS-SET-HEN-HO-BEN-CUA-SO','MS-SET-BEP-TRUONG-DE-XUAT');

DROP TEMPORARY TABLE IF EXISTS `tmp_ms_v2`;

-- KIEM TRA KET QUA
SELECT CONCAT(
  'DONE: ',
  (SELECT COUNT(*) FROM menu_items WHERE is_available=1),' mon hien co | ',
  (SELECT COUNT(*) FROM menu_items WHERE is_best_seller=1),' best seller | ',
  (SELECT COUNT(*) FROM menu_items WHERE is_combo=1),' set/combo | ',
  (SELECT COUNT(*) FROM menu_item_combo_components),' combo components'
) AS ket_qua;
