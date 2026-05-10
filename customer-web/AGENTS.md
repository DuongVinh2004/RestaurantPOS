# customer-web Agent Instructions

## Customer-Facing Language

- Giao diện `customer-web` mặc định dùng tiếng Việt cho mọi nội dung khách hàng nhìn thấy: tiêu đề, nút, nhãn form, trạng thái, lỗi, empty/loading states, toast, metadata và manifest.
- Không dịch tên biến, route, enum, contract key, test id, SDK/generated artifact, log/dev diagnostic chỉ dành cho kỹ thuật.
- Nếu backend trả về dữ liệu do nhà hàng nhập, hiển thị nguyên văn dữ liệu đó. Chỉ dịch fallback hoặc nhãn do frontend tạo.
- Khi thêm flow mới, ưu tiên copy ngắn, rõ thao tác tiếp theo, không hứa tính năng nếu API chưa hỗ trợ.
- Nếu cần tiếng Anh cho tích hợp bên thứ ba, đặt sau lớp adapter và giữ UI customer-facing bằng tiếng Việt.

