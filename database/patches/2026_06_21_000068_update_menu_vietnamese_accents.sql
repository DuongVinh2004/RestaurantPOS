-- =============================================================================
-- PATCH 000068: Update Menu to Vietnamese with Accents - Moc Sen Bistro
-- =============================================================================
SET NAMES utf8mb4;

-- 1. CATEGORIES
-- Remove old categories from 000064 to prevent unique constraint violations during update
-- Rename instead of Delete to prevent foreign key violations on kitchen_station_category_routes
UPDATE `menu_categories` SET `name` = CONCAT(`name`, '_OLD') WHERE `name` IN ('Khai vị', 'Món chính', 'Cơm & bún/phở', 'Rau & chay', 'Tráng miệng', 'Đồ uống', 'Combo');

UPDATE `menu_categories` SET `name` = 'Khai Vị', `description` = 'Món khai vị nhẹ, để chia sẻ - mở đầu hoàn hảo tại Mộc Sen.' WHERE `name` = 'Khai Vi';
UPDATE `menu_categories` SET `name` = 'Canh & Súp', `description` = 'Canh dân dã và súp sánh mịn - không thể thiếu trong bữa Việt.' WHERE `name` = 'Canh & Sup';
UPDATE `menu_categories` SET `name` = 'Món Chính', `description` = 'Tinh hoa ẩm thực Việt từ bếp Mộc Sen.' WHERE `name` = 'Mon Chinh';
UPDATE `menu_categories` SET `name` = 'Cơm & Mì', `description` = 'Phần ăn no quen thuộc - phù hợp bữa trưa văn phòng và bữa tối nhẹ.' WHERE `name` = 'Com & Mi';
UPDATE `menu_categories` SET `name` = 'Rau & Chay', `description` = 'Thực vật tươi, nấm thơm và lựa chọn chay thanh đạm.' WHERE `name` = 'Rau & Chay';
UPDATE `menu_categories` SET `name` = 'Tráng Miệng', `description` = 'Kết thúc ngọt ngào với dessert Việt và fusion nhẹ.' WHERE `name` = 'Trang Miem';
UPDATE `menu_categories` SET `name` = 'Đồ Uống', `description` = 'Đồ uống thủ công đặc trưng của Mộc Sen - trà sen là linh hồn.' WHERE `name` = 'Do Uong';
UPDATE `menu_categories` SET `name` = 'Set & Combo', `description` = 'Bộ set được bếp trưởng lựa chọn - tiết kiệm thời gian, trọn vẹn.' WHERE `name` = 'Set & Combo';

-- 2. MENU ITEMS
-- KHAI VI
UPDATE `menu_items` SET `name` = 'Gỏi cuốn tôm thịt', `description` = 'Tôm sú tươi, thịt ba chỉ luộc, bún mảnh, rau sống và nước xốt đậu phộng rang thơm. 2 cuốn/phần.' WHERE `code` = 'MS-GOI-CUON-TOM-THIT';
UPDATE `menu_items` SET `name` = 'Nem sen giòn', `description` = 'Nem chiên giòn nhân thịt heo, nấm mèo, miến và củ sen - linh hồn của Mộc Sen. Dùng kèm nước mắm chua ngọt.' WHERE `code` = 'MS-NEM-SEN-GION';
UPDATE `menu_items` SET `name` = 'Gỏi ngó sen bò tái', `description` = 'Ngó sen giòn, bò tái chanh, hành tây, mè rang và xốt Mộc Sen đặc biệt - món signature thương hiệu.' WHERE `code` = 'MS-GOI-NGO-SEN-BO-TAI';
UPDATE `menu_items` SET `name` = 'Gỏi gà bắp chuối', `description` = 'Gà xé phay, bắp chuối non thái mỏng, rau răm, hành phi và nước mắm chua ngọt đặc trưng.' WHERE `code` = 'MS-GOI-GA-BAP-CHUOI';
UPDATE `menu_items` SET `name` = 'Chả giò hải sản', `description` = 'Cuốn hải sản thập cẩm chiên giòn, nhân tôm mực cua. Ăn kèm rau sống và xốt Mộc Sen.' WHERE `code` = 'MS-CHA-GIO-HAI-SAN';
UPDATE `menu_items` SET `name` = 'Salad xoài tôm', `description` = 'Xoài xanh thái sợi, tôm sú áp chảo, đậu phộng rang, rau thơm và xốt chua ngọt kiểu Thái.' WHERE `code` = 'MS-SALAD-XOAI-TOM';
UPDATE `menu_items` SET `name` = 'Chả mực mini Mộc Sen', `description` = 'Chả mực giã tay thủ công, không chất bảo quản, chiên vàng đều. Chấm tương ớt Mộc Sen tự làm.' WHERE `code` = 'MS-CHA-MUC-MINI';
UPDATE `menu_items` SET `name` = 'Đậu hũ rang muối sả', `description` = 'Đậu hũ non áo bột mỏng, rang vàng với muối sả ớt - giòn ngoài, mềm trong. Phù hợp ăn chay.' WHERE `code` = 'MS-DAU-HU-RANG-MUOI';

