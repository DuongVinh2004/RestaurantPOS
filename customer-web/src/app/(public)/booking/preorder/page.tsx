import type { Metadata } from "next";
import { BookingPreorderPage } from "@/features/table-booking/booking-preorder-page";

export const metadata: Metadata = {
  title: "Chọn món trước",
  description: "Chọn các món ngon để được phục vụ ngay khi đến nhà hàng.",
};

export default function Page() {
  return <BookingPreorderPage />;
}
