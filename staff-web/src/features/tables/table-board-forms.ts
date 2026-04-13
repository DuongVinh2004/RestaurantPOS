export type WalkInFormValues = {
  guest_name: string;
  phone?: string;
  guest_count: number;
  service_minutes?: number;
  notes?: string;
};

export type MoveTableFormValues = {
  to_table_id: number;
};

export const DEFAULT_WALK_IN_FORM_VALUES: Partial<WalkInFormValues> = {
  guest_count: 2,
  service_minutes: 120,
};
