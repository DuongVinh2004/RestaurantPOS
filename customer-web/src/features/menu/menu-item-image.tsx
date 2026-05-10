"use client";

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

export function MenuItemImage({
  item,
  className = "h-44",
}: {
  item: MenuItem;
  className?: string;
}) {
  const fallbackProfile = imageProfileFor(item);
  const imageUrl = item.img_url ?? fallbackProfile.src;
  const isFallback = !item.img_url;

  return (
    <div className={`relative overflow-hidden bg-secondary ${className}`}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={imageUrl}
        alt={item.img_url ? item.name : `Ảnh minh họa ${fallbackProfile.label.toLocaleLowerCase("vi-VN")}`}
        className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
        loading="lazy"
        onError={(event) => {
          const image = event.currentTarget;

          if (image.dataset.fallbackApplied === "true") {
            return;
          }

          image.dataset.fallbackApplied = "true";
          image.src = defaultMenuImage.src;
        }}
      />
      {isFallback ? (
        <span className="absolute left-3 top-3 rounded-md bg-background/90 px-2 py-1 text-xs font-medium text-foreground shadow-sm">
          Ảnh minh họa
        </span>
      ) : null}
    </div>
  );
}
