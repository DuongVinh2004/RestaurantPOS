# PHẦN I – GIỚI THIỆU

## 1.1 Lý do chọn đề tài

Trong bối cảnh ngành dịch vụ ăn uống ngày càng yêu cầu tốc độ phục vụ, khả năng điều phối bàn, kiểm soát order, đối soát thanh toán và quản trị dữ liệu theo thời gian thực, nhu cầu xây dựng một hệ thống quản lý vận hành nhà hàng theo hướng số hóa là rất rõ ràng. Qua khảo sát repository hiện có, có thể nhận thấy dự án RestaurantPOS không chỉ xử lý một chức năng đơn lẻ như đặt bàn, mà đang hướng tới một backend hợp nhất cho nhiều nghiệp vụ vận hành nhà hàng trong cùng một nền tảng.

Đề tài này được lựa chọn vì ba lý do chính. Thứ nhất, hệ thống có phạm vi nghiệp vụ đủ lớn và mang tính thực tiễn cao, bao phủ từ giữ bàn, đặt bàn, danh sách chờ, vận hành tại sảnh, order, bill, checkout, refund cho tới quản trị chi nhánh, báo cáo và vận hành kỹ thuật. Thứ hai, current-state của dự án cho thấy nhóm phát triển đã đi xa hơn mức dựng demo, thể hiện qua các yếu tố như phân quyền theo capability, idempotency, row version, audit trail, route gate, OpenAPI artifact và release manifest. Thứ ba, đây là một tình huống rất phù hợp cho công tác Business Analysis, vì trong cùng một hệ thống có cả những phần đã thành hình tốt, những phần mới ở mức nền tảng, và những vùng còn cần harden thêm trước khi có thể triển khai rộng.

Vì vậy, việc lập một báo cáo phân tích hiện trạng theo phong cách BA thực tế có ý nghĩa rõ ràng cho các mục đích như current-state review với khách hàng, handover cho đội mới, baseline cho planning, hoặc tài liệu tham chiếu cho giảng viên và hội đồng đánh giá đồ án.

## 1.2 Mục tiêu hệ thống

### 1.2.1 Mục tiêu nghiệp vụ

Từ source code hiện tại, có thể suy ra hệ thống hướng tới các mục tiêu nghiệp vụ sau:

- Hỗ trợ khách hàng tra cứu khả năng phục vụ theo chi nhánh, thời gian và số lượng khách.
- Cho phép giữ bàn tạm thời và tạo reservation có kiểm soát.
- Hỗ trợ self-service cho khách ở một số điểm chạm phù hợp như quản lý reservation của chính mình, preorder, deposit, bill self-payment, waiting list và benefits.
- Hỗ trợ nhân viên sảnh vận hành bàn, check-in khách, mở service session cho khách walk-in, tạo order, cập nhật trạng thái món, khóa bill, checkout và refund.
- Hỗ trợ bộ phận quản trị quản lý menu, giá, chi nhánh, bàn, voucher, loyalty, inventory, purchasing, kitchen routing, invoice settings và reporting.
- Tăng tính an toàn vận hành thông qua audit, health check, release artifact, metrics, readiness command và các gate kiểm tra hợp đồng API.

Như vậy, mục tiêu nghiệp vụ của hệ thống là hình thành một nền tảng backend thống nhất cho chuỗi vận hành nhà hàng, thay vì chỉ giải quyết một tác vụ đơn lẻ.

### 1.2.2 Mục tiêu kỹ thuật

Bên cạnh mục tiêu nghiệp vụ, current-state của dự án cũng thể hiện các mục tiêu kỹ thuật tương đối rõ:

- Tổ chức logic nghiệp vụ theo service layer, giảm phụ thuộc vào controller.
- Cung cấp API versioned và có cơ chế quản trị contract.
- Bảo vệ thao tác ghi bằng phân quyền, idempotency và concurrency control.
- Duy trì tính nhất quán dữ liệu ở cả application layer và database layer.
- Chuẩn hóa release theo định hướng SQL-first, có schema dump, patch và artifact liên quan.
- Dùng test suite và gate suite để giảm rủi ro regression ở các flow trọng yếu.

Từ góc nhìn Solution Architecture, đây là định hướng phù hợp với một backend đang được harden để đi vào vận hành thực tế.

## 1.3 Phạm vi áp dụng

### 1.3.1 Phạm vi nghiệp vụ

Phạm vi hiện tại của hệ thống, xét theo route surface, service layer và test suite, bao gồm:

- Xác thực khách hàng, nhân viên và quản trị viên.
- Tra cứu bàn trống và quản lý table hold.
- Tạo, cập nhật, hủy, đổi lịch reservation.
- Quản lý preorder, deposit và bill self-payment.
- Quản lý waiting list.
- Vận hành tại sảnh: table board, check-in, move table, release table, service session.
- Quản lý order, item lifecycle, bill preview, bill lock, checkout và refund.
- Loyalty, voucher và benefits.
- Menu, pricing và dữ liệu nền của chi nhánh.
- Inventory, purchasing và kitchen routing ở mức nền tảng có thể sử dụng.
- Cashier shift, invoice, reconciliation và reporting snapshot.
- Audit trail, notification outbox, privacy lifecycle, feature flag và operational artifact.

