import type { Metadata } from "next";
import { TableBookingPage } from "@/features/table-booking/table-booking-page";

export const metadata: Metadata = {
  title: "Đặt bàn",
  description: "Kiểm tra bàn trống, giữ bàn tạm thời và hoàn tất đặt chỗ khi bạn sẵn sàng.",
};

export default function Page() {
  return <TableBookingPage />;
}
