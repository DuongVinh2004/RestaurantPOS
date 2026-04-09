# PHẦN I - GIỚI THIỆU

## 1.1 Mục đích của tài liệu

Tài liệu này được viết lại từ bản `current_state_ba_report_thesis_style_2026-04-06.docx`, nhưng đổi trọng tâm từ một bản assessment thiên về đồ án sang một bản mô tả nghiệp vụ có thể dùng ngay cho team phát triển, BA, QA, UI/UX và người mới vào dự án. Mục tiêu chính là giúp mọi người hiểu hệ thống RestaurantPOS backend đang phục vụ bài toán gì, các actor nào tham gia, các phân hệ tương tác với nhau ra sao, và các quy tắc nghiệp vụ cốt lõi đang được backend bảo vệ như thế nào.

Điểm khác biệt của bản này là không đưa diagram vào tài liệu. Thay vào đó, toàn bộ phần logic nghiệp vụ được diễn tả bằng lời văn trực quan, bám sát cách hệ thống vận hành trong thực tế. Ở cuối tài liệu có thêm phần đặc tả use case đủ chi tiết để team có thể dùng làm đầu vào cho Use Case Diagram, Activity Diagram, Sequence Diagram hoặc System Context Diagram sau này.

## 1.2 Phạm vi và cách xây dựng báo cáo

Báo cáo mô tả hiện trạng của backend RestaurantPOS Laravel tại snapshot repository ngày 08/04/2026. Tài liệu không giả định rằng toàn bộ hệ thống ngoài thực tế đã triển khai đầy đủ, mà chỉ mô tả những gì có thể xác nhận từ source code, config, route surface, runbook, UAT pack và file báo cáo cũ mà người dùng cung cấp.

[[TABLE:evidence_sources]]

## 1.3 Snapshot dự án tại thời điểm viết báo cáo

Tại snapshot ngày 08/04/2026, hệ thống tổ chức bề mặt API thành năm nhóm route dưới `/api/v1`: `auth`, `customer self-service`, `staff POS`, `admin`, `ops/release`. Khi quét các file route chính, nhóm `v1` hiện có khoảng 228 endpoint, chưa tính endpoint tương thích `/api/user`. Phân bố tương đối là:

- `auth`: 8 endpoint
- `customer self-service`: 54 endpoint
- `staff POS`: 85 endpoint
- `admin`: 77 endpoint
- `ops/release`: 4 endpoint

Con số này cho thấy dự án không còn ở mức demo nhỏ lẻ. Đây là một backend đã có độ phủ nghiệp vụ khá rộng, bao gồm cả customer-facing flow, staff operation flow, admin back-office flow và operational governance flow.

## 1.4 Cách đọc tài liệu này

Tài liệu được tổ chức theo trình tự dễ nắm nghiệp vụ:

- Phần II giải thích dự án giải quyết bài toán gì và ai là người dùng chính.
- Phần III kể lại logic nghiệp vụ cốt lõi theo lời văn, theo cách một nhà hàng thực sự vận hành trong ngày.
- Phần IV gom các thực thể và phân hệ để team có thể suy ra mô hình dữ liệu và component một cách tự nhiên.
- Phần V chốt các quy tắc nghiệp vụ và ràng buộc kỹ thuật có ảnh hưởng trực tiếp tới hành vi nghiệp vụ.
- Phần VI và VII tổng hợp use case và đặc tả chi tiết, là đầu vào gần nhất để vẽ Use Case Diagram.

# PHẦN II - TỔNG QUAN DỰ ÁN

## 2.1 Dự án đang giải quyết bài toán gì

RestaurantPOS không chỉ là một hệ thống đặt bàn. Backend này đang được harden để trở thành nền tảng chung cho chuỗi nghiệp vụ vận hành nhà hàng, từ lúc khách tìm bàn, vào hàng chờ, đặt bàn, đến check-in, phục vụ tại chỗ, gọi món, thanh toán, hoàn tiền, quản trị dữ liệu nền và kiểm soát vận hành phát hành.

Nếu nhìn theo góc độ kinh doanh, dự án giải quyết ba bài toán lớn cùng lúc.

Thứ nhất là bài toán tiếp cận khách hàng trước giờ phục vụ. Khách cần biết chi nhánh nào còn chỗ, khung giờ nào phù hợp, có thể giữ bàn tạm hay phải vào waiting list, và nếu đã có reservation thì có thể tự quản lý reservation đó mà không cần gọi điện cho nhân viên.

Thứ hai là bài toán vận hành tại sảnh. Khi khách tới, nhân viên cần một backend đủ chặt để biết bàn nào đang trống, bàn nào đang giữ, bàn nào đang có service session, reservation nào sắp đến, ai đã check-in, order nào đang mở, món nào đang chờ bếp, bill nào đã khóa, bill nào còn nợ, và trạng thái hoàn tiền ra sao.

Thứ ba là bài toán quản trị và go-live. Hệ thống phải cho phép quản trị viên quản lý menu, giá, voucher, loyalty tier, chi nhánh, branch policy, dữ liệu bàn, tài chính, reporting; đồng thời phải có các command, artifact và gate để operator biết bản build hiện tại có đủ an toàn để phát hành hay chưa.

## 2.2 Giá trị cốt lõi của hệ thống

Điểm mạnh của RestaurantPOS backend không nằm ở một màn hình hay một chức năng đơn lẻ, mà ở chỗ nhiều nghiệp vụ trước đây thường bị tách rời đang được gom vào một dòng chảy thống nhất.

Reservation không đứng riêng, mà nối với table hold, waiting list, check-in, service session, order, payment, refund và reporting. Customer self-service không đứng riêng, mà bị ràng buộc chặt với owner scope, session contract và trạng thái thực của reservation. Admin master data không chỉ để CRUD dữ liệu, mà còn chi phối availability, kitchen routing, pricing, branch timezone và branch booking policy. Phần ops/release không chỉ là hạ tầng kỹ thuật, mà thực chất là hàng rào bảo vệ để nghiệp vụ không bị trôi khỏi hợp đồng đã cam kết.

## 2.3 Các actor chính của hệ thống

[[TABLE:actor_matrix]]

## 2.4 Các phân hệ chính nhìn từ góc độ nghiệp vụ

[[TABLE:module_maturity]]

## 2.5 Những nguyên tắc kỹ thuật đang chi phối hành vi nghiệp vụ

Có năm nguyên tắc kỹ thuật lặp đi lặp lại trong toàn bộ hệ thống và cần được hiểu như một phần của nghiệp vụ, chứ không chỉ là chi tiết lập trình.

- Boundary xác thực được tách rõ giữa khách hàng và staff/admin. Khách đi theo `X-Customer-Token` và session customer; staff/admin đi theo `X-Staff-Key`.
- Business logic được đưa vào service layer, nên controller chỉ đóng vai trò cổng vào HTTP. Điều này làm cho logic nghiệp vụ có xu hướng ổn định và tái dùng được.
- Các mutation nhạy cảm dùng `Idempotency-Key` để chặn replay, đồng thời nhiều flow có thêm `row_version` hoặc stale-write guard để chặn ghi đè dữ liệu cũ.
- Dự án đi theo hợp đồng SQL-first cho bootstrap và có frozen OpenAPI/release artifact. Nói cách khác, backend không chỉ chạy được mà còn phải phát hành được có kiểm soát.
- Một số flow được che bởi feature flag. Điều này rất quan trọng khi đọc nghiệp vụ vì không phải mọi chức năng có code đều mặc định đang mở ở mọi môi trường.

# PHẦN III - LOGIC NGHIỆP VỤ CỐT LÕI THEO LỜI VĂN

## 3.1 Câu chuyện nghiệp vụ end-to-end của một lượt phục vụ

Nếu kể hệ thống này theo ngôn ngữ dễ hiểu nhất, câu chuyện bắt đầu từ lúc khách chưa đến nhà hàng. Khách chọn chi nhánh, thời gian và số lượng người. Hệ thống trước tiên không tạo booking ngay, mà cố gắng xác định khả năng phục vụ thực tế của chi nhánh đó. Việc “còn bàn hay không” không chỉ phụ thuộc vào số lượng bàn, mà còn phụ thuộc vào timezone của chi nhánh, business hours, các closure window như ngày nghỉ hay blackout, cùng các giới hạn booking như lead time, max advance time và same-day cutoff.

