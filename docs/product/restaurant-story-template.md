# RestaurantPOS Product Story Template

## Purpose

This document is the source of truth for the unified RestaurantPOS product story. Future data, frontend copy, image assets, reports, and operator runbooks should align with this file unless a narrower owning document overrides it.

## Brand

- Restaurant name: Mộc Sen Bistro
- Slug: `moc-sen-bistro`
- Tagline: Bữa Việt tinh gọn, trọn vị mỗi ngày.
- Operating message: Khách thấy tiện. Nhân viên thấy rõ. Quản lý thấy kiểm soát được.
- Personality: Ấm áp, sạch sẽ, tinh tế Việt Nam, chuyên nghiệp nhưng không xa cách.
- Avoid: lorem ipsum, random menu data, dark theme as default, hotlinked images, watermark images, or generic demo-only wording.

## Product Story

Mộc Sen Bistro turns familiar Vietnamese meals into a smoother modern service flow. Guests can browse a clear menu, reserve quickly, choose preorder items, arrive with less waiting, and pay transparently. Staff can see which table needs the next action, which dishes are late, which bill is pending, and which branch needs attention.

Every RestaurantPOS screen should reduce waiting, reduce mistakes, improve customer experience, and help restaurant operators work with better control.

## Personas

| Name | Role | Scenario |
| --- | --- | --- |
| Nguyễn Minh Anh | Office guest | Reserves for dinner, browses menu, preorders, pays by QR, earns Điểm Sen. |
| Trần Thu Hương | Family guest | Reserves for 4, asks for a child-friendly window table and less spicy dishes. |
| Nam | Server | Checks in guests, assigns tables, opens orders, sends dishes to kitchen, serves ready items. |
| Linh | Cashier | Applies vouchers, confirms payment, prints invoices, closes cashier shifts. |
| Quân | Head cook | Handles KDS tickets from New to Cooking to Ready. |
| Mai | Branch manager | Watches revenue, table flow, inventory, staffing, and end-of-day reports. |

## End-To-End Scenario

1. Guest opens customer-web and selects Mộc Sen Bistro - Hoàn Kiếm.
2. Guest browses available menu items, favorites dishes, and adds preorder items.
3. Guest reserves 19:00 for 4 guests, with notes for children and a window table.
4. System holds a valid table window in the database.
5. Nam checks in the guest on staff-web; table moves from Reserved to Seated.
6. Preorder becomes the official order after check-in and is not sent to kitchen too early.
7. Quân moves KDS tickets from New to Cooking to Ready.
8. Nam serves dishes and marks items as served.
9. Linh opens checkout, applies a valid voucher, and accepts QR or cash payment.
10. Điểm Sen is issued, invoice is recorded, and daily reporting reflects real operations.

## Database-First Rules

- Read `database/schema/mysql-schema.sql` before adding SQL or data.
- Do not invent columns. If a field is missing, add a small SQL-first patch and keep `database/schema/mysql-schema.sql`, `database/patches/*.sql`, and `db_all.sql` aligned when schema changes.
- Do not add fake Laravel seeders or factories for demo-only business data.
- Business data belongs in MySQL through SQL-first bootstrap artifacts or explicit data patches.
- Static images are the exception: image binaries belong under `customer-web/public/customer-web/menu`, while the database stores paths such as `/customer-web/menu/com-ga-la-sen.jpg`.
- Data patches must be idempotent. Use stable codes/slugs such as `MS-*`, `MOCSEN-*`, and `RSV-MS-*`.
- Menu prices must use effective price rows where the schema supports historical pricing.
- Do not delete unrelated data. Namespace Mộc Sen rows to avoid colliding with production-like fixtures.

## Branches And Tables

| Branch code | Branch name | Address | Demo role |
| --- | --- | --- | --- |
| `MS-HK` | Mộc Sen Bistro - Hoàn Kiếm | 24 Tràng Tiền, Hoàn Kiếm, Hà Nội | Central branch, office and tourist dinner peak. |
| `MS-CG` | Mộc Sen Bistro - Cầu Giấy | 88 Duy Tân, Cầu Giấy, Hà Nội | Office lunch branch. |
| `MS-TD` | Mộc Sen Bistro - Thảo Điền | 16 Xuân Thủy, Thảo Điền, TP.HCM | Family and group branch. |

