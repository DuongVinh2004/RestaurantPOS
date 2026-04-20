import { LoadingBlock } from "@/components/states/state-blocks";

export default function Loading() {
  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-8">
      <section className="space-y-2">
        <p className="text-sm font-medium text-primary">Loading</p>
        <h1 className="text-2xl font-semibold tracking-normal">Getting things ready.</h1>
        <p className="max-w-md text-sm text-muted-foreground">
          Menu, booking, and reservation details update live from the restaurant.
        </p>
      </section>
      <section className="mt-6">
        <LoadingBlock label="Loading page" />
      </section>
    </main>
  );
}