### 1.3.2 Đối tượng sử dụng

Hệ thống hiện hướng tới các nhóm tác nhân chính:

- Khách hàng cuối.
- Nhân viên vận hành sảnh hoặc thu ngân.
- Quản trị viên nội bộ.
- Hệ thống tích hợp hoặc webhook provider.
- Operator kỹ thuật và release engineer.

### 1.3.3 Giới hạn phạm vi

Trong phạm vi báo cáo này cần làm rõ các giới hạn sau:

- Đây là assessment dựa trên repository backend, không phải đánh giá đầy đủ toàn bộ hệ thống triển khai ngoài thực tế.
- Code và test được xem là nguồn sự thật mạnh hơn docs nếu có mâu thuẫn.
- Một số domain như kitchen, inventory, purchasing hoặc conversation inbox có hiện diện thật nhưng chưa đạt cùng mức chín như reservation hoặc checkout.
- Báo cáo không tuyên bố hệ thống đã production-ready toàn diện nếu chưa có đủ bằng chứng runtime tại môi trường mục tiêu.

## 1.4 Phương pháp phân tích

Báo cáo được xây dựng theo phương pháp phân tích hiện trạng dựa trên bằng chứng trực tiếp từ repository. Các bước phân tích gồm:

- Khảo sát cấu trúc repository để xác định loại hệ thống, entry point, module chính và hướng phụ thuộc.
- Đọc route, controller, request, resource, service, model, config và schema để tái dựng luồng nghiệp vụ thực tế.
- Đối chiếu implementation với test suite, docs, runbook và artifact release.
- Phân loại nhận định theo mức độ chắc chắn: fact từ code, fact từ test, fact từ route/config/schema, suy luận có cơ sở và phần chưa đủ bằng chứng.
- Đánh giá mức trưởng thành của từng domain theo mức độ khép kín của flow, guard, contract, test và dấu hiệu vận hành.

Phương pháp này phù hợp với mục tiêu của báo cáo BA hiện trạng, vì trọng tâm không phải thiết kế một hệ thống lý tưởng mới, mà là mô tả đúng hệ thống đang tồn tại.

## 1.5 Công cụ sử dụng

Trong quá trình phân tích và lập báo cáo, các công cụ và nguồn dữ liệu chính được sử dụng gồm:

[[TABLE:tools]]

# PHẦN II – PHÂN TÍCH YÊU CẦU

## 2.1 Khảo sát hiện trạng

### 2.1.1 Tổng quan hiện trạng repository

Repository hiện tại là một backend Laravel 12 / PHP 8.2, được tổ chức theo hướng API-first. Web surface gần như không phải phần chính của sản phẩm; phần lõi của hệ thống nằm ở API `/api/v1` và các command phục vụ vận hành, release và readiness. Business logic được đặt chủ yếu trong `app/Services`, cho thấy dự án đi theo mô hình controller mỏng, service dày.

Tại thời điểm assessment, hệ thống có 227 API route, trong đó bao gồm route cho customer/public, customer auth, staff auth, staff, admin, webhook và ops. Ngoài ra còn có route tương thích `/api/user`. Đây là bề mặt nghiệp vụ tương đối lớn, chứng minh sản phẩm đã vượt khỏi giai đoạn proof-of-concept.

### 2.1.2 Các vùng chức năng mạnh nhất

Qua việc đối chiếu code, test và gate suite, các vùng trưởng thành nhất hiện nay là:

- Auth / capability model.
- Table availability và table hold.
- Reservation lifecycle và customer self-service reservation.
- Waiting list.
- Floor operations và service session.
- Order, bill, checkout, refund, cashier shift, invoice và reconciliation.
- API contract governance, route gate và release artifact.

Đây là những phần có flow nghiệp vụ rõ, guard tương đối chặt, test dày hơn mức trung bình và có bằng chứng về tư duy vận hành thực tế.

### 2.1.3 Các vùng còn nền tảng hoặc chưa khép kín

Một số vùng hiện vẫn ở mức foundation hoặc cần tiếp tục harden:

- Kitchen dispatch và routing.
- Inventory và purchasing.
- Conversation inbox.
- Một số tích hợp ngoài như SMS/Zalo.
- Một số nhóm OpenAPI còn thiên về fallback hơn là curated contract.

Điều này không có nghĩa là các phần trên không dùng được, nhưng stakeholder cần hiểu rõ chúng chưa nên được xem là các domain đã hoàn thiện ngang với lõi reservation hoặc finance.

## 2.2 Yêu cầu hệ thống

### 2.2.1 Yêu cầu chức năng

Các yêu cầu chức năng hiện diện trong current-state có thể nhóm như sau:

