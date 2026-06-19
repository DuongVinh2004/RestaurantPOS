import { createContext, useContext, useState, type ReactNode } from 'react';

export type PresenceUser = {
  id: number;
  name: string;
  avatar_url?: string;
  color: string;
};

export type PresenceContextType = {
  subscribe: (channel: string) => void;
  unsubscribe: (channel: string) => void;
  getMembers: (channel: string) => PresenceUser[];
};

export const PresenceContext = createContext<PresenceContextType | null>(null);

export function usePresenceContext() {
  const ctx = useContext(PresenceContext);
  if (!ctx) {
    throw new Error('usePresenceContext must be used within a PresenceProvider');
  }
  return ctx;
}

export function PresenceProvider({ children }: { children: ReactNode }) {
  const [channels, setChannels] = useState<Record<string, PresenceUser[]>>({});

  function subscribe(channel: string) {
    setChannels((prev) => {
      if (prev[channel]) return prev;
      
      const mockMembers: PresenceUser[] = [
        { id: 991, name: 'Quản lý A', color: '#f56a00' },
        { id: 992, name: 'Bếp B', color: '#7265e6' }
      ];

      return { ...prev, [channel]: Math.random() > 0.5 ? mockMembers : [] };
    });
  }

  function unsubscribe(channel: string) {
    // Keep it simple for now
  }

  function getMembers(channel: string) {
    return channels[channel] || [];
  }

  return (
    <PresenceContext.Provider value={{ subscribe, unsubscribe, getMembers }}>
      {children}
    </PresenceContext.Provider>
  );
}
