import type { MetadataRoute } from "next";
import { customerBrand } from "@/lib/brand/customer-brand";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: customerBrand.appName,
    short_name: customerBrand.shortName,
    description: customerBrand.metaDescription,
    start_url: "/",
    scope: "/",
    display: "standalone",
    background_color: "#fffdf8",
    theme_color: "#2f7d5c",
    categories: ["food", "business"],
    icons: [
      {
        src: "/favicon.ico",
        sizes: "any",
        type: "image/x-icon",
      },
    ],
  };
}