| Zone | Suggested capacity | Operational meaning |
| --- | --- | --- |
| Main Hall | 2-4 guests | Main room, fast table turns. |
| Window Zone | 2-4 guests | Best light, common reservation preference. |
| Garden Corner | 4-6 guests | Quieter group area. |
| Private Room | 6-12 guests | Family groups and celebrations. |

## Menu Dataset

Use menu image paths in this shape: `/customer-web/menu/{slug}.jpg`.

Current Mộc Sen dataset target: 48 menu items across starters, mains, rice/noodle/phở, vegetables/vegetarian, desserts, drinks, and combo sets.

| Group | Item | Description | Price | Label | Image path |
| --- | --- | --- | ---: | --- | --- |
| Khai vị | Gỏi cuốn tôm thịt | Tôm, thịt mềm, rau sống, bún mảnh, sốt đậu phộng. | 59000 | Nhẹ bụng, gia đình | `/customer-web/menu/goi-cuon-tom-thit.jpg` |
| Khai vị | Nem sen giòn | Nem chiên giòn nhân thịt, nấm, miến và củ sen. | 69000 | Bán chạy | `/customer-web/menu/nem-sen-gion.jpg` |
| Khai vị | Salad xoài tôm | Xoài xanh, tôm áp chảo, rau thơm, sốt chua ngọt. | 79000 | Tươi mát | `/customer-web/menu/salad-xoai-tom.jpg` |
| Khai vị | Chả mực mini | Chả mực giã tay, chiên vàng, dùng kèm tương ớt Mộc Sen. | 89000 | Hải sản | `/customer-web/menu/cha-muc-mini.jpg` |
| Khai vị | Đậu hũ rang muối | Đậu hũ non áo bột mỏng, rang muối sả giòn. | 55000 | Chay nhẹ | `/customer-web/menu/dau-hu-rang-muoi.jpg` |
| Khai vị | Gỏi gà bắp chuối | Gà xé, bắp chuối, rau răm, hành phi và nước mắm chua ngọt. | 76000 | Tươi nhẹ | `/customer-web/menu/goi-ga-bap-chuoi.jpg` |
| Khai vị | Chả giò hải sản | Cuốn hải sản chiên giòn, dùng kèm rau sống và sốt Mộc Sen. | 89000 | Hải sản | `/customer-web/menu/cha-gio-hai-san.jpg` |
| Món chính | Cơm gà lá sen | Gà áp chảo, cơm dẻo, sốt gừng nhẹ, rau củ theo mùa. | 89000 | Signature, bán chạy | `/customer-web/menu/com-ga-la-sen.jpg` |
| Món chính | Bún bò Mộc Sen | Nước dùng đậm vị, thịt bò mềm, rau thơm và sa tế nhẹ. | 95000 | Đậm vị, cay nhẹ | `/customer-web/menu/bun-bo-moc-sen.jpg` |
| Món chính | Cá kho niêu đất | Cá kho tiêu, nước màu truyền thống, ăn kèm cơm trắng. | 119000 | Truyền thống | `/customer-web/menu/ca-kho-nieu-dat.jpg` |
| Món chính | Bò lúc lắc sốt tiêu | Bò mềm áp chảo, khoai tây, salad và sốt tiêu đen. | 139000 | Yêu thích | `/customer-web/menu/bo-luc-lac-sot-tieu.jpg` |
| Món chính | Gà nướng mật ong | Gà nướng vàng, mật ong nhẹ, rau củ nướng. | 129000 | Gia đình | `/customer-web/menu/ga-nuong-mat-ong.jpg` |
| Món chính | Tôm sốt me | Tôm áp chảo, sốt me chua ngọt, hành phi. | 149000 | Hải sản | `/customer-web/menu/tom-sot-me.jpg` |
| Món chính | Sườn non rim mắm | Sườn non rim mắm tỏi, ăn kèm dưa leo và cơm. | 129000 | Đậm vị | `/customer-web/menu/suon-non-rim-mam.jpg` |
| Món chính | Vịt áp chảo sốt me | Vịt áp chảo da giòn, sốt me chua ngọt và rau thơm. | 159000 | Món tối | `/customer-web/menu/vit-ap-chao-sot-me.jpg` |
| Món chính | Cá chiên mắm xoài | Cá chiên giòn, mắm xoài xanh và rau sống ăn kèm. | 149000 | Hải sản | `/customer-web/menu/ca-chien-mam-xoai.jpg` |
| Món chính | Bò kho bánh mì | Bò kho mềm, cà rốt, nước sốt thơm và bánh mì nóng. | 99000 | Ấm bụng | `/customer-web/menu/bo-kho-banh-mi.jpg` |
| Cơm & bún/phở | Phở gà thảo mộc | Nước dùng thanh, gà xé, rau thơm và bánh phở mềm. | 79000 | Thanh nhẹ | `/customer-web/menu/pho-ga-thao-moc.jpg` |
| Cơm & bún/phở | Bún chả Hà Nội | Thịt nướng than, nước chấm chua ngọt, bún và rau sống. | 89000 | Đặc sản Hà Nội | `/customer-web/menu/bun-cha-ha-noi.jpg` |
| Cơm & bún/phở | Cơm sườn mật ong | Sườn nướng mật ong, cơm trắng, trứng và đồ chua. | 99000 | Bán chạy trưa | `/customer-web/menu/com-suon-mat-ong.jpg` |
| Cơm & bún/phở | Mì xào bò rau củ | Mì xào, bò mềm, rau củ giòn và sốt hài hòa. | 92000 | Nhanh | `/customer-web/menu/mi-xao-bo-rau-cu.jpg` |
| Cơm & bún/phở | Bún thịt nướng | Thịt nướng, bún, rau sống, đồ chua và nước mắm. | 85000 | Phổ biến | `/customer-web/menu/bun-thit-nuong.jpg` |
| Cơm & bún/phở | Cơm bò xào sa tế | Bò xào sa tế cay nhẹ, cơm trắng, dưa leo và đồ chua. | 109000 | Cay nhẹ | `/customer-web/menu/com-bo-xao-sate.jpg` |
| Cơm & bún/phở | Miến gà nấm | Miến dai, gà xé, nấm hương và nước dùng thanh. | 79000 | Thanh nhẹ | `/customer-web/menu/mien-ga-nam.jpg` |
| Rau & chay | Rau củ xào tỏi | Rau củ theo mùa xào tỏi thơm, giữ độ giòn. | 55000 | Healthy | `/customer-web/menu/rau-cu-xao-toi.jpg` |
| Rau & chay | Đậu hũ sốt nấm | Đậu hũ non, nấm đông cô, sốt thanh nhẹ. | 65000 | Món chay | `/customer-web/menu/dau-hu-sot-nam.jpg` |
| Rau & chay | Nấm kho tiêu | Nấm kho tiêu, hành boa-rô, ăn kèm cơm nóng. | 69000 | Chay đậm vị | `/customer-web/menu/nam-kho-tieu.jpg` |
| Rau & chay | Canh rau củ hạt sen | Canh rau củ, hạt sen, nước dùng rau củ nhẹ. | 59000 | Thanh mát | `/customer-web/menu/canh-rau-cu-hat-sen.jpg` |
| Rau & chay | Gỏi rau mầm bò | Rau mầm, bò áp chảo, sốt mè rang. | 89000 | Tươi | `/customer-web/menu/goi-rau-mam-bo.jpg` |
| Rau & chay | Cà tím nướng mỡ hành | Cà tím nướng mềm, mỡ hành, đậu phộng và nước mắm chay. | 59000 | Chay Việt | `/customer-web/menu/ca-tim-nuong-mo-hanh.jpg` |
| Rau & chay | Đậu bắp xào tỏi | Đậu bắp xào tỏi nhanh lửa, giữ độ giòn và vị ngọt tự nhiên. | 52000 | Rau xanh | `/customer-web/menu/dau-bap-xao-toi.jpg` |
| Tráng miệng | Chè sen long nhãn | Hạt sen mềm, long nhãn ngọt thanh, dùng lạnh. | 45000 | Signature | `/customer-web/menu/che-sen-long-nhan.jpg` |
| Tráng miệng | Panna cotta dừa | Kem dừa mềm mịn, sốt xoài chua nhẹ. | 49000 | Món mới | `/customer-web/menu/panna-cotta-dua.jpg` |
| Tráng miệng | Bánh flan cà phê | Flan mềm, caramel, cà phê đậm nhẹ. | 42000 | Việt Nam | `/customer-web/menu/banh-flan-ca-phe.jpg` |
| Tráng miệng | Kem dừa non | Kem dừa, dừa non, đậu phộng rang. | 55000 | Mát lạnh | `/customer-web/menu/kem-dua-non.jpg` |
| Tráng miệng | Sữa chua nếp cẩm | Sữa chua mịn, nếp cẩm dẻo, vị ngọt dịu. | 45000 | Healthy | `/customer-web/menu/sua-chua-nep-cam.jpg` |
| Tráng miệng | Bánh chuối nướng | Chuối chín nướng thơm, nước cốt dừa và mè rang. | 49000 | Việt Nam | `/customer-web/menu/banh-chuoi-nuong.jpg` |
| Tráng miệng | Tàu hũ nước đường | Tàu hũ mềm, nước đường gừng và trân châu nhỏ. | 39000 | Nhẹ | `/customer-web/menu/tau-hu-nuoc-duong.jpg` |
| Đồ uống | Trà sen lạnh | Trà sen thơm nhẹ, vị thanh, ít ngọt. | 35000 | Signature | `/customer-web/menu/tra-sen-lanh.jpg` |
| Đồ uống | Nước ép cam cà rốt | Cam tươi và cà rốt ép lạnh. | 49000 | Healthy | `/customer-web/menu/nuoc-ep-cam-ca-rot.jpg` |
| Đồ uống | Cà phê sữa đá | Cà phê rang đậm, sữa đặc, đá viên. | 39000 | Việt Nam | `/customer-web/menu/ca-phe-sua-da.jpg` |
| Đồ uống | Nước chanh sả | Chanh tươi, sả, mật ong nhẹ. | 39000 | Mát | `/customer-web/menu/nuoc-chanh-sa.jpg` |
| Đồ uống | Sinh tố xoài | Xoài chín, sữa chua, đá xay. | 55000 | Trái cây | `/customer-web/menu/sinh-to-xoai.jpg` |
| Đồ uống | Trà tắc mật ong | Trà tắc mát, mật ong nhẹ và lát tắc tươi. | 39000 | Mát | `/customer-web/menu/tra-tac-mat-ong.jpg` |
| Combo | Set trưa văn phòng | Món chính + canh nhỏ + trà sen. | 149000 | Lunch | `/customer-web/menu/set-trua-van-phong.jpg` |
| Combo | Set gia đình Mộc Sen | 4 món chính, 1 rau, 1 tráng miệng. | 399000 | 4 người | `/customer-web/menu/set-gia-dinh-moc-sen.jpg` |
| Combo | Set hẹn hò bên cửa sổ | Khai vị, 2 món chính, 2 đồ uống, 1 tráng miệng. | 299000 | 2 người | `/customer-web/menu/set-hen-ho-ben-cua-so.jpg` |
| Combo | Set bếp trưởng đề xuất | Combo 5 món theo mùa cho nhóm 4 khách, cân bằng khai vị, món chính và tráng miệng. | 459000 | Theo mùa | `/customer-web/menu/set-bep-truong-de-xuat.jpg` |

