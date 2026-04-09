type CapabilityCarrier =
  | Array<string>
  | Set<string>
  | {
      capabilities?: Array<string>;
      known_capabilities?: Array<string>;
    }
  | null
  | undefined;

export function hasCapability(source: CapabilityCarrier, capability: string): boolean {
  return capabilitySet(source).has('*') || capabilitySet(source).has(capability);
}

export function hasAnyCapability(source: CapabilityCarrier, capabilities: Array<string>): boolean {
  const set = capabilitySet(source);

  if (set.has('*')) {
    return true;
  }

  return capabilities.some((capability) => set.has(capability));
}

export function capabilitySet(source: CapabilityCarrier): Set<string> {
  if (!source) {
    return new Set<string>();
  }

  if (source instanceof Set) {
    return source;
  }

  if (Array.isArray(source)) {
    return new Set(source);
  }

  return new Set(source.capabilities ?? []);
}

export function knownCapabilitySet(source: CapabilityCarrier): Set<string> {
  if (!source || source instanceof Set || Array.isArray(source)) {
    return new Set<string>();
  }

  return new Set(source.known_capabilities ?? []);
}
