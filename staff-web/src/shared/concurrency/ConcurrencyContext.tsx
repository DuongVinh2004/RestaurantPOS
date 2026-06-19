import { createContext, useContext, useState, type ReactNode } from 'react';
import { ConflictResolutionModal } from './ConflictResolutionModal';

type ConcurrencyContextType = {
  reportConflict: () => void;
};

const ConcurrencyContext = createContext<ConcurrencyContextType | null>(null);

export function useConcurrencyContext(): ConcurrencyContextType {
  const ctx = useContext(ConcurrencyContext);
  if (!ctx) {
    throw new Error('useConcurrencyContext must be used within a ConcurrencyProvider');
  }
  return ctx;
}

export function ConcurrencyProvider({ children }: { children: ReactNode }) {
  const [hasConflict, setHasConflict] = useState(false);

  function reportConflict() {
    setHasConflict(true);
  }

  function handleReload() {
    window.location.reload();
  }

  return (
    <ConcurrencyContext.Provider value={{ reportConflict }}>
      {children}
      <ConflictResolutionModal 
        open={hasConflict} 
        onReload={handleReload} 
        onClose={() => setHasConflict(false)} 
      />
    </ConcurrencyContext.Provider>
  );
}
