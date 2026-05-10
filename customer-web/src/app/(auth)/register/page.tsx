import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingBlock } from "@/components/states/state-blocks";
import { RegisterPage } from "@/features/auth/register-page";

export const metadata: Metadata = {
  title: "Đăng ký",
  description: "Tạo tài khoản khách hàng để quản lý lịch đặt và thông tin tự phục vụ.",
};

export default function Page() {
  return (
    <Suspense fallback={<main className="mx-auto w-full max-w-md px-4 py-8"><LoadingBlock label="Đang mở trang đăng ký" /></main>}>
      <RegisterPage />
    </Suspense>
  );
}
