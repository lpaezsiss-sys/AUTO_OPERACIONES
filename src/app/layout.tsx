import type { Metadata } from "next";
import { Fraunces, Outfit } from "next/font/google";
import { AppNav } from "@/components/AppNav";
import "./globals.css";

const outfit = Outfit({
  variable: "--font-outfit",
  subsets: ["latin"],
});

const fraunces = Fraunces({
  variable: "--font-fraunces",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "Inventario — Control de Stock",
  description:
    "Gestión de inventario con costo unitario promedio (CUP/PMP), entradas y salidas.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="es" className={`${outfit.variable} ${fraunces.variable} h-full`}>
      <body className="min-h-full antialiased">
        <AppNav />
        <main className="mx-auto w-full max-w-6xl px-4 pb-16 pt-8 sm:px-6">
          {children}
        </main>
      </body>
    </html>
  );
}