Khi vẫn còn khả năng phục vụ, khách hoặc nhân viên có thể tạo một table hold. Hold là bước trung gian rất quan trọng: nó cho phép giữ chỗ tạm thời nhưng chưa biến thành reservation chính thức. Điều này phản ánh đúng thực tế nhà hàng, nơi nhu cầu giữ bàn nhanh trước khi nhập đủ thông tin khách là rất phổ biến. Nếu hold hết hạn, bị conflict hoặc không còn đúng `row_version`, hệ thống sẽ từ chối các thao tác tiếp theo thay vì âm thầm ghi đè.

Sau khi có hold hợp lệ hoặc có đủ điều kiện chọn bàn trực tiếp, reservation được tạo. Từ lúc đó reservation trở thành một “trục xương sống” của nhiều luồng khác nhau. Khách có thể xem, hủy, đổi lịch reservation của chính mình. Tùy chính sách và thời điểm, reservation có thể phát sinh preorder, deposit requirement, voucher, loyalty, bill preview và thậm chí cả bill self-payment.

Nếu lúc đầu không có bàn phù hợp, hệ thống không kết thúc câu chuyện ở trạng thái “hết chỗ”. Thay vào đó, waiting list được dùng để kéo khách vào một hàng chờ có quản lý. Nhân viên có thể notify, khách có thể accept hoặc decline, sau đó confirm arrival, rồi cuối cùng nhân viên seat khách và biến trạng thái chờ thành một trạng thái phục vụ thực thụ. Đây là cách hệ thống nối được phần tìm bàn với phần phục vụ mà không làm rơi khách giữa chừng.

Khi khách đến nơi, câu chuyện chuyển sang luồng staff POS. Nhân viên có thể check-in reservation sẵn có hoặc mở một service session cho khách walk-in. Từ thời điểm này, bàn, reservation, service session và order bắt đầu liên kết chặt với nhau. Hệ thống cần biết bàn đang ở trạng thái nào, reservation nào đang chiếm bàn, order nào là active order của bàn đó, và việc đổi bàn hoặc giải phóng bàn có đang vi phạm ràng buộc nào không.

Trong suốt thời gian khách dùng bữa, order được tạo và cập nhật. Từng món có vòng đời riêng, có thể được dispatch sang bếp, fire, bump hoặc recall. Tới một thời điểm phù hợp, bill được snapshot hoặc lock để tránh việc vừa thanh toán vừa tiếp tục sửa món. Từ đó hệ thống sinh settlement preview, nhận thanh toán, finalize settlement và đưa reservation sang trạng thái hoàn tất. Nếu phát sinh hoàn tiền, refund phải bám theo payment lineage chứ không thể tạo tùy tiện.

Sau khi lượt phục vụ kết thúc, dữ liệu không dừng lại ở giao dịch vừa hoàn tất. Nó chảy tiếp vào audit trail, invoice, reconciliation, reporting snapshot, notification history và release artifact. Đây là điểm cho thấy dự án đang được xây như một backend có định hướng production, không chỉ là API CRUD cho frontend gọi tạm.

## 3.2 Logic đặt bàn và branch-local scheduling

Một trong những đặc điểm quan trọng nhất của hệ thống là mọi quyết định availability và reservation đều được neo theo chi nhánh. Chi nhánh không chỉ là nơi chứa bàn, mà còn là nơi giữ timezone và booking policy riêng. Vì vậy, hai chi nhánh có cùng số lượng bàn vẫn có thể cho ra kết quả availability khác nhau nếu giờ hoạt động, closure window hoặc chính sách lead time khác nhau.

Branch scheduling hiện được mô tả bằng ba lớp dữ liệu chính trên `branches`: `business_hours`, `closure_windows` và `booking_policy`. Khi một giá trị ở chi nhánh là `null`, runtime mới fallback về default trong config. Điều này có nghĩa là hệ thống cho phép từng chi nhánh override hành vi đặt bàn, nhưng vẫn giữ được default chung cho toàn bộ thương hiệu.

Từ góc nhìn nghiệp vụ, cách làm này rất hợp lý. Nó cho phép nhà hàng thiết lập chi nhánh sân bay khác chi nhánh trung tâm thương mại, hoặc một chi nhánh cho phép waiting list còn chi nhánh khác thì không. Khi team vẽ diagram hay mô hình nghiệp vụ, nên xem `Branch` là thực thể điều phối chính sách chứ không chỉ là một thông tin phân loại.

## 3.3 Logic customer self-service

Customer self-service trong dự án này không phải kiểu “public API ai gọi cũng được”. Nó dựa trên một access/session model riêng cho khách hàng. Customer login tạo ra access session; từ đó khách có thể gọi các route self-service với `X-Customer-Token`, và ở một số nơi còn cần `X-Session-Id` để giữ đúng owner contract.

Ý nghĩa nghiệp vụ của cơ chế này là rất rõ: hệ thống phân biệt giữa việc “khách đã xác thực là ai” và việc “phiên truy cập hiện tại có thực sự là phiên được phép quản lý reservation hay payment session đó không”. Cách thiết kế này giúp tránh tình huống chỉ cần biết một mã reservation là có thể xem hoặc sửa thông tin của người khác.

Self-service hiện bao gồm nhiều điểm chạm quan trọng: xem danh sách reservation của mình, hủy, đổi lịch, xem preorder, thay đổi preorder, xem deposit preview, gửi deposit intent, tạo payment session cho deposit, xem active order, xem bill preview, tạo bill payment session, xem loyalty, xem voucher, yêu cầu export dữ liệu và tạo privacy request. Đây là phạm vi rộng hơn một ứng dụng đặt bàn thông thường.

## 3.4 Logic waiting list

Waiting list trong RestaurantPOS không phải một danh sách ghi tên thủ công. Nó là một vòng đời nghiệp vụ có trạng thái rõ ràng. Khách hoặc staff có thể tạo entry. Sau đó staff có thể notify khi có cơ hội phục vụ. Khách có thể accept, decline hoặc confirm arrival. Cuối cùng staff có thể seat khách và đẩy luồng sang phục vụ.

Điểm đáng chú ý là waiting list cũng chịu branch policy. Không phải chi nhánh nào cũng cho vào hàng chờ; chi nhánh phải mở cửa và không nằm trong closure window. Staff notify cũng dùng branch-local `notify_hold_minutes`, còn staff seat thì dùng branch-local `default_service_minutes`. Như vậy, waiting list không tách khỏi branch scheduling mà là một extension của scheduling.

Về mặt nghiệp vụ, waiting list là cầu nối giữa “không có bàn” và “vẫn còn cơ hội phục vụ”. Nó giúp nhà hàng giữ được khách, đồng thời cho phép staff kiểm soát việc seat khách theo năng lực phục vụ thực tế.

## 3.5 Logic vận hành tại sảnh

Phần staff POS cho thấy rõ dự án ưu tiên nhóm nghiệp vụ nhà hàng tại chỗ. Nhân viên có table board để nhìn trạng thái sảnh theo thời gian thực. Từ board đó họ có thể xem active service session của bàn, check-in reservation, mở walk-in service session, đổi bàn hoặc giải phóng bàn.

Điều quan trọng là hệ thống không coi bàn chỉ là một tài sản vật lý. Bàn là nơi gắn với reservation, service session và active order. Vì vậy thao tác move table hay release table luôn kéo theo việc kiểm tra branch scope, row version và trạng thái phục vụ hiện tại. Nếu bỏ các kiểm soát này, backend rất dễ sinh ra dữ liệu “một khách ở hai bàn” hoặc “bàn đã giải phóng nhưng order vẫn đang mở”.

Ở góc nhìn BA, đây là nơi nên vẽ thêm activity diagram hoặc state diagram sau này. Nhưng ngay ở mức lời văn, có thể hiểu đơn giản rằng staff POS đang biến sơ đồ bàn thành trung tâm điều phối tất cả tương tác tại chỗ.

## 3.6 Logic order, bill, checkout và refund

Order lifecycle của hệ thống bám rất chặt vào trải nghiệm thực tế của nhà hàng. Sau khi có service session hoặc reservation đã check-in, staff có thể tạo order theo bàn hoặc theo reservation. Hệ thống luôn có khái niệm active order để tránh việc vô tình tạo quá nhiều order song song cho cùng một lượt phục vụ.

