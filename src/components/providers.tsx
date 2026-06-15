"use client";

import * as React from "react";
import { Toaster } from "@/components/ui/sonner";
import { apiGet } from "@/lib/client";

interface CartState {
  count: number;
  refresh: () => Promise<void>;
  setCount: (n: number) => void;
}

const CartContext = React.createContext<CartState>({ count: 0, refresh: async () => {}, setCount: () => {} });
export const useCart = () => React.useContext(CartContext);

export function Providers({ children, initialCartCount = 0 }: { children: React.ReactNode; initialCartCount?: number }) {
  const [count, setCount] = React.useState(initialCartCount);

  const refresh = React.useCallback(async () => {
    try {
      const data = await apiGet<{ summary: { itemCount: number } }>("/api/v1/cart");
      setCount(data.summary.itemCount);
    } catch {
      /* ignore */
    }
  }, []);

  return (
    <CartContext.Provider value={{ count, refresh, setCount }}>
      {children}
      <Toaster />
    </CartContext.Provider>
  );
}
