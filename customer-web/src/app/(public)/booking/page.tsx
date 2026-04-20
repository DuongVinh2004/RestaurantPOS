import type { Metadata } from "next";
import { TableBookingPage } from "@/features/table-booking/table-booking-page";

export const metadata: Metadata = {
  title: "Find a table",
  description: "Check table availability, hold a time slot, and continue to a reservation when you are ready.",
};

export default function Page() {
  return <TableBookingPage />;
}
