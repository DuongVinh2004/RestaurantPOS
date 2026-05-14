import type { Metadata } from "next";
import { HomePage } from "@/features/home/home-page";
import { customerBrand } from "@/lib/brand/customer-brand";

export const metadata: Metadata = {
  title: customerBrand.appName,
  description: customerBrand.metaDescription,
};

export default function Home() {
  return <HomePage />;
}