## Image Rules

- Do not hotlink external image URLs in production/demo data.
- Store local assets under `customer-web/public/customer-web/menu`.
- Keep `customer-web/public/customer-web/menu/IMAGE_LICENSES.md` updated with source URL, license, download date, and menu item mapping.
- Use real, clear, well-lit food photos without watermark or unrelated text.
- Current dataset images must stay local and licensed; do not reintroduce category fallback rows without documenting a replacement plan.

## Voucher And Loyalty

| Code | Type | Condition | Value |
| --- | --- | --- | --- |
| `WELCOME30` | New guest | Order from 200000 VND, once per customer | 30000 VND off |
| `SENLUNCH10` | Office lunch | 10:00-14:00, Monday-Friday | 10% off |
| `FAMILY50` | Weekend family | From 4 guests, order from 500000 VND | 50000 VND off |
| `BIRTHDAY100` | Member birthday | Sen Vàng or higher, birthday month | 100000 VND off |
| `WINDOWTEA` | Window Zone reservation | Reserved 2 hours ahead, dine-in | 1 Trà sen lạnh |

| Tier | Điểm Sen | Benefits |
| --- | ---: | --- |
| Sen Mới | 0 | New guest voucher and favorites. |
| Sen Bạc | 200 | 5% drink or lunch offer. |
| Sen Vàng | 500 | 8% bill offer and birthday voucher. |
| Sen Kim Cương | 1000 | Priority booking, birthday gift, group support. |

