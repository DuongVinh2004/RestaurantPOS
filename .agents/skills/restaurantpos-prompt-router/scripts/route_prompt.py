#!/usr/bin/env python3
"""Route free-form RestaurantPOS requests to the smallest useful skill set."""

from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path


DOMAIN_RULES = [
    {
        "skill": "restaurantpos-web-client-contracts",
        "keywords": ["frontend contract", "generated sdk", "typescript sdk", "api consumer", "mutation contract", "error shape", "error envelope", "enum", "state exposure", "postman", "dx"],
        "paths": ["docs/runbooks/api-consumer-artifacts.md", "docs/runbooks/booking-api-contract.md", "config/api_artifacts.php", "app/Support/ApiErrorResponse.php", "config/cors.php"],
        "representative_paths": ["config/api_artifacts.php", "app/Support/ApiErrorResponse.php", "config/cors.php"],
        "note": "Use web-client-contracts when the work is driven by what Next.js or Vite consumers can safely depend on, not only by internal backend structure.",
    },
    {
        "skill": "restaurantpos-web-auth-session-contract",
        "keywords": ["header-based auth", "x-customer-token", "x-staff-key", "x-session-id", "access session", "session propagation", "customer login", "staff login", "cors auth", "refresh token", "logout"],
        "paths": ["config/customer_auth.php", "config/staff_auth.php", "config/cors.php", "app/Http/Middleware/CustomerOrStaffMiddleware.php", "app/Http/Controllers/Api/Auth/CustomerAuthController.php", "app/Http/Controllers/Api/Auth/StaffAuthController.php"],
        "representative_paths": ["config/customer_auth.php", "config/staff_auth.php", "app/Http/Middleware/CustomerOrStaffMiddleware.php", "config/cors.php"],
        "note": "Use web-auth-session-contract when split-web auth headers, session propagation, or login lifecycle are the integration risk.",
    },
    {
        "skill": "restaurantpos-staff-web-react",
        "keywords": ["staff-web", "staff web", "staff ui", "operator ui", "antd", "ant design", "react query", "vite", "pos screen", "kds screen", "cashier screen", "admin screen", "staff page", "staff route"],
        "paths": ["staff-web/package.json", "staff-web/src/app", "staff-web/src/domains", "staff-web/src/shared", "staff-web/src/workspaces"],
        "representative_paths": ["staff-web/package.json"],
        "note": "Use staff-web-react when the change is an operator-facing React screen or staff-web state/routing concern.",
    },
    {
        "skill": "restaurantpos-customer-web-ui-flow",
        "keywords": ["customer-web", "customer web", "customer ui", "next.js", "nextjs", "booking page", "reservation page", "reservation form", "waiting list", "menu page", "payment ui", "deposit ui", "customer form", "shadcn", "radix"],
        "paths": ["customer-web/package.json", "customer-web/src/features", "customer-web/src/components", "customer-web/src/lib", "customer-web/src/app"],
        "representative_paths": ["customer-web/package.json"],
        "note": "Use customer-web-ui-flow when the change is a customer-facing page, form, or async UI flow.",
    },
    {
        "skill": "restaurantpos-auth-rbac",
        "keywords": ["auth", "rbac", "capability", "permission", "forbidden", "staff key", "customer token", "guard", "middleware"],
        "paths": ["config/staff_capabilities.php", "app/Http/Middleware/RequireStaffCapability.php", "app/Services/Auth/OpaqueProductAuthService.php"],
        "representative_paths": ["config/staff_capabilities.php", "app/Http/Middleware/RequireStaffCapability.php"],
        "note": "Choose auth-rbac when the failure is about who may act, even if a feature endpoint is the surface.",
    },
    {
        "skill": "restaurantpos-foh-reservations",
        "keywords": ["reservation board", "check-in", "check in", "move table", "release table", "table hold", "availability"],
        "paths": ["app/Services/ReservationService.php", "app/Services/TableAvailabilityService.php", "app/Services/Staff/StaffCheckInService.php"],
        "representative_paths": ["app/Services/TableAvailabilityService.php", "app/Services/Staff/StaffCheckInService.php"],
        "note": "Use FOH reservations when table state and booking-window safety drive the fix.",
    },
    {
        "skill": "restaurantpos-order-lifecycle",
        "keywords": ["order item", "table order", "service session", "active order", "order read", "order lifecycle"],
        "paths": ["app/Services/Staff/StaffTableOrderService.php", "app/Services/Staff/StaffOrderItemLifecycleService.php", "app/Services/Staff/StaffOrderReadService.php"],
        "representative_paths": ["app/Services/Staff/StaffTableOrderService.php"],
        "note": "Use order lifecycle when the invariant is order mutation or item state, not checkout or kitchen output.",
    },
    {
        "skill": "restaurantpos-customer-self-service",
        "keywords": ["customer self-service", "self service", "customer session", "owner access", "self-pay", "self pay"],
        "paths": ["app/Services/CustomerAccessSessionService.php", "app/Services/CustomerReservationSessionAccessService.php"],
        "representative_paths": ["app/Services/CustomerAccessSessionService.php"],
        "note": "Use customer self-service when customer token or owner-contract checks are the main invariant.",
    },
    {
        "skill": "restaurantpos-checkout-finance",
        "keywords": ["checkout", "refund", "cashier shift", "invoice", "reconciliation", "payment webhook", "settlement"],
        "paths": ["app/Services/Staff/StaffCheckoutService.php", "app/Services/Staff/StaffCashierShiftService.php", "app/Services/Staff/StaffFinancialReconciliationService.php"],
        "representative_paths": ["app/Services/Staff/StaffCheckoutService.php"],
        "note": "Use checkout-finance when money movement, invoice state, or refund lineage is the risk.",
    },
    {
        "skill": "restaurantpos-kitchen-kds",
        "keywords": ["kitchen", "kds", "dispatch", "ticket", "fire", "bump", "recall"],
        "paths": ["app/Services/Kitchen", "app/Http/Controllers/Api/Staff/StaffKitchenController.php"],
        "representative_paths": ["app/Http/Controllers/Api/Staff/StaffKitchenController.php"],
        "note": "Use kitchen-kds when routing and ticket state safety matter more than upstream ordering.",
    },
    {
        "skill": "restaurantpos-inventory-purchasing",
        "keywords": ["inventory", "stock", "purchase order", "supplier", "receiving", "recipe", "ingredient"],
        "paths": ["app/Services/Inventory", "app/Services/Admin/AdminInventoryService.php", "app/Services/Admin/AdminPurchasingService.php"],
        "representative_paths": ["app/Services/Admin/AdminInventoryService.php"],
        "note": "Use inventory-purchasing when quantity, unit, or receiving invariants drive the change.",
    },
    {
        "skill": "restaurantpos-api-contract-gates",
        "keywords": ["openapi", "route surface", "form request", "resource", "contract artifact", "api artifact"],
        "paths": ["routes/api.php", "app/Http/Requests", "app/Http/Resources", "app/Services/ApiContract"],
        "representative_paths": ["routes/api.php", "app/Http/Requests"],
        "note": "Use API contract gates when consumer-visible request or response shape may drift.",
    },
    {
        "skill": "restaurantpos-ops-release-contract",
        "keywords": ["bootstrap", "release", "doctor", "deploy", "schema patch", "outbox health", "ops snapshot"],
        "paths": ["database/schema/mysql-schema.sql", "database/patches", "app/Services/BookingDoctorService.php", "app/Services/BookingDeploySafetyService.php"],
        "representative_paths": ["database/schema/mysql-schema.sql"],
        "note": "Use ops-release-contract when runtime readiness or SQL-first artifacts are in scope.",
    },
    {
        "skill": "restaurantpos-data-lifecycle",
        "keywords": ["privacy request", "data export", "anonymize", "anonymization", "retention", "redact", "delete my account", "privacy"],
        "paths": ["docs/data-lifecycle.md", "app/Services/DataLifecycle/CustomerPrivacyRequestService.php", "app/Services/DataLifecycle/CustomerAnonymizationService.php"],
        "representative_paths": ["app/Services/DataLifecycle/CustomerPrivacyRequestService.php", "app/Services/DataLifecycle/DataRetentionService.php"],
        "note": "Choose data lifecycle when the main invariant is what data must be kept, redacted, or purged.",
    },
    {
        "skill": "restaurantpos-notification-platform",
        "keywords": ["notification", "outbox", "email channel", "sms", "zalo", "quiet hours", "dead-letter", "dead letter", "reminder"],
        "paths": ["docs/runbooks/notification-platform-v2.md", "app/Services/NotificationOutboxService.php", "app/Services/Notifications/NotificationPreferenceService.php"],
        "representative_paths": ["app/Services/NotificationOutboxService.php", "app/Services/Notifications/NotificationPreferenceService.php"],
        "note": "Choose notification platform when enqueue, delivery, preference, or channel-driver behavior is the real owner.",
    },
    {
        "skill": "restaurantpos-conversation-inbox",
        "keywords": ["conversation inbox", "internal note", "assign conversation", "take over", "unassign", "linked reservation", "conversation.manage"],
        "paths": ["docs/runbooks/staff-conversation-inbox.md", "app/Http/Controllers/Api/Staff/StaffConversationInboxController.php", "app/Services/Staff/StaffConversationInboxService.php"],
        "representative_paths": ["app/Http/Controllers/Api/Staff/StaffConversationInboxController.php", "app/Services/Staff/StaffConversationInboxService.php"],
        "note": "Choose conversation inbox when the change affects triage, assignment, or conversation-to-reservation linkage.",
    },
    {
        "skill": "restaurantpos-branch-scheduling",
        "keywords": ["business hours", "closure window", "same-day cutoff", "same day cutoff", "branch timezone", "booking policy", "waiting list eligible"],
        "paths": ["docs/runbooks/branch-scheduling-policy-resolution.md", "app/Services/Branch/BranchSchedulingPolicyService.php", "app/Services/Branch/ReservationBranchScopeService.php"],
        "representative_paths": ["app/Services/Branch/BranchSchedulingPolicyService.php", "app/Services/Branch/ReservationBranchScopeService.php"],
        "note": "Choose branch scheduling when a branch-local policy or timezone drives downstream validation.",
    },
    {
        "skill": "restaurantpos-multi-branch-reporting",
        "keywords": ["reporting snapshot", "daily sales", "daily inventory", "daily operations", "default branch", "branch settings", "multi-branch", "multi branch"],
        "paths": ["app/Http/Controllers/Api/Admin/AdminBranchController.php", "app/Http/Controllers/Api/Admin/AdminReportingController.php", "app/Services/Reporting/ReportingSnapshotService.php"],
        "representative_paths": ["app/Services/Reporting/ReportingSnapshotService.php", "app/Services/Branch/BranchContextService.php"],
        "note": "Choose multi-branch reporting when branch scope, default branch behavior, or reporting aggregates are moving together.",
    },
]