-- CANH & SUP
UPDATE `menu_items` SET `name` = 'Canh chua cá lóc', `description` = 'Nước dùng chua thanh từ me, cá lóc phi-lê tươi, cà chua, thơm, giá đỗ và ngò - đậm chất miền Tây.' WHERE `code` = 'MS-CANH-CHUA-CA-LOC';
UPDATE `menu_items` SET `name` = 'Canh khổ qua nhồi thịt', `description` = 'Khổ qua tươi nhồi thịt heo xay, nấu nước dùng trong - đắng dịu, ngọt sâu, thanh lọc.' WHERE `code` = 'MS-CANH-KHO-QUA-NHOI';
UPDATE `menu_items` SET `name` = 'Súp bí đỏ tôm tươi', `description` = 'Bí đỏ xay mịn sánh vàng, tôm sú tươi nguyên con, kem tươi và hành lá - súp đầu bữa tinh tế.' WHERE `code` = 'MS-SUP-BI-DO-TOM';
UPDATE `menu_items` SET `name` = 'Canh rau ngót thịt heo', `description` = 'Rau ngót tươi hái ngay, thịt heo xay, nước dùng ngọt nhẹ - bữa cơm thuần Việt chuẩn vị nhà.' WHERE `code` = 'MS-CANH-RAU-NGOT-THIT';
UPDATE `menu_items` SET `name` = 'Canh rau củ hạt sen', `description` = 'Hạt sen tươi, cà rốt, su hào, nấm hương - nước dùng rau củ nhẹ, phù hợp người ăn chay.' WHERE `code` = 'MS-CANH-RAU-CU-HAT-SEN';

