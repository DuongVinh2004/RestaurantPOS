import type { BranchCollectionEnvelope } from './sdk';
import { staffClient } from './client';

export async function listBranches(): Promise<BranchCollectionEnvelope> {
  return staffClient.getV1StaffBranches();
}