SUPPORT_RULES = [
    {"skill": "restaurantpos-web-client-contracts", "keywords": ["frontend contract", "generated sdk", "typescript sdk", "postman", "api consumer", "error shape", "error envelope", "enum", "state exposure", "dx"]},
    {"skill": "restaurantpos-web-auth-session-contract", "keywords": ["header-based auth", "x-customer-token", "x-staff-key", "x-session-id", "access session", "session propagation", "customer login", "staff login", "cors auth"]},
    {"skill": "restaurantpos-ui-design-system-guardian", "keywords": ["ui", "page", "form", "table", "modal", "dialog", "status badge", "responsive", "accessibility", "a11y", "antd", "ant design", "tailwind", "shadcn"]},
    {"skill": "restaurantpos-targeted-verification", "keywords": ["fix", "implement", "change", "update", "refactor", "test", "verify", "regression", "bug"]},
    {"skill": "restaurantpos-shared-file-discipline", "keywords": ["routes/api.php", "config/booking.php", "config/staff_capabilities.php", "mysql-schema.sql", "shared file"]},
    {"skill": "restaurantpos-sql-first-schema-sync", "keywords": ["schema", "patch", "sql", "db_all.sql", "database contract"]},
    {"skill": "restaurantpos-runbook-sync", "keywords": ["runbook", "docs", "operator", "command", "launch readiness", "api consumer"]},
    {"skill": "restaurantpos-feature-flag-rollout", "keywords": ["feature flag", "rollout", "flag override", "kill switch"]},
    {"skill": "restaurantpos-audit-observability", "keywords": ["audit", "metrics", "alert", "health", "realtime", "outbox health"]},
    {"skill": "restaurantpos-performance-budget", "keywords": ["latency", "performance", "query", "n+1", "budget", "hot path"]},
    {"skill": "restaurantpos-git-aware-verify", "keywords": ["diff", "git", "changed files", "staged", "branch diff"]},
]


