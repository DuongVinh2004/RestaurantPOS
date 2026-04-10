import { render, type RenderOptions } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { ReactElement } from 'react';
import { StaffSessionContext, type StaffSessionContextValue } from '../app/session-context';

export function renderWithSession(
  ui: ReactElement,
  sessionContext: StaffSessionContextValue,
  options?: Omit<RenderOptions, 'wrapper'> & {
    initialEntries?: Array<string>;
  },
) {
  const { initialEntries, ...renderOptions } = options ?? {};

  return render(
    <MemoryRouter initialEntries={initialEntries} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <StaffSessionContext.Provider value={sessionContext}>{ui}</StaffSessionContext.Provider>
    </MemoryRouter>,
    renderOptions,
  );
}