## Customer-Web Copy

| Context | Avoid | Use |
| --- | --- | --- |
| Generic error | Error occurred | Có lỗi xảy ra, vui lòng thử lại sau ít phút. |
| Empty menu | No data | Hiện chưa có món phù hợp với bộ lọc này. |
| Booking success | Submit successful | Đặt bàn thành công. Mộc Sen đã giữ chỗ cho bạn. |
| Preorder CTA | Add item | Thêm món vào giỏ đặt trước |
| Payment success | Paid | Thanh toán thành công. Cảm ơn bạn đã dùng bữa tại Mộc Sen Bistro. |
| Loyalty | Points updated | Bạn đã tích thêm {points} điểm Sen. |

## Customer-Web Visual Rules

- Keep light theme as the default.
- Use `#2F7D5C` as primary accent, `#F4B860` as secondary highlight, `#FFFDF8` as warm page background, `#FFFFFF` for surfaces, `#1F2933` for primary text, `#667085` for muted text, `#E5E7EB` for borders, and `#D92D20` for danger.
- Menu cards need stable image ratio, clear price, enough whitespace, and mobile-friendly CTA size.
- Empty/error states must offer a next action: clear filter, retry, choose another time, or contact the restaurant.

## Staff-Web Status Labels

| Domain | Status | Label |
| --- | --- | --- |
| Table | Available | Trống |
| Table | Reserved | Đã đặt |
| Table | Seated | Đang phục vụ |
| Table | Ordering | Đang gọi món |
| Table | Ready | Có món sẵn sàng |
| Table | Billing | Chờ thanh toán |
| Table | Cleaning | Chờ dọn bàn |
| Order | New | Mới tạo |
| Order | Sent | Đã gửi bếp |
| Kitchen | Cooking | Đang nấu |
| Kitchen | Ready | Sẵn sàng phục vụ |
| Payment | Paid | Đã thanh toán |
| Payment | Refunded | Đã hoàn tiền |

