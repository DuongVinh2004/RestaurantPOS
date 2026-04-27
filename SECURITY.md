# Security Policy

## Supported Branch

Security fixes are handled against the current default branch: `main`.

Older snapshots, personal branches, and historical release evidence are not treated as supported maintenance lines unless explicitly stated.

## Reporting A Vulnerability

Do not open a public GitHub issue for a vulnerability that could be exploited.

Use one of these private channels instead:

1. GitHub Security Advisories for this repository:
   - `https://github.com/DuongVinh2004/RestaurantPOS/security/advisories/new`
2. If advisory creation is unavailable to you, contact the repository owner privately through the GitHub profile associated with this repository.

## What To Include

Please include:

- affected area and impact
- reproduction steps
- required configuration or environment assumptions
- proof-of-concept request or payload, if applicable
- proposed mitigation if you already have one

Good reports are concrete and reproducible. If the issue depends on SQL-first bootstrap, branch scope, auth headers, or feature flags, say so explicitly.

## Response Expectations

The project will try to:

- confirm receipt
- reproduce the issue
- assess impact and affected scope
- patch on `main`
- disclose publicly only after a fix or mitigation is ready

## Non-Security Issues

For bugs, regressions, docs gaps, or feature requests, use the standard issue templates instead of this policy.
