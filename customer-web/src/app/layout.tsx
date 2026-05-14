import type { Metadata, Viewport } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import { Toaster } from "@/components/ui/sonner";
import { AppShell } from "@/components/layout/app-shell";
import { AppQueryProvider } from "@/providers/query-provider";
import { AuthProvider } from "@/providers/auth-provider";
import { customerBrand } from "@/lib/brand/customer-brand";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin", "latin-ext"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin", "latin-ext"],
});

export const metadata: Metadata = {
  title: {
    default: customerBrand.metaTitle,
    template: `%s | ${customerBrand.shortName}`,
  },
  description: customerBrand.metaDescription,
  applicationName: customerBrand.appName,
  manifest: "/manifest.webmanifest",
  formatDetection: {
    telephone: false,
    email: false,
    address: false,
  },
  appleWebApp: {
    capable: true,
    title: customerBrand.shortName,
  },
};

export const viewport: Viewport = {
  themeColor: "#2f7d5c",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="vi"
      data-scroll-behavior="smooth"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col bg-background text-foreground">
        <AppQueryProvider>
          <AuthProvider>
            <AppShell>{children}</AppShell>
            <Toaster richColors position="top-center" />
          </AuthProvider>
        </AppQueryProvider>
      </body>
    </html>
  );
}
