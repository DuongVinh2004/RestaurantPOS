import { copyFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const appRoot = resolve(__dirname, "..");
const repoRoot = resolve(appRoot, "..");
const targetDir = resolve(appRoot, "src/lib/contracts/generated");

mkdirSync(targetDir, { recursive: true });

copyFileSync(
  resolve(repoRoot, "build/api-consumer/sdk/typescript/restaurantpos-sdk.ts"),
  resolve(targetDir, "restaurantpos-sdk.ts"),
);
copyFileSync(
  resolve(repoRoot, "build/api-consumer/sdk/typescript/restaurantpos-enums.ts"),
  resolve(targetDir, "restaurantpos-enums.ts"),
);

console.log("Synced RestaurantPOS customer-web contract artifacts.");