- Hệ thống phải cho phép tra cứu bàn trống theo thời gian, số khách và chi nhánh.
- Hệ thống phải cho phép tạo, làm mới và hủy table hold.
- Hệ thống phải cho phép tạo reservation và gắn bàn phù hợp.
- Hệ thống phải cho phép khách hàng tự xem, hủy hoặc đổi lịch reservation của chính mình trong phạm vi chính sách cho phép.
- Hệ thống phải cho phép quản lý waiting list và chuyển từ waiting list sang seating khi có bàn phù hợp.
- Hệ thống phải cho phép nhân viên mở service session cho khách walk-in, check-in reservation, chuyển bàn và giải phóng bàn.
- Hệ thống phải cho phép tạo order, cập nhật món, khóa bill, checkout và refund.
- Hệ thống phải cho phép xử lý deposit và bill payment session cho khách hàng ở các flow self-service đã được hỗ trợ.
- Hệ thống phải cho phép áp dụng voucher, loyalty và benefits có ràng buộc với trạng thái thanh toán.
- Hệ thống phải cho phép admin quản lý menu, pricing, branch settings, finance settings và một phần inventory / purchasing / kitchen foundation.
- Hệ thống phải cho phép hệ thống vận hành sinh health report, contract artifact, release manifest và các báo cáo readiness liên quan.

### 2.2.2 Yêu cầu phi chức năng

Các yêu cầu phi chức năng suy ra từ current-state bao gồm:

- Bảo mật: tách customer auth và staff auth, dùng capability middleware cho route staff/admin.
- Toàn vẹn dữ liệu: dùng constraint, trigger, row version và integrity guard cho nhiều flow quan trọng.
- Chịu lỗi thao tác lặp: dùng idempotency cho nhiều mutation nhạy cảm.
- Truy vết: có audit trail, release artifact, route inventory, performance report và manifest.
- Bảo trì: business logic tập trung ở service, có test suite lớn và docs/runbook đi kèm.
- Khả năng mở rộng: hỗ trợ multi-branch, feature flag và contract artifact cho consumer.
- Vận hành: có doctor, health, metrics, route gate, launch readiness, DR drill và notification health command.

## 2.3 Phân tích nghiệp vụ

### 2.3.1 Các actor chính

Current-state cho thấy năm nhóm actor chính:

- Khách hàng: tra cứu bàn, tự quản lý reservation, waiting list, deposit, bill payment và benefits trong phạm vi được cho phép.
- Nhân viên: vận hành bàn, check-in, service session, order, checkout, refund và xem các thông tin tài chính cần thiết.
- Quản trị viên: quản lý dữ liệu nền, branch, menu, giá, voucher, inventory, purchasing, kitchen foundation, reporting và privacy review.
- Hệ thống / operator: chạy scheduler, health, readiness, route gate, manifest, notification processing và các command phục vụ release.
- Webhook provider: gửi sự kiện xác nhận thanh toán vào hệ thống.

### 2.3.2 Chuỗi nghiệp vụ cốt lõi

Chuỗi nghiệp vụ cốt lõi của hệ thống có thể mô tả ở mức tổng quát như sau:

1. Khách hàng tra cứu bàn trống và có thể giữ bàn tạm thời.
2. Từ hold, khách hoặc nhân viên tạo reservation.
3. Nếu chưa có bàn phù hợp, khách có thể vào waiting list.
4. Khi khách đến, nhân viên check-in hoặc tạo service session cho khách walk-in.
5. Nhân viên tạo order, cập nhật vòng đời món và theo dõi bill.
6. Hệ thống khóa bill, thực hiện checkout hoặc self-payment tùy flow.
7. Nếu cần, nhân viên xử lý refund và các nghiệp vụ tài chính liên quan.
8. Sau khi kết thúc, dữ liệu tiếp tục đi vào reporting, audit và các artifact vận hành.

Điểm đáng chú ý là các flow này không tồn tại rời rạc, mà đã có liên kết logic khá rõ giữa reservation, order, payment, table state và reporting.

### 2.3.3 Các điểm kiểm soát nghiệp vụ

Các điểm kiểm soát nghiệp vụ quan trọng được quan sát thấy gồm:

- Không cho phép chồng chéo bàn hoặc hold trái quy tắc.
- Không cho phép một số thao tác khi bill đã lock hoặc đã final payment.
- Bảo vệ refund theo lineage của payment gốc.
- Kiểm soát mutation bằng capability, idempotency và row version.
- Tách boundary giữa customer path, staff path và webhook path.
- Dùng feature flag cho các vùng rollout nhạy cảm.

## 2.4 Đặc tả Use Case tổng quan

Bảng dưới đây tổng hợp các use case chính đang hiện diện trong current-state của hệ thống:

[[TABLE:use_case_overview]]

# PHẦN III – THIẾT KẾ HỆ THỐNG

## 3.1 Use Case Diagram

Use Case Diagram dưới đây mô tả tổng quan mối quan hệ giữa các actor chính và các nhóm chức năng lớn của hệ thống RestaurantPOS ở thời điểm hiện tại.

[[IMAGE:use_case_diagram|Hình 3.1. Use Case Diagram tổng quan current-state của hệ thống RestaurantPOS]]

