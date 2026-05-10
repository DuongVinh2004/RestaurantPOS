import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingBlock } from "@/components/states/state-blocks";
import { MenuPage } from "@/features/menu/menu-page";

export const metadata: Metadata = {
  title: "Thực đơn",
  description: "Xem các món đang phục vụ trước khi đặt bàn hoặc lưu món đặt trước.",
};

export default function Menu() {
  return (
    <Suspense fallback={<main className="mx-auto w-full max-w-6xl px-4 py-6"><LoadingBlock label="Đang tải thực đơn" /></main>}>
      <MenuPage />
    </Suspense>
  );
}
