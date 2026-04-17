import { notFound } from "next/navigation";
import { MenuDetailPage } from "@/features/menu/menu-detail-page";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const numericId = Number(id);

  if (!Number.isInteger(numericId) || numericId <= 0) {
    notFound();
  }

  return <MenuDetailPage id={numericId} />;
}
