import Link from "next/link";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/states/state-blocks";

export default function NotFound() {
  return (
    <main className="mx-auto w-full max-w-3xl px-4 py-10">
      <EmptyState
        title="Page not found"
        description="The page may have moved, or the link may be out of date. You can keep going from the menu or table booking."
        action={
          <div className="flex flex-col gap-2 sm:flex-row sm:justify-center">
            <Button asChild className="rounded-lg">
              <Link href="/">Browse menu</Link>
            </Button>
            <Button asChild variant="outline" className="rounded-lg">
              <Link href="/booking">Find a table</Link>
            </Button>
          </div>
        }
      />
    </main>
  );
}
