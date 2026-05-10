import type { Metadata } from "next";
import { HomePage } from "@/features/home/home-page";

export const metadata: Metadata = {
  title: "RestaurantPOS",
  description: "Chọn chi nhánh, xem thực đơn, đặt bàn và tiếp tục bằng phiên khách hoặc tài khoản đã đăng nhập.",
};

export default function Home() {
  return <HomePage />;
}