## 3.2 Mô tả chi tiết Use Case

### UC-01 – Tra cứu bàn trống và giữ bàn

- Mục tiêu: Xác định khả năng phục vụ và giữ bàn tạm thời trước khi tạo reservation.
- Tác nhân chính: Khách hàng, nhân viên.
- Tiền điều kiện: Có chi nhánh, thời gian và số khách hợp lệ.
- Hậu điều kiện: Có danh sách bàn phù hợp hoặc tạo được table hold hợp lệ.
- Luồng chính:
- Hệ thống kiểm tra bàn trống theo branch, thời gian và policy.
- Nếu có bàn phù hợp, người dùng có thể tạo hold.
- Hold có thể được xem, làm mới hoặc hủy theo đúng scope cho phép.
- Luồng thay thế:
- Nếu không có bàn phù hợp, hệ thống trả kết quả không khả dụng hoặc định hướng sang waiting list.
- Nếu hold hết hạn hoặc row version không còn hợp lệ, thao tác bị từ chối.
- Ghi chú current-state: Đây là một trong các flow mạnh nhất của hệ thống.

### UC-02 – Tạo reservation

- Mục tiêu: Tạo một reservation hợp lệ có liên kết với bàn và chi nhánh.
- Tác nhân chính: Khách hàng, nhân viên.
- Tiền điều kiện: Bàn hoặc hold hợp lệ; dữ liệu đầu vào hợp lệ.
- Hậu điều kiện: Reservation được tạo thành công và đi vào vòng đời quản lý.
- Luồng chính:
- Người dùng nhập thông tin đặt bàn.
- Hệ thống kiểm tra conflict, policy, branch scope và dữ liệu liên quan.
- Reservation được tạo, có thể gắn preorder hoặc trạng thái deposit tùy ngữ cảnh.
- Luồng thay thế:
- Nếu bàn xung đột hoặc giữ bàn không hợp lệ, hệ thống từ chối.
- Nếu branch policy không cho phép, yêu cầu không được chấp nhận.

### UC-03 – Khách tự quản lý reservation

- Mục tiêu: Cho phép khách xem, hủy hoặc đổi lịch reservation của chính mình.
- Tác nhân chính: Khách hàng.
- Tiền điều kiện: Khách đã được xác thực đúng owner/session scope.
- Hậu điều kiện: Reservation được hiển thị hoặc thay đổi theo chính sách cho phép.
- Luồng chính:
- Khách xem danh sách reservation của mình.
- Chọn một reservation cụ thể để xem chi tiết.
- Có thể hủy hoặc reschedule nếu chưa vượt qua ngưỡng hạn chế.
- Luồng thay thế:
- Nếu reservation đã ở trạng thái không cho phép sửa, hệ thống từ chối.
- Nếu không đúng owner scope, hệ thống không cho truy cập.

### UC-04 – Quản lý preorder

- Mục tiêu: Gắn hoặc thay đổi món đặt trước cho reservation.
- Tác nhân chính: Khách hàng.
- Tiền điều kiện: Reservation hợp lệ và còn cho phép preorder.
- Hậu điều kiện: Preorder được cập nhật theo giá và quy tắc hiện hành.
- Luồng chính:
- Khách xem preorder hiện có.
- Thêm mới hoặc thay thế danh sách món đặt trước.
- Hệ thống kiểm tra món có cho phép preorder, giá hiệu lực và hạn chót.
- Luồng thay thế:
- Nếu món không hợp lệ hoặc đã quá cutoff, thao tác bị từ chối.

### UC-05 – Quản lý deposit

- Mục tiêu: Quản lý yêu cầu đặt cọc và payment session cho deposit.
- Tác nhân chính: Khách hàng.
- Tiền điều kiện: Reservation đang ở trạng thái phù hợp và thuộc quyền truy cập của khách.
- Hậu điều kiện: Trạng thái deposit được cập nhật hoặc payment session được tạo.
- Luồng chính:
- Khách xem trạng thái deposit của reservation.
- Xác nhận hoặc gửi intent deposit.
- Hệ thống tạo payment session khi flow self-payment được hỗ trợ.
- Luồng thay thế:
- Nếu provider chưa được bật hoặc điều kiện thanh toán không phù hợp, hệ thống trả lỗi nghiệp vụ.

### UC-06 – Quản lý waiting list

- Mục tiêu: Tiếp nhận nhu cầu khi chưa có bàn trống và chuyển sang seating khi có cơ hội phục vụ.
- Tác nhân chính: Khách hàng, nhân viên.
- Tiền điều kiện: Chi nhánh đang hoạt động và dữ liệu khách hợp lệ.
- Hậu điều kiện: Khách được đưa vào danh sách chờ, được thông báo hoặc được xếp bàn.
- Luồng chính:
- Khách hoặc nhân viên tạo waiting list entry.
- Nhân viên theo dõi danh sách và gửi thông báo khi có bàn.
- Khách có thể chấp nhận, từ chối hoặc xác nhận đã đến.
- Nhân viên có thể seat khách và chuyển sang reservation hoặc trạng thái phục vụ.
- Luồng thay thế:
- Nếu entry hết hiệu lực, bị hủy hoặc không còn đủ điều kiện, hệ thống không cho tiếp tục flow.

