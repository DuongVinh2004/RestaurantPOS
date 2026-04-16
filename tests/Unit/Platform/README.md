# Platform Unit Tests

Place new platform-owned unit tests here when the code under test lives in
`app/Platform`.

Use this for pure services, guards, inspectors, artifact helpers, and release or
verification support that can be tested without an HTTP or console feature test.
Runtime-facing command and route checks should stay under `tests/Feature`.
