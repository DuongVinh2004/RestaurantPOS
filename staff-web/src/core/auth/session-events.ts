export type StaffAuthFailure = {
  status: number;
  path: string;
};

let authFailureHandler: ((failure: StaffAuthFailure) => void) | null = null;

export function registerStaffAuthFailureHandler(
  handler: ((failure: StaffAuthFailure) => void) | null,
): void {
  authFailureHandler = handler;
}

export function notifyStaffAuthFailure(failure: StaffAuthFailure): void {
  authFailureHandler?.(failure);
}
