import type { StaffSession } from '../../core/auth/storage';
import { recommendedPathForSession } from '../store/auth-store';

export function resolveIndexRedirectPath(session: StaffSession | null): string {
  return recommendedPathForSession(session);
}

export function resolveFallbackRedirectPath(session: StaffSession | null): string {
  return session ? recommendedPathForSession(session) : '/login';
}
