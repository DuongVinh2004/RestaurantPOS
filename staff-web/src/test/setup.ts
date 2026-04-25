import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach } from 'vitest';
import { writeStoredStaffToken } from '../shared/auth/storage';

afterEach(() => {
  cleanup();
});

beforeEach(() => {
  localStorage.clear();
  writeStoredStaffToken(null);
});