def repo_root() -> Path:
    return Path(__file__).resolve().parents[4]


def normalize_text(text: str) -> str:
    return " ".join(text.lower().split())


def collect_matches(prompt: str, extra_paths: list[str]) -> dict[str, object]:
    text = normalize_text(prompt)
    path_text = " ".join(path.lower().replace("\\", "/") for path in extra_paths)
    scores: list[dict[str, object]] = []

    for rule in DOMAIN_RULES:
        matched = sorted({keyword for keyword in rule["keywords"] if keyword in text})
        path_hits = sorted({path for path in rule["paths"] if path.lower().replace("\\", "/") in path_text})
        score = len(matched) + (2 * len(path_hits))
        scores.append(
            {
                "skill": rule["skill"],
                "score": score,
                "matched_terms": matched,
                "path_hits": path_hits,
                "paths": rule["paths"],
                "representative_paths": rule["representative_paths"],
                "note": rule["note"],
            }
        )

    scores.sort(key=lambda item: item["score"], reverse=True)
    primary = scores[0]
    notes: list[str] = []

    if primary["score"] == 0:
        primary = {
            "skill": "restaurantpos-context-router",
            "score": 0,
            "matched_terms": [],
            "path_hits": [],
            "paths": ["AGENTS.md", ".codex/AGENTS.md", ".agents/skills/restaurantpos-context-router/references/decision-map.md"],
            "representative_paths": [],
            "note": "No domain matched strongly; fall back to manual context routing.",
        }
    elif len(scores) > 1 and scores[1]["score"] > 0 and int(primary["score"]) - int(scores[1]["score"]) <= 1:
        notes.append(
            f"multi-domain tie between {primary['skill']} and {scores[1]['skill']}; consider restaurantpos-workstream-orchestrator before patching shared seams"
        )

    supporting: list[str] = []
    combined_text = f"{text} {path_text}"
    for rule in SUPPORT_RULES:
        if rule["skill"] == primary["skill"]:
            continue
        if any(keyword in combined_text for keyword in rule["keywords"]):
            supporting.append(rule["skill"])

    supporting = supporting[:3]
    notes.append(str(primary["note"]))

    return {
        "primary_skill": primary["skill"],
        "supporting_skills": supporting,
        "matched_terms": primary["matched_terms"],
        "first_pass_files": primary["paths"][:6],
        "representative_paths": primary["representative_paths"],
        "notes": notes,
    }