### UC-07 – Vận hành sảnh và service session

- Mục tiêu: Hỗ trợ nhân viên quản lý trạng thái bàn và phục vụ khách tại chỗ.
- Tác nhân chính: Nhân viên.
- Tiền điều kiện: Nhân viên có quyền tương ứng và bàn thuộc branch phù hợp.
- Hậu điều kiện: Bàn được check-in, gắn service session, chuyển bàn hoặc giải phóng đúng quy tắc.
- Luồng chính:
- Nhân viên xem table board để nắm trạng thái toàn sảnh.
- Thực hiện check-in reservation hoặc mở service session cho khách walk-in.
- Có thể chuyển bàn, giải phóng bàn hoặc theo dõi trạng thái phục vụ.
- Luồng thay thế:
- Nếu bàn đang bị xung đột hoặc row version không còn hợp lệ, hệ thống từ chối thao tác.

### UC-08 – Quản lý order và bill

- Mục tiêu: Ghi nhận món ăn, vòng đời món và bill phục vụ thanh toán.
- Tác nhân chính: Nhân viên.
- Tiền điều kiện: Có reservation hoặc service session đang hoạt động.
- Hậu điều kiện: Order và bill được cập nhật nhất quán.
- Luồng chính:
- Nhân viên tạo hoặc lấy active order theo bàn / reservation.
- Thêm món và cập nhật trạng thái từng món.
- Xem bill preview và khóa bill trước khi thanh toán.
- Luồng thay thế:
- Nếu bill đã khóa hoặc payment state không cho phép, một số mutation sẽ bị chặn.

### UC-09 – Checkout và refund

- Mục tiêu: Hoàn tất thanh toán và xử lý hoàn tiền nếu cần.
- Tác nhân chính: Nhân viên có quyền settlement/finance.
- Tiền điều kiện: Có order / reservation đủ điều kiện thanh toán.
- Hậu điều kiện: Reservation được hoàn tất thanh toán hoặc refund được ghi nhận đúng.
- Luồng chính:
- Hệ thống tạo settlement preview.
- Nhân viên xác nhận thanh toán và finalize settlement.
- Nếu phát sinh hoàn tiền, hệ thống tính toán refund và cập nhật trạng thái liên quan.
- Luồng thay thế:
- Nếu payment không hợp lệ, refund vượt quá giới hạn hoặc bị replay, hệ thống từ chối.
- Ghi chú current-state: Đây là một trong những vùng được harden tốt nhất.

### UC-10 – Quản lý loyalty, voucher và benefits

- Mục tiêu: Áp dụng quyền lợi khách hàng vào reservation một cách có kiểm soát.
- Tác nhân chính: Khách hàng, nhân viên.
- Tiền điều kiện: Reservation còn hợp lệ và quyền lợi còn sử dụng được.
- Hậu điều kiện: Voucher hoặc loyalty được áp dụng, gỡ bỏ hoặc đồng bộ với payment state.
- Luồng chính:
- Người dùng xem benefits hiện có.
- Chọn áp dụng voucher hoặc redeem loyalty.
- Hệ thống kiểm tra điều kiện và cập nhật reservation.
- Luồng thay thế:
- Nếu bill đã lock, đã final payment hoặc voucher không còn hợp lệ, thao tác bị chặn.

### UC-11 – Quản lý dữ liệu nền và chi nhánh

- Mục tiêu: Duy trì dữ liệu nền phục vụ vận hành hệ thống.
- Tác nhân chính: Quản trị viên.
- Tiền điều kiện: Quản trị viên có quyền phù hợp.
- Hậu điều kiện: Dữ liệu nền được thêm, sửa hoặc import/export thành công.
- Luồng chính:
- Admin quản lý branch, menu, giá, voucher, loyalty tier, bàn, khu vực, tax profile và các cấu hình nền.
- Có thể dùng import/export hàng loạt ở một số domain.
- Luồng thay thế:
- Nếu dữ liệu không hợp lệ hoặc bị stale update, hệ thống từ chối.

### UC-12 – Quản lý inventory, purchasing và kitchen

- Mục tiêu: Hỗ trợ vận hành hậu cần phía sau nhà hàng.
- Tác nhân chính: Quản trị viên, nhân viên bếp.
- Tiền điều kiện: Feature tương ứng được bật và dữ liệu nền đầy đủ.
- Hậu điều kiện: Ingredient, purchase order, receipt hoặc kitchen ticket được cập nhật.
- Luồng chính:
- Admin quản lý ingredient, recipe, supplier, purchase order, receiving, kitchen station và category route.
- Staff bếp có thể xem và thao tác ticket ở khu vực kitchen dispatch.
- Luồng thay thế:
- Nếu feature flag tắt hoặc station/route không hợp lệ, hệ thống không cho thao tác.
- Ghi chú current-state: Đây là nhóm foundation có thể dùng, chưa đạt cùng mức hardening với reservation/checkout.

