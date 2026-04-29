import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingBlock } from "@/components/states/state-blocks";
import { LoginPage } from "@/features/auth/login-page";

export const metadata: Metadata = {
  title: "Đăng nhập",
  description: "Đăng nhập để xem lịch đặt và các thông tin tài khoản dành riêng cho bạn.",
};

export default function Page() {
  return (
    <Suspense fallback={<main className="mx-auto w-full max-w-md px-4 py-8"><LoadingBlock label="Đang mở trang đăng nhập" /></main>}>
      <LoginPage />
    </Suspense>
  );
}