## Staff-Web Action Copy

| Area | Copy |
| --- | --- |
| Reservation | Check-in khách; Xếp bàn; Gọi khách; Đánh dấu không đến; Hủy đặt bàn |
| Table board | Mở order; Chuyển bàn; Ghép bàn; Tách bàn; Yêu cầu dọn bàn; Đóng bàn |
| POS/order | Thêm món; Ghi chú món; Gửi món xuống bếp; Hủy món; Tách hóa đơn; Yêu cầu thanh toán |
| Kitchen/KDS | Nhận món; Đang nấu; Món đã sẵn sàng; Đã phục vụ; Đánh dấu trễ |
| Checkout | Áp voucher; Xác nhận thanh toán; In hóa đơn; Hoàn tiền; Kết ca thu ngân |
| Inventory | Nhập hàng; Điều chỉnh tồn; Cảnh báo dưới định mức; Kiểm kê cuối ngày |

## Reporting Story

Reports should tell the same Mộc Sen operating story:

- Daily sales: revenue, bill count, AOV, QR/cash/card ratio.
- Reservations: booked, checked-in, no-show, cancelled, hold timeout.
- Kitchen: ticket count, average cook time, delayed items, ready-not-served.
- Menu performance: best seller, out-of-stock, preorder ratio.
- Voucher/loyalty: redemption, new guests, Điểm Sen issued/redeemed.
- Inventory: low stock, receiving, usage variance.

## Implementation Lanes

1. Governance docs: this file.
2. SQL data and menu image URLs: schema-first, idempotent data patch, no fake seeder.
3. Real food images: local assets plus license notes.
4. Customer-web polish: copy, bright UI, empty/error states, menu/booking/preorder/payment/loyalty text.
5. Staff-web polish: operational labels, status text, warnings, dashboard/KDS/checkout/reporting language.
6. Verification: targeted backend/frontend checks, runtime gates when schema/bootstrap behavior changes, API artifacts only when contract changes.

## Acceptance Checklist

- Brand/story: main screens no longer feel generic or lorem-style.
- Database: Mộc Sen data lives in SQL-first bootstrap artifacts or data patches and remains idempotent.
- Menu: at least 48 items have name, description, price, category, availability, preorder setting, and image path.
- Images: all 48 menu items use local downloaded assets with license notes; no menu row relies on category fallback assets.
- Customer UX: home/menu/booking/preorder/payment/loyalty are bright, clear, and action-oriented.
- Staff UX: dashboard/table/POS/KDS/checkout/reporting labels are short, operational, and clear.
- Reports: demo insights reflect real Mộc Sen operations.
- Contracts: OpenAPI/generated artifacts change only if route, field, enum, or RBAC contract changes.
- Verification: targeted tests and build/type checks match changed surfaces.