### UC-13 – Quản lý cashier shift, invoice và reconciliation

- Mục tiêu: Hỗ trợ vận hành tài chính nội bộ theo ca.
- Tác nhân chính: Nhân viên có quyền tài chính.
- Tiền điều kiện: Có quyền phù hợp và dữ liệu thanh toán liên quan.
- Hậu điều kiện: Shift, invoice hoặc báo cáo đối soát được cập nhật chính xác.
- Luồng chính:
- Mở cashier shift.
- Theo dõi và tổng hợp payment trong ca.
- Đóng shift, issue invoice và xem reconciliation theo nhu cầu.
- Luồng thay thế:
- Nếu đã có shift mở hoặc reservation chưa đủ điều kiện xuất invoice, hệ thống từ chối.

### UC-14 – Vận hành hệ thống và release governance

- Mục tiêu: Kiểm tra sức khỏe hệ thống và đảm bảo release có kiểm soát.
- Tác nhân chính: Operator, release engineer, system scheduler.
- Tiền điều kiện: Môi trường được cấu hình phù hợp.
- Hậu điều kiện: Hệ thống sinh ra report, artifact và kết quả readiness phục vụ vận hành.
- Luồng chính:
- Chạy doctor, route gate, API contract và release manifest.
- Sinh OpenAPI và artifact cho API consumer.
- Theo dõi health, metrics, outbox, DR metadata và performance verification.
- Luồng thay thế:
- Nếu thiếu artifact hoặc hạ tầng chưa sẵn sàng, các command sẽ trả fail hoặc warning.

## 3.3 Activity Diagram

Activity Diagram dưới đây mô tả luồng nghiệp vụ điển hình từ lúc khách tra cứu bàn cho đến khi hoàn tất thanh toán. Đây là flow đại diện cho cách các module reservation, floor ops, order và finance liên kết với nhau trong current-state.

[[IMAGE:activity_diagram|Hình 3.2. Activity Diagram cho luồng đặt bàn - phục vụ - thanh toán]]

## 3.4 Sequence Diagram

Sequence Diagram được lựa chọn cho báo cáo này là luồng bill self-payment có webhook, vì đây là một trong những flow phản ánh rõ cách hệ thống phối hợp giữa API nội bộ, payment session service, provider và webhook intake.

[[IMAGE:sequence_diagram|Hình 3.3. Sequence Diagram cho luồng bill self-payment có webhook]]

## 3.5 State Diagram

State Diagram dưới đây mô tả vòng đời chính của reservation trong current-state. Sơ đồ giúp người đọc hình dung rằng reservation không phải chỉ là một bản ghi tĩnh, mà có nhiều trạng thái nghiệp vụ và guard đi kèm.

[[IMAGE:state_diagram|Hình 3.4. State Diagram của reservation lifecycle]]

## 3.6 Thiết kế cơ sở dữ liệu (ERD)

Do repository có schema dữ liệu khá lớn, ERD trong báo cáo này được trình bày ở mức khái niệm, tập trung vào các thực thể cốt lõi liên quan tới user, branch, table, hold, waiting list, reservation, order, payment, invoice và benefit.

[[IMAGE:erd_diagram|Hình 3.5. ERD mức khái niệm của các thực thể chính]]

## 3.7 Class Diagram & Component Diagram

### 3.7.1 Class Diagram

Class Diagram mức khái niệm dưới đây không nhằm phản ánh đầy đủ toàn bộ class code trong repository, mà tập trung thể hiện quan hệ giữa các đối tượng nghiệp vụ quan trọng nhất.

[[IMAGE:class_diagram|Hình 3.6. Class Diagram mức khái niệm cho domain cốt lõi]]

### 3.7.2 Component Diagram

Component Diagram thể hiện cách backend hiện tại được tổ chức theo các lớp chính: route, controller, request/resource, service, support, model và database, cùng với các thành phần ngoài như Redis, provider và release artifact.

[[IMAGE:component_diagram|Hình 3.7. Component Diagram current-state của backend RestaurantPOS]]

## 3.8 Deployment Diagram

Deployment Diagram mô tả hệ thống ở mức triển khai khái quát: client bên ngoài gọi vào Laravel API, ứng dụng làm việc với MySQL, Redis, storage artifact, mailer và một số provider tích hợp.

[[IMAGE:deployment_diagram|Hình 3.8. Deployment Diagram mức khái quát]]

# PHẦN IV – TRIỂN KHAI & VẬN HÀNH

## 4.1 Kiến trúc công nghệ

Xét theo source code hiện tại, hệ thống được xây dựng theo hướng API-first trên nền Laravel 12 và PHP 8.2. Kiến trúc phù hợp nhất với mô hình modular monolith: ứng dụng vẫn là một backend thống nhất, nhưng nghiệp vụ đã được chia thành nhiều service và nhóm route rõ ràng.

