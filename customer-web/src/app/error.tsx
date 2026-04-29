"use client";

import Link from "next/link";
import { Button } from "@/components/ui/button";
import { ErrorState } from "@/components/states/state-blocks";

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-10">
      <section className="space-y-4">
        <div className="space-y-2">
          <p className="text-sm font-medium text-primary">Lỗi trang</p>
          <h1 className="text-2xl font-semibold tracking-normal">Chưa mở được trang này.</h1>
          <p className="max-w-md text-sm text-muted-foreground">
            Hãy thử lại, hoặc quay về thực đơn để tiếp tục.
          </p>
        </div>
        <ErrorState error={error} title="Đã có lỗi xảy ra" onRetry={reset} />
        <div className="flex flex-col gap-2 sm:flex-row">
          <Button asChild className="rounded-lg">
            <Link href="/">Xem thực đơn</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-lg">
            <Link href="/booking">Tìm bàn</Link>
          </Button>
        </div>
      </section>
    </main>
  );
}
