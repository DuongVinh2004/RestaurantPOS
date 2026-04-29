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
    src: "https://images.unsplash.com/photo-1582878826629-29b7ad1cdc43?auto=format&fit=crop&w=900&q=80",
    label: "Món nước",
  },
  {
    keywords: ["cơm", "rice", "curry", "gà", "chicken"],
    src: "https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=900&q=80",
    label: "Món chính",
  },
  {
    keywords: ["salad", "rau", "chay", "vegetable"],
    src: "https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=80",
    label: "Món rau",
  },
  {
    keywords: ["tráng miệng", "dessert", "cake", "bánh", "kem"],
    src: "https://images.unsplash.com/photo-1551024506-0bccd828d307?auto=format&fit=crop&w=900&q=80",
    label: "Tráng miệng",
  },
  {
    keywords: ["drink", "nước", "tea", "coffee", "cà phê", "trà"],
    src: "https://images.unsplash.com/photo-1544145945-f90425340c7e?auto=format&fit=crop&w=900&q=80",
    label: "Đồ uống",
  },
];

const defaultMenuImage: MenuImageProfile = {
  keywords: [],
  src: "https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=80",
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