Route được tổ chức theo actor và phạm vi truy cập như customer, staff, admin, webhook và ops. Business logic chủ yếu nằm ở `app/Services`, trong khi lớp controller giữ vai trò tiếp nhận request và điều hướng sang service. Dữ liệu được quản lý bằng mô hình SQL-first với schema dump và patch SQL được xem như một phần của hợp đồng release.

Về thành phần hỗ trợ, hệ thống có MySQL-compatible schema, Redis cho một số hành vi runtime, storage cho artifact release và nhiều command vận hành. Đây là nền tảng công nghệ phù hợp với một backend nghiệp vụ có yêu cầu kiểm soát tương đối cao.

## 4.2 Bảo mật

Current-state cho thấy hệ thống có nền tảng bảo mật khá tốt ở các vùng trọng yếu:

- Tách customer auth và staff auth.
- Dùng capability middleware cho route staff/admin.
- Dùng idempotency cho nhiều mutation nhạy cảm.
- Dùng row version và conflict mapping cho cập nhật đồng thời.
- Có audit trail để truy vết actor và hành động.

Tuy vậy, assessment hiện tại chưa có đủ bằng chứng để kết luận toàn bộ secret management hoặc key rotation ngoài môi trường triển khai thực tế. Vì vậy, bảo mật ở mức code và flow tương đối tốt, nhưng vẫn cần xác nhận thêm ở tầng vận hành.

## 4.3 Tích hợp

Hệ thống hiện có ba nhóm tích hợp đáng chú ý.

Thứ nhất là payment integration với payment session, webhook intake, signature verification, replay protection và provider registry. Thứ hai là notification platform với outbox, preference, health check và dead-letter. Thứ ba là nhóm artifact cho API consumer như OpenAPI, Postman collection và TypeScript SDK.

Điểm cần lưu ý là mức trưởng thành của các tích hợp không đồng đều. Payment architecture là có thật nhưng chưa nên khẳng định đã fully proven với một provider production cụ thể chỉ từ repo này. Email khả dụng hơn SMS/Zalo. Vì vậy, cần đánh giá từng loại tích hợp riêng thay vì kết luận chung chung rằng “hệ thống đã tích hợp hoàn chỉnh”.

## 4.4 Quản trị hệ thống

Ở lớp quản trị nghiệp vụ, admin có thể quản lý nhiều nhóm dữ liệu nền như branch, menu, pricing, voucher, loyalty tier, finance settings, inventory, purchasing và kitchen foundation. Một số vùng có hỗ trợ import/export hàng loạt, phù hợp với nhu cầu vận hành thực tế.

Ở lớp quản trị kỹ thuật, repository có nhiều command phục vụ kiểm tra và release như doctor, route gate, API contract, release manifest, launch readiness, performance verify và disaster recovery drill. Đây là điểm mạnh rõ rệt của current-state.

Ngoài ra, feature flag được dùng để rollout có kiểm soát cho một số vùng chức năng nhạy cảm. Đây là cách làm phù hợp trong giai đoạn hệ thống đang tiếp tục harden.

## 4.5 Yêu cầu phi chức năng chi tiết

Về hiệu năng, hệ thống đã có performance budget test và performance verification artifact, cho thấy nhóm phát triển có ý thức kiểm soát hot path. Về độ tin cậy, hệ thống có health endpoint, metrics endpoint, doctor command, scheduler heartbeat check và notification outbox health.

Về khả năng mở rộng, multi-branch là logic thật chứ không chỉ là metadata. Về khả năng bảo trì, kiến trúc service layer, test suite và release artifact giúp hệ thống tương đối dễ kiểm soát hơn so với một codebase controller-heavy. Về khả năng truy vết, audit trail và các artifact vận hành là điểm cộng lớn.

Tuy nhiên, các bằng chứng này chủ yếu cho thấy hệ thống có production intent mạnh, chứ chưa đủ để khẳng định toàn bộ phi chức năng đã được kiểm chứng hoàn toàn ở môi trường triển khai mục tiêu.

## 4.6 Chiến lược kiểm thử

Current-state cho thấy chiến lược kiểm thử được xây dựng khá bài bản. Hệ thống có cả feature test và unit test với số lượng lớn, đồng thời có các gate suite theo nhóm nghiệp vụ. `core_ops_gate_suite` tập trung vào reservation, availability, waiting list và floor operations. `round5_gate_suite` tập trung vào checkout, payment, refund, webhook và các rủi ro tài chính.

Ngoài kiểm thử nghiệp vụ, repository còn có test cho route surface, OpenAPI coverage, command vận hành, performance budget và readiness logic. Điều này cho thấy chất lượng đang được kiểm soát không chỉ ở tầng business flow mà cả ở tầng contract và release.

Hạn chế là độ phủ không đồng đều giữa các domain. Các vùng mới như kitchen, inventory hoặc purchasing có test nhưng chưa sâu bằng nhóm reservation hoặc finance.

## 4.7 Kế hoạch triển khai

Nếu dùng repository này làm baseline cho rollout thực tế, cách tiếp cận phù hợp là triển khai theo pha.

