"use client";

import Image from "next/image";
import { useState } from "react";
import type { MenuItem } from "./api";

type MenuImageProfile = {
  keywords: string[];
  src: string;
  label: string;
};

const menuImageProfiles: MenuImageProfile[] = [
  {
    keywords: ["phở", "pho", "bún", "mì", "noodle", "soup"],
    src: "/customer-web/fallback-noodle.jpg",
    label: "Món nước",
  },
  {
    keywords: ["cơm", "rice", "curry", "gà", "chicken"],
    src: "/customer-web/fallback-rice.jpg",
    label: "Món chính",
  },
  {
    keywords: ["salad", "rau", "chay", "vegetable"],
    src: "/customer-web/fallback-salad.jpg",
    label: "Món rau",
  },
  {
    keywords: ["tráng miệng", "dessert", "cake", "bánh", "kem"],
    src: "/customer-web/fallback-dessert.jpg",
    label: "Tráng miệng",
  },
  {
    keywords: ["drink", "nước", "tea", "coffee", "cà phê", "trà"],
    src: "/customer-web/fallback-drink.jpg",
    label: "Đồ uống",
  },
];

const defaultMenuImage: MenuImageProfile = {
  keywords: [],
  src: "/customer-web/fallback-default.jpg",
  label: "Món ăn",
};

function imageProfileFor(item: MenuItem): MenuImageProfile {
  const searchable = [item.name, item.category_name, item.description].filter(Boolean).join(" ").toLocaleLowerCase("vi-VN");

  return menuImageProfiles.find((profile) => profile.keywords.some((keyword) => searchable.includes(keyword))) ?? defaultMenuImage;
}

function canUseNextImage(src: string): boolean {
  return src.startsWith("/");
}

export function MenuItemImage({
  item,
  className = "h-44",
  priority = false,
}: {
  item: MenuItem;
  className?: string;
  priority?: boolean;
}) {
  const [failedImageUrl, setFailedImageUrl] = useState<string | null>(null);
  const fallbackProfile = imageProfileFor(item);
  const rawImageUrl = item.img_url ?? fallbackProfile.src;
  const imageUrl = failedImageUrl === rawImageUrl ? defaultMenuImage.src : rawImageUrl;
  const isFallback = !item.img_url || failedImageUrl === rawImageUrl;
  const altText = item.img_url && !isFallback ? item.name : `Ảnh minh họa ${fallbackProfile.label.toLocaleLowerCase("vi-VN")}`;

  return (
    <div className={`relative overflow-hidden bg-secondary ${className}`}>
      {canUseNextImage(imageUrl) ? (
        <Image
          src={imageUrl}
          alt={altText}
          fill
          priority={priority}
          sizes="(min-width: 1280px) 25vw, (min-width: 640px) 50vw, 100vw"
          className="object-cover transition duration-300 group-hover:scale-[1.03]"
          onError={() => setFailedImageUrl(rawImageUrl)}
        />
      ) : (
        // Remote menu images are left unoptimized unless an explicit host allow-list is added.
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={imageUrl}
          alt={altText}
          className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
          loading={priority ? "eager" : "lazy"}
          onError={() => setFailedImageUrl(rawImageUrl)}
        />
      )}
      {isFallback ? (
        <span className="absolute left-3 top-3 rounded-md bg-background/90 px-2 py-1 text-xs font-medium text-foreground shadow-sm">
          Ảnh minh họa
        </span>
      ) : null}
    </div>
  );
}
