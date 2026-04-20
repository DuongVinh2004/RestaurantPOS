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
          <p className="text-sm font-medium text-primary">Page error</p>
          <h1 className="text-2xl font-semibold tracking-normal">We could not open this page.</h1>
          <p className="max-w-md text-sm text-muted-foreground">
            Try again, or return to the menu and continue from there.
          </p>
        </div>
        <ErrorState error={error} title="Something went wrong" onRetry={reset} />
        <div className="flex flex-col gap-2 sm:flex-row">
          <Button asChild className="rounded-lg">
            <Link href="/">Browse menu</Link>
          </Button>
          <Button asChild variant="outline" className="rounded-lg">
            <Link href="/booking">Find a table</Link>
          </Button>
        </div>
      </section>
    </main>
  );
}