Khi staff thêm món hoặc sửa trạng thái món, hệ thống không chỉ cập nhật danh sách món. Nó còn phải giữ sự nhất quán của bill, khả năng dispatch bếp và tổng tiền phải trả. Đến lúc chuẩn bị thanh toán, bill có thể được snapshot hoặc lock để đóng băng mặt bằng thanh toán. Từ đó staff mới xem settlement preview, nhận thanh toán, pay, checkout hoặc finalize settlement.

Refund được xây như một nghiệp vụ tài chính chứ không phải một nút “undo”. Staff phải xem refund preview, biết khoản nào đang được hoàn, hoàn ở mức deposit hay final payment, và trong một số trường hợp có thể refund kèm hủy reservation. Hệ thống cũng bảo vệ refund bằng capability riêng `payment.refund`, idempotency và lineage của payment gốc.

Đây là nhóm use case có mức hardening cao vì rủi ro nghiệp vụ lớn nhất thường nằm ở tiền, nợ và hoàn tiền chứ không chỉ ở đặt bàn.

## 3.7 Logic benefits, voucher và loyalty

Benefits trong hệ thống bao gồm voucher, loyalty tier, loyalty point và các quyền lợi áp dụng vào reservation. Điều đáng chú ý là chúng không được thiết kế như một module marketing độc lập, mà gắn trực tiếp với reservation và payment state.

Ví dụ, voucher có thể bị lock khi đã áp dụng vào reservation và cần release đúng cách nếu không dùng nữa. Loyalty có luồng redeem và release riêng, với giao dịch điểm được ghi nhận để không mất dấu lịch sử. Khi bill đã khóa hoặc thanh toán đã đi qua ngưỡng không cho phép sửa, một số thao tác benefits sẽ bị chặn.

Từ góc độ team, đây là vùng nghiệp vụ cần hiểu theo logic “quyền lợi đi cùng dòng tiền”. Nếu nhìn benefits chỉ như một nhãn giảm giá, team rất dễ thiết kế sai diagram hoặc UI flow.

## 3.8 Logic admin back-office và dữ liệu nền

Khối admin API cho thấy backend này không chỉ phục vụ thao tác runtime, mà còn cho phép nhà hàng cấu hình nền dữ liệu ở mức tương đối rộng. Admin quản lý menu category, menu item, menu price, voucher, loyalty tier, benefit setting, branch, tax profile, kitchen station, route bếp, zone, table và reporting snapshot.

Hệ thống còn có bulk import/export cho một số domain như branch, restaurant table, menu, voucher, loyalty tier. Điểm nghiệp vụ quan trọng ở đây là import không phải ghi đè tùy tiện. Import hỗ trợ `dry_run` và `commit`, có all-or-nothing behavior, có giới hạn batch size và có upsert key rõ ràng cho từng domain. Điều này cho thấy mục tiêu là hỗ trợ vận hành dữ liệu thật, không phải chỉ làm công cụ seed.

Branch settings đặc biệt quan trọng vì nó chạm sang availability, waiting list, reservation reschedule và các logic tính theo timezone. Khi vẽ diagram sau này, branch policy nên được đặt ở vị trí trung tâm giữa admin flow và reservation flow.

## 3.9 Logic kitchen, inventory và purchasing

Kitchen, inventory và purchasing đã có mặt đủ rõ để nhận diện như các domain thật, nhưng chúng chưa phải phần trưởng thành nhất của hệ thống. Kitchen hiện có station, category route, dispatch order, fire, bump, recall và stream changes. Inventory có ingredient, recipe, movement, supplier, purchase order, receipt. Purchasing đi cùng inventory theo hướng kiểm soát nhập hàng.

Nghĩa là backend đã có nền cho hậu cần và bếp, nhưng team không nên lầm tưởng rằng các flow này đã chín như reservation hay checkout. Khi dùng tài liệu này để vẽ use case, có thể đặt kitchen/inventory thành một cụm riêng hoặc một diagram con riêng, với ghi chú rằng đây là nhóm foundation usable, chưa phải vùng harden sâu nhất.

## 3.10 Logic conversation inbox, notification, privacy, audit, reporting và release governance

Conversation inbox là một module nội bộ cho staff, không phải sản phẩm chat với khách. Mục tiêu của nó là nhận hội thoại, phân công người xử lý, gắn hội thoại với reservation hoặc waiting list, thêm internal note và trong một số điều kiện có thể xếp outbound reply qua queue. Điều này hữu ích cho vận hành front office, nhất là khi một khách đang vừa ở waiting list vừa liên hệ với nhà hàng.

Notification platform theo mô hình outbox. Email là kênh usable thực sự; SMS và Zalo hiện mới ở mức stub/provider-ready. Về mặt nghiệp vụ, điều đó có nghĩa là hệ thống đã có nền tảng phát thông báo và lưu delivery evidence, nhưng không nên mô tả SMS/Zalo như capability production-ready trong tài liệu ngoài.

Privacy và audit là hai lớp bảo vệ quan trọng. Privacy flow cho phép khách export dữ liệu và yêu cầu anonymize, còn admin có thể review ở chế độ `dry_run` hoặc `commit`. Audit trail thì ghi các mutation có giá trị vận hành, pháp lý hoặc tài chính, nhằm trả lời bốn câu hỏi: ai làm gì, khi nào, trên subject nào và trạng thái trước/sau là gì.

Reporting và ops/release governance cho thấy dự án đang hướng tới production discipline. Hệ thống có health endpoint, metrics, frozen OpenAPI artifact, route inventory, release manifest, readiness command và UAT scenario pack chuẩn hóa. Điều này không trực tiếp tạo ra trải nghiệm người dùng cuối, nhưng nó giúp cả team tin rằng những gì đang mô tả về nghiệp vụ có thể được giữ ổn định qua nhiều vòng phát hành.

# PHẦN IV - THỰC THỂ NGHIỆP VỤ VÀ PHÂN RÃ PHÂN HỆ

## 4.1 Những thực thể nghiệp vụ quan trọng nhất

- `Branch`: đại diện cho một chi nhánh nhà hàng; giữ timezone, business hours, closure windows, booking policy và các dữ liệu vận hành đặc thù.
- `RestaurantTable`: đại diện cho bàn phục vụ; gắn với branch, zone, template và trạng thái vật lý.
- `TableHold`: giữ chỗ tạm thời cho một khoảng thời gian; thường là bước đệm trước reservation.
- `Reservation`: trục nghiệp vụ chính của khách đặt trước; có thể đi qua các trạng thái từ confirmed tới completed, cancelled, expired hoặc no-show.
- `WaitingListEntry`: đại diện cho khách chưa có bàn nhưng vẫn muốn chờ cơ hội được phục vụ.
- `ServiceSession`: phiên phục vụ tại chỗ; đặc biệt quan trọng với khách walk-in và quá trình check-in.
- `ReservationOrder` và `OrderItem`: ghi nhận món ăn, số lượng, trạng thái từng món và tổng bill phát sinh.
- `Payment Session` và `Payment`: tách ý định hoặc phiên thanh toán khỏi giao dịch tài chính đã áp dụng thành công.
- `CashierShift` và `BillingInvoice`: hỗ trợ vận hành thu ngân, hóa đơn và đối soát.
- `Voucher`, `Loyalty`, `Benefit Setting`: biểu diễn quyền lợi, quy tắc áp dụng và lịch sử sử dụng.
- `Conversation`: miền trao đổi nội bộ có thể được gắn với reservation hoặc waiting list.
- `CustomerPrivacyRequest`: đại diện cho yêu cầu export hoặc anonymize dữ liệu cá nhân.
- `NotificationOutbox` và `NotificationDeliveryAttempt`: ghi nhận thông báo chờ gửi và bằng chứng giao nhận.
- `AuditLog`: bản ghi sự kiện mutation có ý nghĩa vận hành hoặc tài chính.

## 4.2 Quan hệ nghiệp vụ giữa các thực thể