def collect_verification(paths: list[str]) -> dict[str, object] | None:
    if not paths:
        return None

    script = repo_root() / ".agents" / "skills" / "restaurantpos-targeted-verification" / "scripts" / "recommend_verify.py"
    if not script.exists():
        return None

    result = subprocess.run([sys.executable, str(script), "--json", *paths], cwd=repo_root(), capture_output=True, text=True)
    if result.returncode != 0:
        return {"error": result.stderr.strip() or result.stdout.strip()}

    return json.loads(result.stdout)


def main() -> int:
    parser = argparse.ArgumentParser(description="Route a RestaurantPOS task prompt to the smallest useful context.")
    parser.add_argument("prompt", nargs="*", help="Prompt text to classify")
    parser.add_argument("--path", action="append", default=[], dest="paths", help="Optional concrete file path hints")
    parser.add_argument("--json", action="store_true", dest="json_output", help="Emit machine-readable JSON")
    args = parser.parse_args()

    prompt = " ".join(args.prompt).strip()
    if not prompt:
        prompt = sys.stdin.read().strip()
    if not prompt:
        print("Provide a request as arguments or via stdin.", file=sys.stderr)
        return 1

    routed = collect_matches(prompt, args.paths)
    verification = collect_verification(list(routed["representative_paths"]))
    if verification is not None:
        routed["verification"] = verification

    if args.json_output:
        print(json.dumps(routed, indent=2))
        return 0

    print(f"Primary skill: ${routed['primary_skill']}")
    if routed["supporting_skills"]:
        print("Supporting skills:")
        for skill in routed["supporting_skills"]:
            print(f"- ${skill}")

    print("Read first:")
    for path in routed["first_pass_files"]:
        print(f"- {path}")

    if routed["matched_terms"]:
        print("Matched terms:")
        for term in routed["matched_terms"]:
            print(f"- {term}")

    if routed.get("verification", {}).get("commands"):
        print("Verification hints:")
        for command in routed["verification"]["commands"]:
            print(f"- {command}")

    if routed["notes"]:
        print("Notes:")
        for note in routed["notes"]:
            print(f"- {note}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
