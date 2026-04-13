import type { StaffSession } from '../auth/storage';

type CapabilitySource = Pick<StaffSession, 'capabilities'> | Array<string> | Set<string> | null | undefined;

export function can(source: CapabilitySource, capability: string): boolean {
  const capabilities = capabilitySet(source);
  return capabilities.has('*') || capabilities.has(capability);
}

export function hasAny(source: CapabilitySource, capabilities: Array<string>): boolean {
  const granted = capabilitySet(source);

  if (granted.has('*')) {
    return true;
  }

  return capabilities.some((capability) => granted.has(capability));
}

export function hasAll(source: CapabilitySource, capabilities: Array<string>): boolean {
  const granted = capabilitySet(source);

  if (granted.has('*')) {
    return true;
  }

  return capabilities.every((capability) => granted.has(capability));
}

export function capabilitySet(source: CapabilitySource): Set<string> {
  if (!source) {
    return new Set<string>();
  }

  if (source instanceof Set) {
    return source;
  }

  if (Array.isArray(source)) {
    return new Set(source);
  }

  return new Set(source.capabilities);
}