1. `Branch` là điểm neo của chính sách, còn `RestaurantTable` là tài nguyên vật lý nằm dưới chi nhánh đó.
2. `TableHold` có thể được tạo trước, sau đó trở thành đầu vào cho `Reservation`.
3. `Reservation` có thể phát sinh `Preorder`, `Deposit`, `Benefit`, `Order`, `Payment`, `Invoice` và dữ liệu self-service.
4. `WaitingListEntry` có thể được notify, accept, confirm arrival và cuối cùng chuyển thành một lượt phục vụ hoặc reservation mới.
5. `ServiceSession` nối bàn với thực tế khách đang ngồi tại nhà hàng, đặc biệt trong walk-in flow.
6. `ReservationOrder` và `OrderItem` sinh ra số tiền phải thanh toán, từ đó đi sang `Payment Session`, `Payment`, `Checkout`, `Refund`.
7. `Voucher` và `Loyalty` không đứng độc lập mà bám vào `Reservation` và trạng thái thanh toán.
8. `Conversation` có thể được liên kết với `Reservation` hoặc `WaitingListEntry`, giúp staff xử lý một khách trong cùng ngữ cảnh nghiệp vụ.
9. `AuditLog`, `NotificationOutbox`, `Reporting Snapshot` và release artifact là lớp hậu kiểm và vận hành của mọi mutation quan trọng.

## 4.3 Đánh giá mức trưởng thành của các phân hệ

[[TABLE:module_maturity]]

# PHẦN V - QUY TẮC NGHIỆP VỤ VÀ RÀNG BUỘC QUAN TRỌNG

## 5.1 Boundary xác thực và actor không được chồng lấn

Customer path và staff/admin path là hai boundary tách biệt. Khách dùng `X-Customer-Token`; staff/admin dùng `X-Staff-Key`. Điều này ảnh hưởng trực tiếp tới cách viết use case và vẽ diagram: không nên gom “Người dùng hệ thống” thành một actor chung.

Trong customer self-service, một số route còn ràng buộc thêm `X-Session-Id` hoặc session-bound contract. Ý nghĩa nghiệp vụ là khách không chỉ cần “đăng nhập”, mà còn phải đi đúng phiên truy cập được gắn với dữ liệu đó.

## 5.2 Branch policy được áp dụng trước khi quyết định availability

Tất cả các quyết định về đặt bàn, đổi lịch, waiting list eligibility và hold window đều phải đi qua branch-local policy. Điều này tạo ra một quy tắc rất quan trọng cho team: nếu có use case nào động đến booking time, hãy luôn xét branch trước rồi mới xét customer hay table.

## 5.3 Mutation nhạy cảm phải chịu idempotency và stale-write guard

Hệ thống dùng `Idempotency-Key` ở nhiều route tạo, sửa, hủy hoặc thanh toán. Điều này không chỉ để chống bấm đúp, mà để bảo vệ backend trước retry từ client, queue hoặc webhook. Một số flow còn có `row_version` hoặc stale-write protection. Về nghiệp vụ, có thể hiểu đây là hàng rào chống “hai người cùng sửa một thực thể theo hai giả định khác nhau”.

## 5.4 Dòng tiền phải bám theo settlement lineage

Checkout không phải là một bước cập nhật trạng thái đơn thuần. Nó đi qua bill snapshot hoặc bill lock, settlement preview, pay/finalize và có rule rõ khi refund. Refund cũng không thể tách khỏi payment gốc. Đây là lý do tại sao finance flow có capability riêng, preview riêng và audit riêng.

## 5.5 Privacy không xóa sạch lịch sử, audit không được làm lộ dữ liệu nhạy cảm

Privacy flow của hệ thống ưu tiên anonymization hơn là hard delete. Nhiều bảng như reservations, payments, invoices, audit logs vẫn được giữ để bảo toàn history và reconciliation. Ngược lại, các trường nhạy cảm như họ tên, email, phone, token, nội dung hội thoại hoặc URL file có thể bị purge hoặc redact.

Điều này rất quan trọng khi team xây sơ đồ dữ liệu hoặc sequence diagram: xóa dữ liệu cá nhân không có nghĩa là xóa bản ghi nghiệp vụ.

## 5.6 Notification có nền tảng mạnh nhưng channel readiness chưa đồng đều

Email là kênh usable thực sự. SMS và Zalo mới ở mức stub/provider-ready. Khi mô tả use case hoặc scope release, team cần nói rõ đây là nền tảng notification có outbox, preference, quiet hour, dead-letter và health check; nhưng không nên mô tả mọi channel là đã sẵn sàng như nhau.

## 5.7 Hợp đồng API và release governance là một phần của nghiệp vụ

Backend đang dùng frozen OpenAPI artifact, route inventory, release manifest và các command verify để khóa contract. Điều này có ý nghĩa nghiệp vụ trực tiếp vì customer-web, staff-web, QA và operator đều dựa vào contract này để hiểu hệ thống.

Nói cách khác, trong dự án RestaurantPOS, “API contract ổn định” không phải chỉ là yêu cầu kỹ thuật. Nó là một phần của nghiệp vụ giao tiếp giữa backend với các consumer khác trong tổ chức.

# PHẦN VI - TẬP USE CASE LÀM ĐẦU VÀO CHO USE CASE DIAGRAM

## 6.1 Actor nên xuất hiện trong UC diagram

Để vẽ Use Case Diagram cho dự án này, nên tối thiểu có các actor sau:

- Khách hàng
- Nhân viên sảnh / thu ngân
- Quản trị viên
- Nhân viên bếp
- Operator / System
- Payment Provider / Webhook

Trong trường hợp muốn diagram gọn hơn, có thể gom `Nhân viên bếp` vào actor `Nhân viên`, nhưng khi đó nên chú thích rõ đây là actor con theo capability chứ không phải boundary auth riêng.

## 6.2 Danh sách use case tổng hợp

[[TABLE:use_case_overview]]

## 6.3 Gợi ý nhóm use case để vẽ sơ đồ

Để tránh một UC diagram quá dày và khó đọc, nên chia thành một sơ đồ tổng quan và ba đến bốn sơ đồ con.

- Sơ đồ tổng quan: chỉ giữ các actor lớn và các use case chính như đặt bàn, waiting list, phục vụ tại sảnh, order, checkout, admin master data, privacy, ops governance.
- Sơ đồ con 1 - Customer Journey: UC-01, UC-03, UC-04, UC-05, UC-06, UC-07, UC-08, UC-09, UC-16, UC-20.
- Sơ đồ con 2 - Staff POS Journey: UC-02, UC-10, UC-11, UC-12, UC-13, UC-14, UC-15, UC-21.
- Sơ đồ con 3 - Admin / Back-office: UC-02, UC-17, UC-18, UC-21.
- Sơ đồ con 4 - Ops / Governance: UC-19, UC-20, UC-22 cùng actor Payment Provider và Operator/System.

Nếu team chỉ vẽ một UC diagram duy nhất cho báo cáo tổng hợp, nên lấy các use case ở mức mục tiêu nghiệp vụ, không vẽ các action quá chi tiết như `refresh payment session` hoặc `release loyalty`. Các action đó nên để trong đặc tả chi tiết hoặc sequence diagram.

# PHẦN VII - ĐẶC TẢ USE CASE CHI TIẾT

## 7.1 Nhóm xác thực và truy cập

### UC-01 - Đăng nhập khách hàng

- Mục tiêu: Cho phép khách truy cập các chức năng self-service bằng boundary xác thực riêng.
- Actor chính: Khách hàng.
- Actor phụ: Hệ thống xác thực khách hàng.
- Tiền điều kiện: Customer auth đang bật; người dùng thuộc tập role được phép vào customer flow.
- Kích hoạt: Khách gửi yêu cầu đăng nhập với thông tin định danh và mật khẩu.
- Luồng chính:
1. Khách nhập định danh và mật khẩu.
2. Hệ thống kiểm tra throttle và tính hợp lệ của thông tin đăng nhập.
3. Nếu hợp lệ, hệ thống tạo customer access session.
4. Hệ thống trả về token truy cập và thông tin phiên liên quan.
5. Khách dùng token này để gọi các use case self-service tiếp theo.
- Luồng thay thế / ngoại lệ:
1. Nếu sai thông tin hoặc vượt ngưỡng throttle, hệ thống từ chối đăng nhập.
2. Nếu user không thuộc nhóm role khách hàng cho phép, hệ thống không cấp session.
3. Đường legacy token bridge không phải đường mặc định và chỉ dùng trong phạm vi hạn chế.
- Hậu điều kiện: Khách có access session hợp lệ cho self-service.
- Quy tắc nghiệp vụ cần nhớ: Customer auth tách biệt với staff auth; không dùng chung khóa hay session.