-- MON CHINH
UPDATE `menu_items` SET `name` = 'Cơm gà lá sen', `description` = 'Gà ta áp chảo da giòn, cơm dẻo nấu với nước cốt gà, xốt gừng mật ong, rau củ theo mùa. Đặc trưng Mộc Sen.' WHERE `code` = 'MS-COM-GA-LA-SEN';
UPDATE `menu_items` SET `name` = 'Bún bò Mộc Sen', `description` = 'Nước dùng hầm xương 6 giờ, thịt bò bắp mềm, giò heo, mắm ruốc và sa tế tự làm. Món ăn biểu tượng.' WHERE `code` = 'MS-BUN-BO-MOC-SEN';
UPDATE `menu_items` SET `name` = 'Cá kho niêu đất', `description` = 'Cá lóc kho nước màu dừa, tiêu hạt, nước mắm nguyên chất trong niêu đất nung - ăn kèm cơm trắng nóng.' WHERE `code` = 'MS-CA-KHO-NIEU-DAT';
UPDATE `menu_items` SET `name` = 'Bò lúc lắc xốt tiêu xanh', `description` = 'Thăn bò Úc áp chảo, khoai tây chiên bơ, salad cà chua và xốt tiêu xanh Madagascar - bistro chuẩn.' WHERE `code` = 'MS-BO-LUC-LAC-SOT-TIEU';
UPDATE `menu_items` SET `name` = 'Gà nướng mật ong nghệ', `description` = 'Nửa gà ta ướp mật ong, nghệ tươi, sả, nướng than hoa đều lửa. Rau củ nướng kèm, xốt tắc mật ong.' WHERE `code` = 'MS-GA-NUONG-MAT-ONG';
UPDATE `menu_items` SET `name` = 'Tôm xốt me Mộc Sen', `description` = 'Tôm sú lớn áp chảo với bơ tỏi, xốt me chua ngọt tự làm, hành phi giòn và ớt tươi.' WHERE `code` = 'MS-TOM-SOT-ME';
UPDATE `menu_items` SET `name` = 'Sườn non rim mắm tỏi', `description` = 'Sườn non heo rim mắm tỏi ngọt mặn hài hòa, ăn kèm dưa leo ngâm chua và cơm trắng nóng.' WHERE `code` = 'MS-SUON-NON-RIM-MAM';
UPDATE `menu_items` SET `name` = 'Vịt áp chảo xốt me', `description` = 'Ức vịt áp chảo da giòn rụm, xốt me chua ngọt đặc, rau thơm và hành tây ngâm giấm.' WHERE `code` = 'MS-VIT-AP-CHAO-SOT-ME';
UPDATE `menu_items` SET `name` = 'Mực xào sa tế rau củ', `description` = 'Mực ống cắt khoanh xào lửa to với cà chua, hành tây, ớt chuông và sa tế tự làm - cay nhẹ thơm.' WHERE `code` = 'MS-MUC-XAO-SA-TE';
UPDATE `menu_items` SET `name` = 'Cá chiên mắm xoài', `description` = 'Cá điêu hồng phi-lê chiên giòn, chấm mắm xoài xanh tự ngâm, rau sống ăn kèm.' WHERE `code` = 'MS-CA-CHIEN-MAM-XOAI';
UPDATE `menu_items` SET `name` = 'Bò kho bánh mì nóng', `description` = 'Bò bắp hầm sa tế hoa hồi, cà rốt mềm vừa miệng, nước xốt sánh đỏ - ăn kèm bánh mì lò nóng giòn.' WHERE `code` = 'MS-BO-KHO-BANH-MI';
UPDATE `menu_items` SET `name` = 'Sườn bò nướng kiểu bistro', `description` = 'Sườn bò nướng lò, bơ thảo mộc, khoai tây nghiền bơ sữa, nấm xốt vang đỏ - món premium tối cuối tuần.' WHERE `code` = 'MS-SUON-BO-NUONG-BISTRO';

