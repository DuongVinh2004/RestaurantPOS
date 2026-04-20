import type { Metadata } from "next";
import { MenuPage } from "@/features/menu/menu-page";

export const metadata: Metadata = {
  title: "Menu",
  description: "Browse the live menu, search dishes, and preview preorder-eligible items before your visit.",
};

export default function Home() {
  return <MenuPage />;
}