### UC-02 - Đăng nhập nhân viên / quản trị

- Mục tiêu: Cho phép staff hoặc admin truy cập nghiệp vụ nội bộ và bị chặn theo capability.
- Actor chính: Nhân viên hoặc quản trị viên.
- Actor phụ: Hệ thống staff auth.
- Tiền điều kiện: Staff auth đang bật; user thuộc role hợp lệ hoặc có API key hợp lệ theo cấu hình.
- Kích hoạt: Staff/admin gửi yêu cầu đăng nhập hoặc dùng staff API key.
- Luồng chính:
1. Người dùng nội bộ gửi thông tin đăng nhập.
2. Hệ thống xác thực staff actor và tạo session hoặc chấp nhận API key hợp lệ.
3. Khi gọi từng endpoint nghiệp vụ, middleware kiểm tra capability tương ứng.
4. Chỉ các use case được gán capability mới được thực hiện.
- Luồng thay thế / ngoại lệ:
1. Nếu staff key không hợp lệ hoặc role không đúng, truy cập bị từ chối.
2. Nếu actor đăng nhập được nhưng thiếu capability, hệ thống trả về forbidden ở đúng route.
- Hậu điều kiện: Staff/admin có thể truy cập tập use case phù hợp với vai trò của mình.
- Quy tắc nghiệp vụ cần nhớ: Cùng một boundary staff auth nhưng capability mới là yếu tố quyết định quyền thao tác.

## 7.2 Nhóm đặt bàn và self-service

### UC-03 - Tra cứu bàn trống

- Mục tiêu: Xác định chi nhánh có khả năng phục vụ trong khoảng thời gian cụ thể hay không.
- Actor chính: Khách hàng hoặc nhân viên.
- Actor phụ: Branch scheduling policy.
- Tiền điều kiện: Có thông tin `branch_id`, thời gian bắt đầu, thời gian kết thúc và số lượng khách.
- Kích hoạt: Người dùng yêu cầu xem bàn trống.
- Luồng chính:
1. Hệ thống xác định chi nhánh và timezone tương ứng.
2. Hệ thống resolve business hours, closure windows và booking policy của chi nhánh.
3. Hệ thống kiểm tra lead time, max advance, same-day cutoff và service buffer.
4. Hệ thống tính các bàn phù hợp theo sức chứa và trạng thái hiện tại.
5. Hệ thống trả về danh sách bàn khả dụng hoặc gợi ý phù hợp.
- Luồng thay thế / ngoại lệ:
1. Nếu chi nhánh đóng cửa hoặc đang trong blackout window, không trả về bàn khả dụng.
2. Nếu vượt quá max advance hoặc chưa đủ lead time, hệ thống từ chối theo chính sách.
- Hậu điều kiện: Người dùng biết có thể tiếp tục tạo hold hoặc reservation hay không.
- Quy tắc nghiệp vụ cần nhớ: Availability không chỉ là phép đếm bàn trống; nó là quyết định theo branch policy.

### UC-04 - Tạo / làm mới / hủy table hold

- Mục tiêu: Giữ bàn tạm thời trước khi xác nhận reservation.
- Actor chính: Khách hàng hoặc nhân viên.
- Actor phụ: Hệ thống quản lý hold.
- Tiền điều kiện: Bàn được chọn thuộc cùng một chi nhánh và đáp ứng window hợp lệ.
- Kích hoạt: Người dùng quyết định giữ bàn sau khi xem availability.
- Luồng chính:
1. Người dùng gửi yêu cầu tạo hold cho một hoặc nhiều bàn phù hợp.
2. Hệ thống kiểm tra branch consistency, thời gian và ràng buộc rate limit.
3. Hệ thống tạo hold với thời hạn tồn tại rõ ràng.
4. Trong thời gian hold còn hiệu lực, người dùng có thể xem hold, refresh hoặc hủy hold.
- Luồng thay thế / ngoại lệ:
1. Nếu bàn đã bị giữ hoặc không còn hợp lệ, hệ thống từ chối tạo hold.
2. Nếu hold hết hạn hoặc `row_version` không còn khớp, refresh bị từ chối.
3. Nếu người dùng lặp lại cùng một yêu cầu với cùng idempotency key, hệ thống không tạo thêm hold mới.
- Hậu điều kiện: Bàn được giữ tạm hoặc được giải phóng đúng cách.
- Quy tắc nghiệp vụ cần nhớ: Hold là tài sản ngắn hạn nhưng có ảnh hưởng trực tiếp đến tính đúng của reservation.

### UC-05 - Tạo reservation

- Mục tiêu: Tạo đặt bàn hợp lệ từ hold hoặc từ dữ liệu chọn bàn trực tiếp.
- Actor chính: Khách hàng hoặc nhân viên.
- Actor phụ: Reservation service, branch policy.
- Tiền điều kiện: Có thông tin khách, thời gian, branch và bàn hoặc hold hợp lệ.
- Kích hoạt: Người dùng xác nhận tạo reservation.
- Luồng chính:
1. Người dùng cung cấp thông tin cần thiết cho reservation.
2. Hệ thống kiểm tra hold, bàn, chi nhánh, conflict thời gian và branch-local policy.
3. Hệ thống tạo reservation với trạng thái ban đầu phù hợp.
4. Reservation mới có thể trở thành điểm neo cho preorder, deposit, benefits hoặc check-in sau này.
- Luồng thay thế / ngoại lệ:
1. Nếu hold không thuộc về phiên hoặc không còn hiệu lực, create bị chặn.
2. Nếu bảng thời gian xung đột, hệ thống từ chối để tránh double-book.
3. Nếu chi nhánh không cho phép slot đó theo policy, reservation không được tạo.
- Hậu điều kiện: Có reservation hợp lệ gắn với khách và chi nhánh.
- Quy tắc nghiệp vụ cần nhớ: Reservation là thực thể trung tâm và phải được tạo từ dữ liệu nhất quán.

### UC-06 - Khách tự quản lý reservation

- Mục tiêu: Cho phép khách xem, hủy hoặc đổi lịch reservation của chính mình.
- Actor chính: Khách hàng.
- Actor phụ: Customer access session.
- Tiền điều kiện: Khách đã đăng nhập đúng owner/session scope.
- Kích hoạt: Khách truy cập màn hình self-service reservation.
- Luồng chính:
1. Hệ thống hiển thị danh sách reservation thuộc về khách.
2. Khách chọn một reservation để xem chi tiết.
3. Khách có thể hủy hoặc đổi lịch nếu chính sách chi nhánh và trạng thái reservation còn cho phép.
4. Hệ thống cập nhật reservation và phản ánh trạng thái mới.
- Luồng thay thế / ngoại lệ:
1. Nếu reservation không thuộc owner scope hiện tại, truy cập bị chặn.
2. Nếu đã qua cutoff hủy hoặc cutoff đổi lịch, thao tác bị từ chối.
3. Nếu slot mới không hợp lệ theo branch policy, reschedule không thành công.
- Hậu điều kiện: Reservation được giữ nguyên hoặc thay đổi hợp lệ theo chính sách.
- Quy tắc nghiệp vụ cần nhớ: Self-service luôn đi sau owner contract; không có chuyện ai cũng xem hoặc sửa reservation bằng ID trần.

### UC-07 - Quản lý preorder và deposit

- Mục tiêu: Cho phép khách chuẩn bị trước món và nghĩa vụ đặt cọc cho reservation.
- Actor chính: Khách hàng.
- Actor phụ: Menu catalog, reservation service.
- Tiền điều kiện: Reservation tồn tại, khách có quyền truy cập và reservation còn trong giai đoạn cho phép preorder/deposit.
- Kích hoạt: Khách mở phần preorder hoặc deposit của reservation.
- Luồng chính:
1. Khách xem preorder hiện có hoặc deposit preview.
2. Khách thêm, thay thế hoặc xóa danh sách món đặt trước trong phạm vi policy cho phép.
3. Hệ thống kiểm tra menu item, quota preorder, cutoff và giá hiện hành.
4. Với deposit, hệ thống hiển thị nghĩa vụ đặt cọc, cho phép acknowledge hoặc gửi intent thanh toán.
- Luồng thay thế / ngoại lệ:
1. Nếu món không hợp lệ hoặc đã qua cutoff preorder, hệ thống từ chối cập nhật.
2. Nếu reservation không còn ở trạng thái cho phép deposit, intent bị chặn.
- Hậu điều kiện: Reservation chứa dữ liệu preorder và/hoặc trạng thái deposit cập nhật.
- Quy tắc nghiệp vụ cần nhớ: Preorder và deposit không phải dữ liệu rời; chúng là phần chuẩn bị cho một reservation cụ thể.