-- COM & MI
UPDATE `menu_items` SET `name` = 'Phở gà thảo mộc', `description` = 'Nước dùng hầm gà ta với thảo mộc, gà xé mềm, bánh phở sợi mảnh, rau thơm và chanh ớt tươi.' WHERE `code` = 'MS-PHO-GA-THAO-MOC';
UPDATE `menu_items` SET `name` = 'Bún chả Hà Nội', `description` = 'Thịt chả nướng than, thịt heo viên, nước chấm pha chuẩn Hà Nội - ăn kèm bún tươi và rau sống.' WHERE `code` = 'MS-BUN-CHA-HA-NOI';
UPDATE `menu_items` SET `name` = 'Cơm sườn mật ong trứng ốp', `description` = 'Sườn cốt-lết nướng mật ong, cơm trắng, trứng ốp-la, dưa leo và đồ chua Mộc Sen - cơm dĩa văn phòng đỉnh.' WHERE `code` = 'MS-COM-SUON-MAT-ONG';
UPDATE `menu_items` SET `name` = 'Mì xào bò rau củ', `description` = 'Mì trứng xào lửa to, bò thái mỏng, ớt chuông, hành tây, cà rốt - xốt hàu hài vị đậm đà.' WHERE `code` = 'MS-MI-XAO-BO-RAU-CU';
UPDATE `menu_items` SET `name` = 'Bún thịt nướng đặc biệt', `description` = 'Thịt nướng than mỡ hành, chả giò chiên, bún, rau sống đầy đủ, đồ chua và nước mắm Mộc Sen.' WHERE `code` = 'MS-BUN-THIT-NUONG';
UPDATE `menu_items` SET `name` = 'Cơm bò xào sa tế', `description` = 'Bò Úc thái lát xào sa tế cay nhẹ, cơm trắng, dưa leo, đồ chua - bữa trưa đủ chất.' WHERE `code` = 'MS-COM-BO-XAO-SATE';
UPDATE `menu_items` SET `name` = 'Miến gà nấm hương', `description` = 'Miến dong dai, gà ta xé, nấm hương khô ngâm nở, nước dùng gà thanh ngọt, hành lá và tiêu.' WHERE `code` = 'MS-MIEN-GA-NAM';
UPDATE `menu_items` SET `name` = 'Cơm chiên hải sản Mộc Sen', `description` = 'Cơm chiên tôm mực, trứng gà, hành lá, cà rốt - chiên lửa to, hạt cơm rời, hải sản ngọt tươi.' WHERE `code` = 'MS-COM-CHIEN-HAI-SAN';

-- RAU & CHAY
UPDATE `menu_items` SET `name` = 'Rau củ xào tỏi', `description` = 'Rau theo mùa (cải ngọt, bông cải, đậu cô-ve) xào tỏi băm thơm, lửa to giữ giòn.' WHERE `code` = 'MS-RAU-CU-XAO-TOI';
UPDATE `menu_items` SET `name` = 'Đậu hũ non xốt nấm', `description` = 'Đậu hũ non cắt miếng, xốt nấm đông cô và nấm rơm tươi - đậm thực vật giàu dinh dưỡng.' WHERE `code` = 'MS-DAU-HU-SOT-NAM';
UPDATE `menu_items` SET `name` = 'Nấm kho tiêu đen', `description` = 'Nấm bào ngư, nấm đông cô kho tương hoisin, tiêu đen và hành boa-rô - ăn kèm cơm nóng.' WHERE `code` = 'MS-NAM-KHO-TIEU';
UPDATE `menu_items` SET `name` = 'Gỏi rau mầm bò áp chảo', `description` = 'Rau mầm tươi, bò thái mỏng áp chảo tái hồng, xốt mè rang và dầu hào - salad Á tinh tế.' WHERE `code` = 'MS-GOI-RAU-MAM-BO';
UPDATE `menu_items` SET `name` = 'Cà tím nướng mỡ hành', `description` = 'Cà tím nướng mềm thơm, mỡ hành phi vàng, đậu phộng rang giã thô, nước mắm chay chua ngọt.' WHERE `code` = 'MS-CA-TIM-NUONG-MO-HANH';
UPDATE `menu_items` SET `name` = 'Đậu bắp xào tỏi', `description` = 'Đậu bắp non chọn lọc xào tỏi lửa to, giữ độ giòn và vị ngọt tự nhiên của rau.' WHERE `code` = 'MS-DAU-BAP-XAO-TOI';
UPDATE `menu_items` SET `name` = 'Nộm hoa chuối chay', `description` = 'Hoa chuối thái, đậu phộng, mè, rau thơm và nước trộn chua ngọt chay - thanh mát giải nhiệt.' WHERE `code` = 'MS-NOM-HOA-CHUOI-CHAY';

