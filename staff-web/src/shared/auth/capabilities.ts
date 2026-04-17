import type { StaffSession } from './storage';

type CapabilitySource = Pick<StaffSession, 'capabilities'> | Array<string> | Set<string> | null | undefined;
type CapabilityCarrier =
  | Array<string>
  | Set<string>
  | {
      capabilities?: Array<string>;
      known_capabilities?: Array<string>;
    }
  | null
  | undefined;

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

export function hasCapability(source: CapabilityCarrier, capability: string): boolean {
  return can(toGrantedCapabilitySource(source), capability);
}

export function hasAnyCapability(source: CapabilityCarrier, capabilities: Array<string>): boolean {
  return hasAny(toGrantedCapabilitySource(source), capabilities);
}

export function knownCapabilitySet(source: CapabilityCarrier): Set<string> {
  if (!source || source instanceof Set || Array.isArray(source)) {
    return new Set<string>();
  }

  return new Set(source.known_capabilities ?? []);
}

function toGrantedCapabilitySource(
  source: CapabilityCarrier,
): Array<string> | Set<string> | { capabilities: Array<string> } | null | undefined {
  if (!source || source instanceof Set || Array.isArray(source)) {
    return source;
  }

  return {
    capabilities: source.capabilities ?? [],
  };
}
