# Refactored app Structure Guide

## Top-level rule

- New business code goes to `app/Modules/<Domain>/`.
- New cross-cutting release, artifact, metrics, verification, backup, or operator code goes to `app/Platform/`.
- Generic `app/Services/` and `app/Support/` are compatibility or intentionally shared seams, not the default home for new business logic.

## Module layout

Use this structure unless the module already has a stricter local convention:

```text
app/Modules/<Domain>/
  Domain/
    Models/
    State/
    Policies/
    Guards/
    ValueObjects/
    Contracts/
    Domains/
  Application/
    Services/
    Actions/
    Queries/
  Infrastructure/
    Contracts/
    Drivers/
    Parsers/
    Support/
  Http/
    Controllers/
    Requests/
    Resources/
```

## Placement rules

- Controllers stay thin. Request parsing, permission branching, and response shaping can stay in HTTP; business decisions go to `Application/Services`.
- Eloquent models, state machines, policies, guards, and value objects belong in `Domain/`.
- Driver adapters, parsers, and framework-facing integrations belong in `Infrastructure/`.
- If a class orchestrates two true domains but does not own either one, prefer placing it in the owning module that exposes the workflow entrypoint. Do not create a dumping-ground helper.
- If a concern is release or operator facing, prefer `app/Platform/` over `app/Services/`.

## What not to do

- Do not add new domain logic to `app/Services/Customer*`, `app/Services/Staff/*`, or `app/Support/*` when a module already owns that workflow.
- Do not put import/export orchestration into the source domain model layer; keep that in `AdminMasterDataBulk`.
- Do not put notification sending mechanics into source business modules; emit through `Notifications`.