### UC-08 - Tạo phiên thanh toán deposit hoặc bill

- Mục tiêu: Thực hiện self-payment có payment session, confirm và webhook.
- Actor chính: Khách hàng.
- Actor phụ: Payment Provider / Webhook.
- Tiền điều kiện: Reservation có khoản deposit hoặc bill outstanding; provider tương ứng đang bật.
- Kích hoạt: Khách yêu cầu tạo payment session cho deposit hoặc bill.
- Luồng chính:
1. Hệ thống kiểm tra reservation, số tiền outstanding và owner scope.
2. Hệ thống tạo payment session với provider.
3. Khách hoàn tất thao tác thanh toán ở phía provider.
4. Provider gọi webhook hoặc khách gọi confirm/refresh.
5. Hệ thống verify chữ ký, chống replay và apply payment vào reservation hoặc bill.
6. Hệ thống cập nhật payment session sang trạng thái thành công.
- Luồng thay thế / ngoại lệ:
1. Nếu provider chưa sẵn sàng hoặc flow chưa bật, session không được tạo.
2. Nếu webhook bị trùng hoặc chữ ký sai, kết quả không được apply.
3. Nếu bill hoặc deposit không còn outstanding, confirm sẽ bị từ chối.
- Hậu điều kiện: Khoản thanh toán được ghi nhận hoặc được giữ ở trạng thái chưa hoàn tất.
- Quy tắc nghiệp vụ cần nhớ: Thanh toán không chỉ dựa vào client confirm; webhook intake là actor thật của use case này.

### UC-09 - Quản lý waiting list

- Mục tiêu: Duy trì khách trong hàng chờ và chuyển họ sang phục vụ khi có cơ hội.
- Actor chính: Khách hàng hoặc nhân viên.
- Actor phụ: Branch policy, table hold.
- Tiền điều kiện: Chi nhánh cho phép waiting list tại thời điểm hiện tại.
- Kích hoạt: Không còn bàn phù hợp hoặc staff muốn đưa khách vào hàng chờ.
- Luồng chính:
1. Khách hoặc staff tạo waiting list entry.
2. Hệ thống kiểm tra branch eligibility và trạng thái mở cửa.
3. Staff theo dõi danh sách, notify khách khi có cơ hội.
4. Khách accept hoặc decline; có thể confirm arrival.
5. Staff seat khách, từ đó tạo thành một lượt phục vụ hoặc reservation mới.
- Luồng thay thế / ngoại lệ:
1. Nếu chi nhánh đang đóng cửa hoặc waiting list bị tắt, entry không được tạo.
2. Nếu notify hold hết hạn hoặc khách decline, entry quay về nhánh xử lý khác.
3. Nếu entry không còn hợp lệ, seat bị từ chối.
- Hậu điều kiện: Khách vẫn ở waiting list hoặc đã được chuyển sang trạng thái phục vụ.
- Quy tắc nghiệp vụ cần nhớ: Waiting list là nhịp cầu giữa “chưa phục vụ được” và “sắp phục vụ được”.

## 7.3 Nhóm vận hành tại sảnh

### UC-10 - Check-in reservation hoặc mở service session walk-in

- Mục tiêu: Bắt đầu một lượt phục vụ tại chỗ.
- Actor chính: Nhân viên.
- Actor phụ: Reservation, service session.
- Tiền điều kiện: Nhân viên có capability `reservation.manage`; bàn và reservation thuộc đúng branch.
- Kích hoạt: Khách đến nơi hoặc có khách walk-in.
- Luồng chính:
1. Nhân viên xem table board và xác định reservation hoặc bàn cần phục vụ.
2. Với khách đặt trước, nhân viên check-in reservation.
3. Với khách walk-in, nhân viên mở service session cho bàn tương ứng.
4. Hệ thống đánh dấu bàn và reservation/service session ở trạng thái phục vụ.
- Luồng thay thế / ngoại lệ:
1. Nếu bàn đang conflict hoặc không đúng branch, thao tác bị từ chối.
2. Nếu reservation không còn ở trạng thái cho phép check-in, hệ thống không cho mở flow.
- Hậu điều kiện: Lượt phục vụ được bắt đầu hợp lệ trên hệ thống.
- Quy tắc nghiệp vụ cần nhớ: Walk-in và reservation check-in khác điểm vào nhưng cùng hội tụ về service state của bàn.

### UC-11 - Gán bàn / đổi bàn / giải phóng bàn

- Mục tiêu: Điều phối trạng thái bàn trong suốt quá trình phục vụ.
- Actor chính: Nhân viên.
- Actor phụ: Table board, reservation/service session.
- Tiền điều kiện: Có bàn hợp lệ và staff có capability tương ứng.
- Kích hoạt: Staff cần tối ưu sơ đồ bàn, đổi bàn cho khách hoặc kết thúc chiếm dụng bàn.
- Luồng chính:
1. Staff xem board để xác định bàn hiện tại và bàn đích.
2. Staff có thể assign suggested, assign best-fit, move table hoặc release table.
3. Hệ thống kiểm tra row version, branch consistency và trạng thái phục vụ hiện tại.
4. Nếu hợp lệ, hệ thống cập nhật bàn và các liên kết reservation/service session liên quan.
- Luồng thay thế / ngoại lệ:
1. Nếu bàn đích không còn trống hoặc vi phạm policy, move/assign bị chặn.
2. Nếu bàn đang có trạng thái xung đột, release bị từ chối cho đến khi giải quyết xong flow liên quan.
- Hậu điều kiện: Sơ đồ bàn phản ánh đúng thực tế sảnh.
- Quy tắc nghiệp vụ cần nhớ: Bàn là thực thể sống; mọi cập nhật bàn kéo theo cập nhật ngữ cảnh phục vụ.

### UC-12 - Tạo order và cập nhật món

- Mục tiêu: Ghi nhận chính xác món khách đã gọi trong lượt phục vụ.
- Actor chính: Nhân viên.
- Actor phụ: Active order, bill engine.
- Tiền điều kiện: Có service session hoặc reservation đang phục vụ.
- Kích hoạt: Staff bắt đầu nhập order cho bàn hoặc cho reservation.
- Luồng chính:
1. Staff tạo order cho bàn hoặc lấy active order hiện có.
2. Staff thêm món vào order.
3. Hệ thống kiểm tra menu item, số lượng và trạng thái order hiện tại.
4. Hệ thống cập nhật bill tạm tính và trạng thái order.
5. Staff có thể tiếp tục sửa món khi bill chưa bị khóa.
- Luồng thay thế / ngoại lệ:
1. Nếu bill đã lock hoặc order không còn ở trạng thái cho phép sửa, mutation bị chặn.
2. Nếu món không hợp lệ hoặc không còn available, hệ thống từ chối thêm món.
- Hậu điều kiện: Order và bill tạm tính được cập nhật nhất quán.
- Quy tắc nghiệp vụ cần nhớ: Order là trung tâm của consumption, không chỉ là danh sách món đơn thuần.

### UC-13 - Dispatch bếp và cập nhật vòng đời món

- Mục tiêu: Chuyển món sang bếp và theo dõi trạng thái hoàn thành.
- Actor chính: Nhân viên hoặc nhân viên bếp.
- Actor phụ: Kitchen ticket.
- Tiền điều kiện: Staff có capability `order.manage`; kitchen dispatch feature hoặc cấu hình bếp phù hợp đang bật.
- Kích hoạt: Order cần được bếp xử lý hoặc món cần cập nhật trạng thái.
- Luồng chính:
1. Staff dispatch order sang kitchen station phù hợp.
2. Hệ thống tạo hoặc cập nhật ticket theo station và category route.
3. Nhân viên bếp hoặc staff có thể fire, bump hoặc recall ticket.
4. Trạng thái món và ticket được phản ánh lại về order.
- Luồng thay thế / ngoại lệ:
1. Nếu station hoặc route không hợp lệ, dispatch bị từ chối.
2. Nếu ticket không ở trạng thái cho phép, fire/bump/recall không thực hiện được.
- Hậu điều kiện: Bếp nhận được ticket hoặc trạng thái món được cập nhật đúng.
- Quy tắc nghiệp vụ cần nhớ: Đây là domain foundation usable; khi vẽ UC diagram nên chú thích là phân hệ đang tiếp tục harden.

