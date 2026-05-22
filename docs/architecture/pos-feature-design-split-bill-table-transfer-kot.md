# First Implementation Candidate

## Candidates
1. **Split Bill**: Complex state. Impacts Ordering and Checkout.
2. **Table Transfer**: State shift. Impacts Floor Operations and Ordering.
3. **KOT/Receipt Printing**: Abstraction layer. Low risk.

## Recommendation: Table Transfer
**Why**: It is a high-value POS feature that touches core concurrency logic (moving an active order from one table to another) without the extreme accounting complexities of Split Bill. It proves the architecture's locking mechanism.

### Safe Implementation Plan
- **Batch A**: DB/API contract (Add 'transfer' endpoint, ensure schema tracks transfer history).
- **Batch B**: Backend domain/application logic (FloorOperations & Ordering integration, redis locks on both tables).
- **Batch C**: Frontend UX (Table selection modal in staff-web).
- **Batch D**: Tests/evidence/docs (Concurrency tests).
