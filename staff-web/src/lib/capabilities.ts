import { can, capabilitySet as grantedCapabilitySet, hasAny } from '../core/permissions/capabilities';

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
  return can(toGrantedCapabilitySource(source), capability);
}

export function hasAnyCapability(source: CapabilityCarrier, capabilities: Array<string>): boolean {
  return hasAny(toGrantedCapabilitySource(source), capabilities);
}

export function capabilitySet(source: CapabilityCarrier): Set<string> {
  return grantedCapabilitySet(toGrantedCapabilitySource(source));
}

export function knownCapabilitySet(source: CapabilityCarrier): Set<string> {
  if (!source || source instanceof Set || Array.isArray(source)) {
    return new Set<string>();
  }

  return new Set(source.known_capabilities ?? []);
}

function toGrantedCapabilitySource(source: CapabilityCarrier): Array<string> | Set<string> | { capabilities: Array<string> } | null | undefined {
  if (!source || source instanceof Set || Array.isArray(source)) {
    return source;
  }

  return {
    capabilities: source.capabilities ?? [],
  };
}
