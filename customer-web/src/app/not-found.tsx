import Link from "next/link";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/states/state-blocks";

export default function NotFound() {
  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-10">
      <EmptyState
        title="Không tìm thấy trang"
        description="Trang có thể đã đổi địa chỉ hoặc liên kết đã cũ. Bạn có thể tiếp tục từ thực đơn hoặc trang đặt bàn."
        action={
          <div className="flex flex-col gap-2 sm:flex-row sm:justify-center">
            <Button asChild className="rounded-lg">
              <Link href="/">Xem thực đơn</Link>
            </Button>
            <Button asChild variant="outline" className="rounded-lg">
              <Link href="/booking">Tìm bàn</Link>
            </Button>
          </div>
        }
      />
    </main>
  );
}