### UC-14 - Khóa bill và hoàn tất checkout

- Mục tiêu: Chốt bill và đóng nợ cho lượt phục vụ.
- Actor chính: Nhân viên hoặc thu ngân.
- Actor phụ: Settlement service.
- Tiền điều kiện: Order có dữ liệu bill hợp lệ; actor có capability `settlement.manage`.
- Kích hoạt: Staff quyết định chuẩn bị thanh toán.
- Luồng chính:
1. Staff tạo bill snapshot hoặc lock bill.
2. Hệ thống tính settlement preview, bao gồm tổng tiền, giảm giá, thanh toán đã áp dụng và phần còn phải thu.
3. Staff thực hiện pay hoặc finalize settlement.
4. Hệ thống cập nhật payment, bill và trạng thái reservation/order.
5. Nếu toàn bộ nghĩa vụ đã hoàn tất, reservation đi sang trạng thái completed.
- Luồng thay thế / ngoại lệ:
1. Nếu bill còn bị chỉnh sửa dở dang hoặc dữ liệu thanh toán không hợp lệ, finalize không thành công.
2. Nếu actor thiếu capability settlement, thao tác bị chặn.
- Hậu điều kiện: Nợ được đóng hoặc bill vẫn ở trạng thái chờ hoàn tất.
- Quy tắc nghiệp vụ cần nhớ: Checkout là nghiệp vụ tài chính; không nên gộp chung với “đóng order” trong sơ đồ.

### UC-15 - Refund hoặc refund kèm hủy reservation

- Mục tiêu: Hoàn tiền đúng phạm vi và giữ được lineage của giao dịch gốc.
- Actor chính: Nhân viên hoặc thu ngân.
- Actor phụ: Refund planner.
- Tiền điều kiện: Actor có capability `payment.refund`; reservation có payment đủ điều kiện hoàn.
- Kích hoạt: Khách hủy hoặc cần điều chỉnh giao dịch sau thanh toán.
- Luồng chính:
1. Staff xem refund preview.
2. Hệ thống xác định có thể hoàn khoản nào, số tiền bao nhiêu và ở loại payment nào.
3. Staff xác nhận refund hoặc refund-cancel.
4. Hệ thống tạo payment dạng refund và cập nhật reservation/payment state tương ứng.
- Luồng thay thế / ngoại lệ:
1. Nếu khoản hoàn vượt giới hạn hoặc payment không còn đủ điều kiện, refund bị từ chối.
2. Nếu cùng yêu cầu được gửi lặp lại, idempotency chặn tạo thêm refund.
- Hậu điều kiện: Có giao dịch refund hợp lệ hoặc reservation bị hủy kèm refund theo đúng rule.
- Quy tắc nghiệp vụ cần nhớ: Refund là giao dịch mới có liên hệ với giao dịch cũ, không phải xóa dấu vết giao dịch trước đó.

### UC-16 - Áp dụng voucher / loyalty / benefits

- Mục tiêu: Gắn quyền lợi khách hàng vào reservation một cách kiểm soát được.
- Actor chính: Khách hàng hoặc nhân viên.
- Actor phụ: Benefit settings, loyalty ledger.
- Tiền điều kiện: Reservation hợp lệ; voucher hoặc điểm đủ điều kiện sử dụng.
- Kích hoạt: Người dùng muốn áp dụng ưu đãi cho reservation.
- Luồng chính:
1. Hệ thống hiển thị benefits preview cho reservation.
2. Người dùng chọn apply voucher hoặc redeem loyalty.
3. Hệ thống kiểm tra điều kiện sử dụng, giới hạn và trạng thái thanh toán.
4. Nếu hợp lệ, hệ thống áp dụng quyền lợi và cập nhật reservation.
5. Khi cần, người dùng có thể remove voucher hoặc release loyalty.
- Luồng thay thế / ngoại lệ:
1. Nếu voucher hết hạn hoặc không còn khả dụng, apply bị từ chối.
2. Nếu bill đã lock hoặc thanh toán đã đi qua ngưỡng không cho sửa, thao tác benefits bị chặn.
- Hậu điều kiện: Reservation chứa benefit mới hoặc được trả về trạng thái trước đó.
- Quy tắc nghiệp vụ cần nhớ: Benefits luôn đi cùng payment state và reservation state.

## 7.4 Nhóm quản trị và hỗ trợ vận hành

### UC-17 - Quản trị dữ liệu nền và branch policy

- Mục tiêu: Duy trì dữ liệu và chính sách nền để các luồng front-office hoạt động đúng.
- Actor chính: Quản trị viên.
- Actor phụ: Branch settings, menu, finance settings.
- Tiền điều kiện: Admin có capability phù hợp như `menu.manage`, `settings.manage`, `voucher.master_data.manage`.
- Kích hoạt: Admin cần thêm mới, sửa hoặc import dữ liệu nền.
- Luồng chính:
1. Admin quản lý branch, menu category, menu item, menu price, voucher, loyalty tier, table, zone, tax profile hoặc reporting snapshot.
2. Với một số domain, admin có thể export hoặc import hàng loạt.
3. Hệ thống hỗ trợ `dry_run` và `commit` để tránh cập nhật sai hàng loạt.
4. Khi admin sửa branch policy, các flow availability và reservation downstream sẽ tự dùng chính sách mới.
- Luồng thay thế / ngoại lệ:
1. Nếu payload import lỗi, hệ thống trả về kết quả validation theo từng dòng.
2. Nếu dữ liệu vi phạm business key hoặc rule domain, commit không được thực hiện.
- Hậu điều kiện: Dữ liệu nền được cập nhật và sẵn sàng cho runtime flow.
- Quy tắc nghiệp vụ cần nhớ: Branch policy là đầu vào của booking engine; không phải cấu hình trang trí.

### UC-18 - Quản lý tồn kho, mua hàng và cấu hình bếp

- Mục tiêu: Hỗ trợ vận hành hậu cần phía sau nhà hàng.
- Actor chính: Quản trị viên.
- Actor phụ: Inventory, purchasing, kitchen routing.
- Tiền điều kiện: Admin có capability `inventory.manage` hoặc `settings.manage`.
- Kích hoạt: Nhà hàng cần cấu hình nguyên liệu, recipe, supplier, purchase order, receipt hoặc station bếp.
- Luồng chính:
1. Admin tạo hoặc sửa ingredient, recipe và movement.
2. Admin quản lý supplier, purchase order và receipt.
3. Admin cấu hình kitchen station và category route.
4. Các cấu hình này trở thành nền cho kitchen dispatch và tồn kho.
- Luồng thay thế / ngoại lệ:
1. Nếu dữ liệu recipe hoặc supplier không hợp lệ, hệ thống từ chối cập nhật.
2. Nếu route bếp không nhất quán, dispatch downstream có thể bị chặn.
- Hậu điều kiện: Hạ tầng hậu cần và bếp được cập nhật.
- Quy tắc nghiệp vụ cần nhớ: Đây là domain nền tảng đã dùng được nhưng còn cần harden thêm ở giai đoạn sau.

### UC-19 - Xử lý conversation inbox nội bộ

