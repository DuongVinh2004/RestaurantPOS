import type { BranchCollectionEnvelope } from './sdk';
import { apiRequest } from './http';

export async function listBranches(): Promise<BranchCollectionEnvelope> {
  return apiRequest<BranchCollectionEnvelope>('/staff/branches');
}
