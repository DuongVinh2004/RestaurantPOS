import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('./providers/AppProviders', () => ({
  AppProviders: ({ children }: { children: ReactNode }) => <div data-testid="app-providers">{children}</div>,
}));

vi.mock('./router/index.tsx', () => ({
  AppRouter: () => <div data-testid="canonical-router">canonical router</div>,
}));

import App from './App';

describe('App', () => {
  it('boots the active app through the canonical router module', () => {
    render(<App />);

    expect(screen.getByTestId('app-providers')).toBeInTheDocument();
    expect(screen.getByTestId('canonical-router')).toBeInTheDocument();
  });
});