-- TRANG MIEM
UPDATE `menu_items` SET `name` = 'Chè sen long nhãn', `description` = 'Hạt sen nấu mềm vừa miệng, long nhãn ngọt thanh, đường phèn - dùng lạnh. Đặc sản Mộc Sen.' WHERE `code` = 'MS-CHE-SEN-LONG-NHAN';
UPDATE `menu_items` SET `name` = 'Panna cotta dừa xoài', `description` = 'Kem dừa Thái sánh mịn, xốt xoài chín chua nhẹ và lát xoài tươi - fusion Việt-Âu tinh tế.' WHERE `code` = 'MS-PANNA-COTTA-DUA';
UPDATE `menu_items` SET `name` = 'Bánh flan cà phê', `description` = 'Flan mịn pha cà phê robusta đậm, lớp caramel cháy nhẹ - kết thúc hoàn hảo cho bữa tối.' WHERE `code` = 'MS-BANH-FLAN-CA-PHE';
UPDATE `menu_items` SET `name` = 'Kem dừa non', `description` = 'Kem dừa tươi trong trái dừa non, kem thạch dừa, đậu phộng rang - giải nhiệt mùa hè.' WHERE `code` = 'MS-KEM-DUA-NON';
UPDATE `menu_items` SET `name` = 'Sữa chua nếp cẩm mật ong', `description` = 'Sữa chua Hy Lạp mịn, nếp cẩm dẻo tím, mật ong hoa nhãn và hạt chia - lành mạnh, ngon miệng.' WHERE `code` = 'MS-SUA-CHUA-NEP-CAM';
UPDATE `menu_items` SET `name` = 'Bánh chuối nướng nước cốt dừa', `description` = 'Chuối sứ chín nướng than thơm, chan nước cốt dừa ấm, mè rang và đường thốt nốt.' WHERE `code` = 'MS-BANH-CHUOI-NUONG';
UPDATE `menu_items` SET `name` = 'Tàu hũ nước đường gừng', `description` = 'Tàu hũ non mịn, nước đường gừng ấm thơm, trân châu nhỏ và lá pandan - chay 100%.' WHERE `code` = 'MS-TAU-HU-NUOC-DUONG';

-- DO UONG
UPDATE `menu_items` SET `name` = 'Trà sen ướp tươi lạnh', `description` = 'Trà oolong ướp hoa sen tươi cắt buổi sáng, pha nguội và rót đá - linh hồn của Mộc Sen.' WHERE `code` = 'MS-TRA-SEN-LANH';
UPDATE `menu_items` SET `name` = 'Trà sen nóng', `description` = 'Trà oolong ướp sen pha bình, dùng nóng - thích hợp mùa đông hay không khí máy lạnh.' WHERE `code` = 'MS-TRA-SEN-NONG';
UPDATE `menu_items` SET `name` = 'Cà phê sữa đá', `description` = 'Cà phê phin robusta rang đậm, sữa đặc Ông Thọ, đá viên - chuẩn cà phê Việt truyền thống.' WHERE `code` = 'MS-CA-PHE-SUA-DA';
UPDATE `menu_items` SET `name` = 'Cà phê đen đá', `description` = 'Cà phê phin nguyên chất, không sữa, pha loãng vừa đủ, đá to giữ lạnh lâu.' WHERE `code` = 'MS-CA-PHE-DEN-DA';
UPDATE `menu_items` SET `name` = 'Nước chanh sả mật ong', `description` = 'Chanh vắt tươi, sả đập, mật ong hoa nhãn - thanh mát, giải nhiệt, nhẹ ngọt tự nhiên.' WHERE `code` = 'MS-NUOC-CHANH-SA';
UPDATE `menu_items` SET `name` = 'Trà tắc mật ong đá', `description` = 'Trà xanh pha với tắc (quất) tươi vắt, mật ong, đá viên - chua dịu, thơm mát.' WHERE `code` = 'MS-TRA-TAC-MAT-ONG';
UPDATE `menu_items` SET `name` = 'Sinh tố xoài cát', `description` = 'Xoài cát Hòa Lộc chín, sữa chua tươi, đá xay - đặc quánh, ngọt dịu, không pha thêm đường.' WHERE `code` = 'MS-SINH-TO-XOAI';
UPDATE `menu_items` SET `name` = 'Sinh tố bơ sữa', `description` = 'Bơ sáp Đắk Lắk chín, sữa tươi không đường, đường phèn nhẹ, đá bào - béo mịn, no lâu.' WHERE `code` = 'MS-SINH-TO-BO-SUA';
UPDATE `menu_items` SET `name` = 'Nước ép cam cà rốt tươi', `description` = 'Cam sành và cà rốt ép ngay khi order, không đường không bảo quản - vitamin C tự nhiên.' WHERE `code` = 'MS-NUOC-EP-CAM-CA-ROT';
UPDATE `menu_items` SET `name` = 'Trà đào cam sả', `description` = 'Đào ngâm, cam tươi, sả thơm, trà xanh Thái Nguyên, đá viên - nhẹ nhàng, thơm trái cây.' WHERE `code` = 'MS-TRA-DAO-CAM-SA';
UPDATE `menu_items` SET `name` = 'Nước dừa tươi', `description` = 'Dừa xiêm chặt phục vụ nguyên trái - mát lạnh tự nhiên, ngọt thanh đặc trưng.' WHERE `code` = 'MS-NUOC-DUA-TUOI';
UPDATE `menu_items` SET `name` = 'Nước suối / Soda', `description` = 'Nước suối Vĩnh Hảo hoặc soda lạnh - lựa chọn đơn giản đi kèm bữa ăn.' WHERE `code` = 'MS-NUOC-SUOI';
UPDATE `menu_items` SET `name` = 'Bia Sài Gòn lạnh', `description` = 'Bia Sài Gòn chai 330ml ướp lạnh - đi kèm bữa tối hoặc gặp gỡ bạn bè.' WHERE `code` = 'MS-BIA-CHAI';

