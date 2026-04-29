import type { Metadata } from "next";
import { MenuPage } from "@/features/menu/menu-page";

export const metadata: Metadata = {
  title: "Thực đơn",
  description: "Xem thực đơn nhà hàng, tìm món và kiểm tra món có thể đặt trước khi đến.",
};

export default function Home() {
  return <MenuPage />;
}
