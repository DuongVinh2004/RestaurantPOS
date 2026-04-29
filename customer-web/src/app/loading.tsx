import { LoadingBlock } from "@/components/states/state-blocks";

export default function Loading() {
  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-8">
      <section className="space-y-2">
        <p className="text-sm font-medium text-primary">Đang tải</p>
        <h1 className="text-2xl font-semibold tracking-normal">Đang chuẩn bị nội dung.</h1>
        <p className="max-w-md text-sm text-muted-foreground">
          Thực đơn, đặt bàn và lịch đặt sẽ được cập nhật từ nhà hàng.
        </p>
      </section>
      <section className="mt-6">
        <LoadingBlock label="Đang tải trang" />
      </section>
    </main>
  );
}
