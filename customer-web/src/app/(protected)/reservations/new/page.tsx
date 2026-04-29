import { Suspense } from "react";
import { LoadingBlock } from "@/components/states/state-blocks";
import { ReservationCreatePage } from "@/features/reservations/reservation-create-page";

export default function Page() {
  return (
    <Suspense fallback={<main className="mx-auto w-full max-w-3xl px-4 py-6"><LoadingBlock label="Đang tải mẫu đặt chỗ" /></main>}>
      <ReservationCreatePage />
    </Suspense>
  );
}
