# Hot API Benchmark Plan
Target Flows:
- staff floor map (Load < 200ms)
- reservation listing/filter (Load < 300ms)
- create reservation (Mutation < 200ms)
- create order (Mutation < 200ms)
- add order items (Mutation < 100ms)
- dispatch to kitchen (Mutation < 150ms)
- checkout (External API dependent)
- refund (External API dependent)
- inventory stock lookup (Load < 100ms)
- sales report (Load < 1s)