- Mục tiêu: Cho phép staff triage và xử lý hội thoại trong ngữ cảnh reservation hoặc waiting list.
- Actor chính: Nhân viên.
- Actor phụ: Conversation aggregate, assignment.
- Tiền điều kiện: Staff có capability `conversation.manage`.
- Kích hoạt: Có conversation mới hoặc staff cần tiếp tục xử lý conversation cũ.
- Luồng chính:
1. Staff mở danh sách conversation theo filter trạng thái, branch, assignment hoặc keyword.
2. Staff xem chi tiết conversation.
3. Staff có thể assign, take over, unassign, link reservation, link waiting list hoặc thêm internal note.
4. Khi runtime delivery cho phép, staff có thể queue outbound reply.
- Luồng thay thế / ngoại lệ:
1. Nếu conversation đang bị actor khác nắm giữ hoặc xảy ra conflict assignment, hệ thống trả về conflict.
2. Nếu outbound reply không được hỗ trợ ở runtime hoặc dữ liệu người nhận chưa sẵn sàng, hành động trả lời bị khóa.
- Hậu điều kiện: Conversation được gán người xử lý và gắn đúng ngữ cảnh nghiệp vụ.
- Quy tắc nghiệp vụ cần nhớ: Đây là inbox nội bộ, không phải sản phẩm chat omnichannel hoàn chỉnh.

### UC-20 - Xử lý privacy request và export dữ liệu

- Mục tiêu: Cho khách lấy dữ liệu của mình hoặc yêu cầu anonymize dữ liệu cá nhân.
- Actor chính: Khách hàng hoặc quản trị viên.
- Actor phụ: Data lifecycle service.
- Tiền điều kiện: Khách đã xác thực hoặc admin có capability `privacy.manage`.
- Kích hoạt: Khách yêu cầu export/anonymize hoặc admin cần review yêu cầu.
- Luồng chính:
1. Khách xem lịch sử privacy request hoặc tạo request mới.
2. Admin xem danh sách request và chọn review.
3. Admin có thể review ở chế độ `dry_run` để thấy blocker và preview tác động.
4. Nếu không còn blocker, admin có thể approve theo `commit`.
5. Hệ thống purge/redact dữ liệu nhạy cảm nhưng giữ các record cần cho finance, audit và reporting.
- Luồng thay thế / ngoại lệ:
1. Nếu còn active reservation, waiting list entry, open conversation hoặc payment session chưa terminal, commit bị chặn.
2. Nếu yêu cầu bị reject, trạng thái request phản ánh quyết định đó nhưng dữ liệu không bị mutate.
- Hậu điều kiện: Có export dữ liệu hoặc dữ liệu được anonymize đúng policy.
- Quy tắc nghiệp vụ cần nhớ: Privacy ở đây là anonymize có kiểm soát, không phải xóa sạch history nghiệp vụ.

### UC-21 - Theo dõi audit, báo cáo và đối soát

- Mục tiêu: Cho staff/admin kiểm tra được lịch sử mutation, số liệu vận hành và báo cáo tài chính cần thiết.
- Actor chính: Nhân viên hoặc quản trị viên.
- Actor phụ: Audit trail, reporting snapshot, reconciliation.
- Tiền điều kiện: Actor có capability như `audit.view` hoặc `settlement.manage`.
- Kích hoạt: Người dùng cần điều tra sự cố, xem doanh thu, xem báo cáo ca hoặc đối soát reservation.
- Luồng chính:
1. Người dùng truy cập audit trail hoặc các endpoint reporting/reconciliation.
2. Hệ thống trả về dữ liệu có filter theo actor, subject, date range hoặc reservation.
3. Người dùng dùng dữ liệu này để kiểm tra ai đã làm gì, doanh thu thế nào, invoice đã phát hành ra sao.
- Luồng thay thế / ngoại lệ:
1. Nếu actor thiếu capability, hệ thống không cho xem dữ liệu nhạy cảm.
2. Nếu snapshot reporting cần làm mới, admin phải rebuild trước khi dùng cho mục đích vận hành.
- Hậu điều kiện: Người dùng nội bộ có dữ liệu để điều hành hoặc điều tra.
- Quy tắc nghiệp vụ cần nhớ: Audit chỉ ghi mutation có giá trị vận hành/tài chính; không phải access log toàn bộ.

### UC-22 - Kiểm tra health, API contract và release readiness

- Mục tiêu: Đảm bảo backend sẵn sàng phát hành và không drift khỏi contract đã khóa.
- Actor chính: Operator / System.
- Actor phụ: Release artifact, OpenAPI contract, scheduler, notification outbox.
- Tiền điều kiện: Môi trường đã được bootstrap đúng theo hợp đồng SQL-first và có đủ thành phần runtime cần thiết.
- Kích hoạt: Team chuẩn bị release, cần kiểm tra sức khỏe hệ thống hoặc cần tạo artifact cho consumer.
- Luồng chính:
1. Operator kiểm tra `health`, `metrics` và các command doctor/readiness.
2. Hệ thống sinh hoặc verify frozen OpenAPI artifact, route inventory và release manifest.
3. Nếu cần, hệ thống tạo consumer artifacts cho FE, Postman và SDK.
4. Team dùng kết quả này để quyết định có thể phát hành hoặc bàn giao contract hay chưa.
- Luồng thay thế / ngoại lệ:
1. Nếu artifact bị drift hoặc health check đỏ, release không nên tiếp tục.
2. Nếu môi trường thiếu MySQL, Redis hoặc scheduler heartbeat, nhiều kiểm tra runtime sẽ không đạt dù unit test có thể vẫn xanh.
- Hậu điều kiện: Có bằng chứng rõ ràng về mức sẵn sàng của hệ thống.
- Quy tắc nghiệp vụ cần nhớ: Ở dự án này, release governance là một phần của vận hành nhà hàng số, không phải chi tiết kỹ thuật bên lề.

# PHẦN VIII - CÁCH SỬ DỤNG TÀI LIỆU VÀ NHỮNG ĐIỂM CẦN LƯU Ý

## 8.1 Cách team nên dùng tài liệu này

Team mới vào dự án nên đọc Phần II và III trước để hiểu dự án đang phục vụ quy trình nhà hàng nào. Sau đó đọc Phần IV để nắm thực thể nghiệp vụ và phân hệ, rồi chuyển sang Phần VI và VII nếu cần dựng diagram, viết test case, viết user story hoặc chuẩn hóa terminology giữa BA, dev và QA.

Nếu mục tiêu là vẽ diagram, có thể dùng tài liệu theo cách sau:

- Use Case Diagram: lấy actor và use case từ Phần VI và VII.
- Activity Diagram: lấy luồng trong các UC-03 đến UC-15.
- Sequence Diagram: lấy các use case có actor ngoài như UC-08 hoặc UC-22.
- State Diagram: lấy reservation lifecycle, waiting list lifecycle, payment session lifecycle hoặc kitchen ticket lifecycle từ phần lời văn.
- Context Diagram hoặc Component Diagram: lấy Phần II, III và V làm nền.

## 8.2 Những giới hạn và rủi ro còn tồn tại

Tài liệu này phản ánh đúng hiện trạng repo, nhưng vẫn có một số giới hạn mà team cần nhớ khi dùng nó làm baseline cho các đầu việc tiếp theo.

- Kitchen, inventory, purchasing và conversation inbox đã là domain thật nhưng chưa chín ngang các flow reservation hoặc checkout.
- SMS và Zalo trong notification platform hiện mới ở mức stub/provider-ready, chưa nên mô tả như kênh production-ready.
- Một phần bề mặt OpenAPI vẫn đang ở mức fallback thay vì contract-grade được curate kỹ.
- Conversation inbox đã có branch consistency ở dữ liệu, nhưng branch authorization theo membership của actor chưa phải mô hình hoàn chỉnh.
- Runtime readiness của dự án phụ thuộc MySQL, Redis và scheduler heartbeat; không thể suy ra từ test SQLite đơn thuần.

## 8.3 Kết luận ngắn

RestaurantPOS backend ở snapshot hiện tại đã có hình hài của một nền tảng vận hành nhà hàng đầy đủ hơn nhiều so với một hệ thống booking cơ bản. Các flow mạnh nhất hiện nay là auth/RBAC, availability/hold/reservation, customer self-service ưu tiên, waiting list, floor ops, order/bill, checkout/refund và release governance. Các flow nền như kitchen, inventory, purchasing và conversation inbox đã có chỗ đứng rõ ràng, nhưng cần tiếp tục harden trước khi mở rộng kỳ vọng vận hành.

Vì vậy, cách tốt nhất để dùng tài liệu này là xem nó như bản đồ nghiệp vụ hiện trạng: đủ rõ để onboarding team, đủ chặt để chuẩn hóa ngôn ngữ giữa các bên, và đủ chi tiết để làm đầu vào cho các diagram phổ biến ở các bước tiếp theo.