Giai đoạn đầu nên tập trung vào auth, table availability, reservation, waiting list, floor operations, order lifecycle và checkout cơ bản. Đây là lõi vận hành trực tiếp và cũng là vùng đã có mức trưởng thành cao hơn.

Giai đoạn tiếp theo có thể mở rộng sang deposit, bill self-payment, loyalty/voucher, cashier shift, invoice, reconciliation và reporting.

Sau đó mới nên tiếp tục harden và rollout có kiểm soát cho kitchen, inventory, purchasing, conversation inbox và các module feature-flagged khác.

Trước mỗi đợt release, cần thực hiện tối thiểu các bước doctor, route gate, API contract, release manifest và bộ test trọng yếu tương ứng với phạm vi thay đổi.

## 4.8 Rủi ro & biện pháp khắc phục

Rủi ro lớn nhất của current-state là mức trưởng thành giữa các domain chưa đồng đều. Nếu stakeholder đánh giá hệ thống như một khối hoàn chỉnh đồng nhất, rất dễ dẫn tới kỳ vọng sai. Biện pháp phù hợp là công bố maturity theo domain và chỉ cam kết rollout với các vùng đã có đủ bằng chứng.

Rủi ro thứ hai là readiness phụ thuộc mạnh vào môi trường. Dù repo có nhiều command kiểm tra tốt, kết quả local tại thời điểm assessment vẫn cho thấy Redis và scheduler heartbeat chưa pass hoàn toàn. Điều này cần được xử lý bằng staging gần production hơn và lưu artifact readiness theo từng đợt.

Rủi ro thứ ba là contract drift giữa docs, generated artifact và runtime route surface. Biện pháp là lấy code, route gate và generated artifact làm nguồn sự thật chính, đồng thời cập nhật docs cùng lúc với thay đổi contract.

Rủi ro thứ tư là một số tích hợp ngoài chưa được chứng minh đầy đủ ở mức production evidence. Vì vậy, cần có pilot hoặc UAT riêng trước khi rollout chính thức cho payment provider hoặc các kênh notification nâng cao.

# PHẦN V – KẾT LUẬN & HƯỚNG PHÁT TRIỂN

## 5.1 Tóm tắt hệ thống

RestaurantPOS hiện là một backend quản lý vận hành nhà hàng theo hướng API-first, có phạm vi nghiệp vụ rộng và đã vượt qua giai đoạn prototype sơ khai. Hệ thống bao phủ nhiều domain từ đặt bàn, waiting list, floor operations, order, thanh toán cho đến quản trị dữ liệu nền, reporting, audit, privacy lifecycle và artifact vận hành.

## 5.2 Kết quả đạt được

Current-state cho thấy dự án đã đạt được nhiều kết quả quan trọng:

- Hình thành chuỗi nghiệp vụ lõi khá rõ từ availability tới thanh toán.
- Tách vai trò customer, staff, admin và system tương đối mạch lạc.
- Có nhiều flow được bảo vệ bằng authz, idempotency, row version và audit.
- Có test suite và gate suite cho nhiều vùng trọng yếu.
- Có release artifact và readiness command phục vụ kiểm soát thay đổi.

Đây là nền tảng tốt để dùng làm tài liệu current-state cho khách hàng, giảng viên, PM/PO, BA hoặc đội tiếp nhận hệ thống.

## 5.3 Hạn chế hiện tại

Hạn chế lớn nhất là sự không đồng đều về độ chín giữa các domain. Reservation, waiting list, floor operations, order và checkout là các vùng mạnh; trong khi inventory, purchasing, kitchen và conversation inbox vẫn đang ở mức nền tảng hoặc rollout có kiểm soát.

Ngoài ra, chưa nên xem sự hiện diện của command, artifact hoặc feature flag là bằng chứng đủ để kết luận hệ thống đã sẵn sàng production trên mọi mặt. Phần docs và contract cũng cần tiếp tục đồng bộ chặt với runtime để giảm drift.

## 5.4 Hướng phát triển tương lai

Hướng phát triển phù hợp nhất là tiếp tục “làm chắc lõi trước, mở rộng sau”. Trong ngắn hạn, nên ưu tiên harden các flow đã gần hoàn chỉnh nhất như auth, reservation, waiting list, service session, order lifecycle, checkout/refund và readiness verification.

Trong trung hạn, nên củng cố thêm nhóm tài chính và self-service gồm deposit, bill self-payment, loyalty/voucher, invoice, reconciliation và reporting. Với các domain như kitchen, inventory, purchasing và conversation inbox, nên phát triển theo nhu cầu thực tế và rollout bằng feature flag, tránh overbuild khi core flow vẫn còn cần tăng độ tin cậy.

Kết luận chung, dự án đang ở giai đoạn trưởng thành trung gian nhưng có định hướng tốt. Nếu tiếp tục giữ kỷ luật thiết kế, kiểm thử và vận hành như current-state đang thể hiện, hệ thống có tiềm năng tiến tới một backend quản lý nhà hàng đủ mạnh cho triển khai thực tế theo từng pha.