-- SET & COMBO
UPDATE `menu_items` SET `name` = 'Set Trưa Văn Phòng', `description` = '1 món cơm/mì tùy chọn + 1 tô canh nhỏ + 1 trà sen lạnh. Giao từ 11h. Thay đổi theo ngày.' WHERE `code` = 'MS-SET-TRUA-VP';
UPDATE `menu_items` SET `name` = 'Set Gia Đình Mộc Sen (4 người)', `description` = '4 món chính (gà/cá/bò/tôm luân phiên), 1 canh, 1 rau xào, 1 tráng miệng + 4 đồ uống. Phục vụ 4 người.' WHERE `code` = 'MS-SET-GIA-DINH';
UPDATE `menu_items` SET `name` = 'Set Hẹn Hò Bên Cửa Sổ (2 người)', `description` = 'Khai vị chia sẻ, 2 món chính premium, 2 đồ uống signature Mộc Sen, 1 tráng miệng. Bàn Window Zone.' WHERE `code` = 'MS-SET-HEN-HO';
UPDATE `menu_items` SET `name` = 'Set Bếp Trưởng Đề Xuất (4 người)', `description` = 'Combo cao cấp 7 món theo mùa do bếp trưởng lựa chọn. Thay đổi theo tuần. Đặt trước 2 giờ.' WHERE `code` = 'MS-SET-BEP-TRUONG';
UPDATE `menu_items` SET `name` = 'Set Cuối Tuần Đại Gia Đình (6 người)', `description` = '6 món chính, 2 rau xào, 1 canh, 2 tráng miệng + 6 đồ uống. Phục vụ 6 người. Đặt trước 3 giờ.' WHERE `code` = 'MS-SET-CUOI-TUAN';
UPDATE `menu_items` SET `name` = 'Set Chay Mộc Sen (2 người)', `description` = '1 khai vị chay, 2 món chay tùy chọn, 1 canh rau, 1 tráng miệng chay + 2 trà sen nóng. 100% thực vật.' WHERE `code` = 'MS-SET-CHAY';

SELECT 'Cập nhật món ăn và danh mục có dấu thành công!' AS result;
