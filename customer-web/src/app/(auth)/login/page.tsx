import { Suspense } from "react";
import { LoadingBlock } from "@/components/states/state-blocks";
import { LoginPage } from "@/features/auth/login-page";

export default function Page() {
  return (
    <Suspense fallback={<main className="mx-auto w-full max-w-md px-4 py-8"><LoadingBlock label="Loading login" /></main>}>
      <LoginPage />
    </Suspense>
  );
}
