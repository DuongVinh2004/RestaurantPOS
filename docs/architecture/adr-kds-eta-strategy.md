# ADR: KDS Smart ETA Strategy

## Context
The Kitchen Display System (KDS) needs to help kitchen staff understand workload and prioritize tickets. A Smart Estimated Time of Arrival (ETA) is requested, but jumping straight to complex ML models without solid historical data is risky and prone to misleading the kitchen.

## Decision
1. **Iterative Rollout**:
   - **Phase 1**: Heuristic ETA (static mapping based on item type/category).
   - **Phase 2**: Historical average ETA (dynamic calculation based on recent similar tickets).
   - **Phase 3**: Station workload adjustment (factoring in current queue depth).
   - **Phase 4**: Predictive model (only when sufficient high-quality data is proven).
2. **Presentation**: ETAs will be displayed with a Confidence Indicator and a brief reason (e.g., "High confidence - Station idle", or "Low confidence - Historical average").
3. **No False Guarantees**: ETAs will explicitly be presented as estimates, never as absolute service-level agreements (SLAs) unless tied to a specific policy.
4. **Grouped Prep Views**: The KDS will provide grouped summaries (e.g., "8x Spring Rolls total") while maintaining strict linkage to individual tickets and tables to prevent misrouting.

## Consequences
- Keeps the KDS reliable and trustworthy from day one.
- Requires building data collection pipelines for ticket preparation times before advanced ETAs can be implemented.
