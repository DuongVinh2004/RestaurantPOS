export type DataEnvelope<T> = {
  data: T;
};

export function unwrapData<TEnvelope extends DataEnvelope<unknown>>(envelope: TEnvelope): TEnvelope["data"] {
  return envelope.data;
}

export function unwrapCollection<TEnvelope extends DataEnvelope<unknown[]> & { meta?: unknown }>(
  envelope: TEnvelope,
): { items: TEnvelope["data"]; meta: TEnvelope["meta"] | null } {
  return {
    items: envelope.data,
    meta: envelope.meta ?? null,
  };
}
