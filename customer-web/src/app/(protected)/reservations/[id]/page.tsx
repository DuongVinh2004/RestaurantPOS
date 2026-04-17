import { notFound } from "next/navigation";
import { ReservationDetailPage } from "@/features/reservations/reservation-detail-page";

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const numericId = Number(id);

  if (!Number.isInteger(numericId) || numericId <= 0) {
    notFound();
  }

  return <ReservationDetailPage id={numericId} />;
}
