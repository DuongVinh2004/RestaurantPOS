import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "RestaurantPOS Khách hàng",
    short_name: "RestaurantPOS",
    description: "Đặt bàn, xem thực đơn, theo dõi ưu đãi và trạng thái thanh toán cho khách hàng RestaurantPOS.",
    start_url: "/",
    scope: "/",
    display: "standalone",
    background_color: "#fffaf7",
    theme_color: "#f97316",
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
