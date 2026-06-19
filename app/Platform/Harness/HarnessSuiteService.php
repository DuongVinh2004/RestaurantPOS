<?php

declare(strict_types=1);

namespace App\Platform\Harness;

use App\Platform\ApiContract\ApiArtifacts\ApiConsumerArtifactService;
use App\Platform\ApiContract\ApiArtifacts\ApiEnumStateArtifactService;
use App\Platform\Release\Services\LaunchReadinessService;
use Illuminate\Support\Facades\File;

class HarnessSuiteService
{
    /**
     * @var list<array{
     *   key: string,
     *   label: string,
     *   manifest_refs: list<string>,
     *   routes: list<string>,
     *   headers: list<string>,
     *   tests: list<string>,
     *   smoke_command: string
     * }>
     */
    private const GOLDEN_FLOWS = [
        [
            'key' => 'customer_reservation_journey',
            'label' => 'Customer login -> availability -> hold -> reservation',
            'manifest_refs' => ['auth.customer_primary', 'scenarios.availability_hold_reservation', 'tables.main_4p'],
            'routes' => [
                'POST /api/v1/auth/customer/login',
                'GET /api/v1/tables/available',
                'POST /api/v1/table-holds',
                'POST /api/v1/reservations',
                'GET /api/v1/reservations/{id}',
            ],
            'headers' => ['X-Customer-Token', 'X-Session-Id', 'Idempotency-Key'],
            'tests' => [
                'tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php',
                'tests/Feature/Reservation/CustomerReservationSelfServiceHttpFlowTest.php',
            ],
            'smoke_command' => 'php artisan test tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php tests/Feature/Reservation/CustomerReservationSelfServiceHttpFlowTest.php',
        ],
        [
            'key' => 'deposit_self_pay',
            'label' => 'Customer deposit self-pay session lifecycle',
            'manifest_refs' => ['reservations.deposit_pending', 'scenarios.deposit_self_pay', 'auth.customer_primary'],
            'routes' => [
                'GET /api/v1/reservations/{id}/deposit-preview',
                'POST /api/v1/reservations/{id}/deposit/acknowledge',
                'POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions',
                'POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm',
            ],
            'headers' => ['X-Customer-Token', 'X-Session-Id', 'Idempotency-Key'],
            'tests' => [
                'tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php',
                'tests/Feature/Reservation/CustomerReservationDepositSelfServiceFlowTest.php',
            ],
            'smoke_command' => 'php artisan test tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php tests/Feature/Reservation/CustomerReservationDepositSelfServiceFlowTest.php',
        ],
        [
            'key' => 'dine_in_checkout',
            'label' => 'Staff check-in -> order -> bill -> settlement finalize',
            'manifest_refs' => ['reservations.dine_in_checkin', 'scenarios.dine_in_checkout', 'auth.staff'],
            'routes' => [
                'POST /api/v1/staff/reservations/{id}/check-in',
                'POST /api/v1/staff/tables/{table_id}/orders',
                'POST /api/v1/staff/orders/{order_id}/bill-snapshot',
                'POST /api/v1/staff/orders/{order_id}/settlement/finalize',
            ],
            'headers' => ['X-Staff-Key', 'Idempotency-Key'],
            'tests' => [
                'tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php',
                'tests/Feature/Staff/StaffCheckout*.php',
            ],
            'smoke_command' => 'php artisan test tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php tests/Feature/Staff/StaffCheckout*.php',
        ],
        [
            'key' => 'refund_path',
            'label' => 'Refund preview -> refund -> refund cancel',
            'manifest_refs' => ['reservations.refund_partial_ready', 'reservations.refund_cancel_ready', 'auth.staff'],
            'routes' => [
                'GET /api/v1/staff/reservations/{reservation_id}/refund-preview',
                'POST /api/v1/staff/reservations/{reservation_id}/refund',
                'POST /api/v1/staff/reservations/{reservation_id}/refund-cancel',
            ],
            'headers' => ['X-Staff-Key', 'Idempotency-Key'],
            'tests' => [
                'tests/Feature/Staff/StaffCheckout*.php',
                'tests/Feature/Payments',
            ],
            'smoke_command' => 'php artisan test tests/Feature/Staff/StaffCheckout*.php tests/Feature/Payments',
        ],
        [
            'key' => 'waiting_list_roundtrip',
            'label' => 'Waiting-list customer + staff roundtrip',
            'manifest_refs' => ['waiting_list', 'scenarios.waiting_list_lifecycle', 'auth.customer_primary', 'auth.staff'],
            'routes' => [
                'POST /api/v1/waiting-list',
                'POST /api/v1/waiting-list/{id}/accept',
                'GET /api/v1/staff/waiting-list',
                'POST /api/v1/staff/waiting-list/{id}/notify',
                'POST /api/v1/staff/waiting-list/{id}/seat',
            ],
            'headers' => ['X-Customer-Token', 'X-Session-Id', 'X-Staff-Key', 'Idempotency-Key'],
            'tests' => [
                'tests/Feature/WaitingList',
                'tests/Feature/Staff/StaffWaitingList*.php',
            ],
            'smoke_command' => 'php artisan test tests/Feature/WaitingList tests/Feature/Staff/StaffWaitingList*.php',
        ],
    ];

    /**
     * @var list<array{label: string, command: string, purpose: string}>
     */
    private const RUNTIME_GATES = [
        [
            'label' => 'Runtime smoke boundary gate',
            'command' => 'php artisan test tests/Feature/Infrastructure/ApiRuntimeSmokeGateTest.php',
            'purpose' => 'Check locked smoke URIs still return allowed boundary statuses.',
        ],
        [
            'label' => 'Live runtime regression gate',
            'command' => 'php artisan test tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php',
            'purpose' => 'Exercise live login, reservation, checkout alias, and capability boundary behavior.',
        ],
        [
            'label' => 'Doctor preflight',
            'command' => 'php artisan booking:doctor --json',
            'purpose' => 'Validate runtime dependencies, scheduler heartbeat, and core configuration.',
        ],
        [
            'label' => 'Deploy preflight',
            'command' => 'php artisan booking:deploy-check --mode=preflight',
            'purpose' => 'Verify release artifact, environment, and rollout guardrails before launch.',
        ],
    ];

    public function __construct(
        private readonly ApiConsumerArtifactService $apiConsumerArtifacts,
        private readonly ApiEnumStateArtifactService $enumStateArtifacts,
        private readonly LaunchReadinessService $launchReadiness,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function buildWebAuthReport(): array
    {
        $customerHeader = (string) config('customer_auth.header', 'X-Customer-Token');
        $staffHeader = 'X-Staff-Key';
        $staffCsrfHeader = (string) config('staff_auth.browser_session.csrf_header', 'X-Staff-CSRF');
        $sessionHeader = 'X-Session-Id';
        $staffBrowserSessionEnabled = (bool) config('staff_auth.browser_session.enabled', false);
        $supportsCredentials = (bool) config('cors.supports_credentials', false);
        $allowedHeaders = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) config('cors.allowed_headers', [])
        );
        $allowedOrigins = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) config('cors.allowed_origins', [])
        ), static fn (string $value): bool => $value !== ''));

        $checks = [
            [
                'key' => 'customer_auth_enabled',
                'severity' => 'error',
                'ok' => (bool) config('customer_auth.enabled', false),
                'message' => 'Customer access-session auth must stay enabled for customer-web.',
            ],
            [
                'key' => 'browser_credentials_disabled',
                'severity' => 'error',
                'ok' => $staffBrowserSessionEnabled ? $supportsCredentials : $supportsCredentials === false,
                'message' => $staffBrowserSessionEnabled
                    ? 'Staff browser refresh-cookie rollout requires credentials mode for exact allowed origins.'
                    : 'Split web default stays header-based; credentials mode must remain disabled unless staff refresh-cookie rollout is enabled.',
            ],
            [
                'key' => 'customer_header_allowed',
                'severity' => 'error',
                'ok' => in_array(strtolower($customerHeader), $allowedHeaders, true),
                'message' => sprintf('CORS must allow the customer auth header [%s].', $customerHeader),
            ],
            [
                'key' => 'staff_header_allowed',
                'severity' => 'error',
                'ok' => in_array(strtolower($staffHeader), $allowedHeaders, true),
                'message' => 'CORS must allow the staff auth header [X-Staff-Key].',
            ],
            [
                'key' => 'staff_csrf_header_allowed',
                'severity' => 'error',
                'ok' => in_array(strtolower($staffCsrfHeader), $allowedHeaders, true),
                'message' => sprintf('CORS must allow the staff browser CSRF header [%s].', $staffCsrfHeader),
            ],
            [
                'key' => 'session_header_allowed',
                'severity' => 'error',
                'ok' => in_array(strtolower($sessionHeader), $allowedHeaders, true),
                'message' => 'CORS must allow the session propagation header [X-Session-Id].',
            ],
            [
                'key' => 'session_bound_routes_registered',
                'severity' => 'error',
                'ok' => count((array) config('customer_auth.session_bound_route_contracts', [])) > 0,
                'message' => 'Customer session-bound route contracts must stay explicit in config/customer_auth.php.',
            ],
            [
                'key' => 'origins_configured',
                'severity' => 'warn',
                'ok' => $allowedOrigins !== [],
                'message' => 'CORS allowed origins are currently empty; cross-origin browser requests will be denied until env values are configured.',
            ],
        ];

        $ok = collect($checks)->every(static fn (array $check): bool => $check['severity'] !== 'error' || (bool) $check['ok']);

        return [
            'ok' => $ok,
            'batch' => 'web_auth_session',
            'frontends' => [
                ['key' => 'customer-web', 'stack' => 'Next.js + TypeScript', 'dev_origin' => 'http://localhost:3000'],
                ['key' => 'staff-web', 'stack' => 'React + TypeScript + Vite', 'dev_origin' => 'http://localhost:5173'],
            ],
            'headers' => [
                'customer_auth' => $customerHeader,
                'staff_auth' => $staffHeader,
                'staff_csrf' => $staffCsrfHeader,
                'session' => $sessionHeader,
                'idempotency' => 'Idempotency-Key',
                'request_id' => 'X-Request-Id',
            ],
            'staff_startup' => [
                'source' => 'Staff auth session envelope (login/me/refresh)',
                'fields' => [
                    'data.startup.primary_workspace',
                    'data.startup.available_workspaces',
                    'data.startup.default_branch_id',
                    'data.startup.allowed_branch_ids',
                    'data.startup.assigned_station_ids',
                    'data.startup.default_branch',
                    'data.startup.active_cashier_shift',
                    'data.startup.readiness.access',
                    'data.startup.readiness.branch',
                    'data.startup.readiness.cashier_shift',
                    'data.startup.readiness.operator_ready',
                ],
            ],
            'contract' => [
                'supports_credentials' => $supportsCredentials,
                'staff_browser_session_cookie_enabled' => $staffBrowserSessionEnabled,
                'staff_refresh_cookie_name' => (string) config('staff_auth.browser_session.refresh_cookie_name', 'staff_web_refresh'),
                'staff_csrf_header' => $staffCsrfHeader,
                'allowed_origins' => $allowedOrigins,
                'session_bound_route_count' => count((array) config('customer_auth.session_bound_route_contracts', [])),
                'customer_allow_bearer' => (bool) config('customer_auth.allow_bearer', false),
                'staff_allow_bearer' => (bool) config('staff_auth.allow_bearer', false),
            ],
            'checks' => $checks,
            'verify' => [
                'php artisan test tests/Feature/Auth tests/Unit/Http/Middleware tests/Unit/Config/CustomerAuthConfigContractTest.php tests/Unit/Config/StaffAuthConfigContractTest.php',
                'php artisan test tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php tests/Feature/CorsContractTest.php',
                'php artisan test tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php',
            ],
            'docs' => [
                'docs/runbooks/api-consumer-artifacts.md',
                'docs/runbooks/booking-api-contract.md',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildFeContractReport(
        ?string $outputRoot = null,
        ?string $specPath = null,
        bool $refreshOpenApi = false,
    ): array {
        $payload = $this->apiConsumerArtifacts->generate(
            outputRoot: $outputRoot,
            specPath: $specPath,
            refreshOpenApi: $refreshOpenApi,
        );

        return [
            'ok' => (bool) ($payload['ok'] ?? false),
            'batch' => 'fe_contract',
            'official_sources' => [
                'frozen_openapi' => (string) ($payload['spec_path'] ?? ''),
                'sdk_typescript' => (string) (($payload['artifacts'] ?? [])['sdk_typescript'] ?? ''),
                'sdk_enums' => (string) (($payload['artifacts'] ?? [])['enum_state_typescript'] ?? ''),
                'mutation_contract' => (string) (($payload['artifacts'] ?? [])['mutation_contract'] ?? ''),
                'enum_state_json' => (string) (($payload['artifacts'] ?? [])['enum_state_json'] ?? ''),
            ],
            'artifacts' => (array) ($payload['artifacts'] ?? []),
            'summary' => (array) ($payload['summary'] ?? []),
            'contract_report_summary' => (array) ($payload['contract_report_summary'] ?? []),
            'verify' => [
                'composer api:artifacts',
                'php artisan test tests/Feature/Infrastructure tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php',
                'php artisan test tests/Feature/Http/ApiListingQueryStandardTest.php tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php tests/Feature/CorsContractTest.php tests/Unit/Config/ApiArtifactsConfigContractTest.php',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildGoldenFlowReport(?string $manifestPath = null): array
    {
        $resolvedManifestPath = $this->resolveManifestPath($manifestPath);
        $manifest = null;
        $bootstrapSummary = null;
        $notes = [];

        if ($resolvedManifestPath !== null && File::exists($resolvedManifestPath)) {
            /** @var array<string,mixed> $manifest */
            $manifest = json_decode((string) File::get($resolvedManifestPath), true, 512, JSON_THROW_ON_ERROR);
        } elseif ($resolvedManifestPath !== null) {
            $notes[] = sprintf('manifest path [%s] does not exist yet; scenario definitions are still reported without resolved IDs', $resolvedManifestPath);
        } else {
            $notes[] = 'no manifest path supplied; scenario definitions are reported without resolved UAT identifiers';
        }

        $scenarios = [];
        foreach (self::GOLDEN_FLOWS as $definition) {
            $resolvedRefs = [];
            foreach ($definition['manifest_refs'] as $ref) {
                $resolvedRefs[$ref] = $manifest !== null ? data_get($manifest, $ref) : null;
            }

            $scenarios[] = array_merge($definition, [
                'manifest_context' => $resolvedRefs,
            ]);
        }

        return [
            'ok' => true,
            'batch' => 'golden_flows',
            'manifest_path' => $resolvedManifestPath,
            'manifest_available' => $manifest !== null,
            'bootstrap_summary' => $bootstrapSummary,
            'scenarios' => $scenarios,
            'runtime_gate_commands' => array_map(
                static fn (array $gate): string => (string) $gate['command'],
                self::RUNTIME_GATES
            ),
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildEnumStateReport(?string $outputRoot = null): array
    {
        $payload = $this->enumStateArtifacts->generate($outputRoot);

        return [
            'ok' => (bool) ($payload['ok'] ?? false),
            'batch' => 'enum_state_export',
            'artifacts' => (array) ($payload['artifacts'] ?? []),
            'summary' => (array) ($payload['summary'] ?? []),
            'sources' => [
                'php_enums' => 'app/Enums',
                'contract_json' => (string) (($payload['artifacts'] ?? [])['enum_state_json'] ?? ''),
                'contract_typescript' => (string) (($payload['artifacts'] ?? [])['enum_state_typescript'] ?? ''),
            ],
            'verify' => [
                'php artisan booking:harness:enum-state --json',
                'composer api:artifacts',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildReleaseReadinessReport(
        string $target = 'staging',
        ?string $manualEvidencePath = null,
        ?string $packageId = null,
        bool $overwritePackage = false,
        int $paymentSampleLimit = 10,
        ?string $manifestPath = null,
    ): array {
        $goldenFlows = $this->buildGoldenFlowReport($manifestPath);
        $readiness = $this->launchReadiness->evaluate(
            target: $target,
            manualEvidencePath: $manualEvidencePath,
            packageId: $packageId,
            overwritePackage: $overwritePackage,
            paymentSampleLimit: $paymentSampleLimit,
        );

        return [
            'ok' => true,
            'batch' => 'release_runtime',
            'readiness' => $readiness,
            'golden_flows' => $goldenFlows,
            'runtime_gates' => self::RUNTIME_GATES,
            'recommended_commands' => [
                'php artisan booking:doctor --json',
                'php artisan booking:deploy-check --mode=preflight',
                'php artisan booking:release-manifest --verify-frozen --json',
                'php artisan test tests/Feature/Infrastructure/ApiRuntimeSmokeGateTest.php tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php',
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $feContractPayload
     * @return array<string,mixed>
     */
    public function buildReleaseBuildContext(?string $manifestPath = null, array $feContractPayload = []): array
    {
        $webAuth = $this->buildWebAuthReport();
        $goldenFlows = $this->buildGoldenFlowReport($manifestPath);
        $scenarioKeys = collect((array) ($goldenFlows['scenarios'] ?? []))
            ->map(static fn (array $scenario): string => (string) ($scenario['key'] ?? ''))
            ->filter(static fn (string $key): bool => $key !== '')
            ->values()
            ->all();

        $recommendedCommands = [
            'php artisan booking:harness:web-auth --json',
            $manifestPath !== null && trim($manifestPath) !== ''
                ? sprintf('php artisan booking:harness:golden-flows --json --manifest-path=%s', $manifestPath)
                : 'php artisan booking:harness:golden-flows --json',
            'php artisan booking:deploy-check --mode=preflight',
            'php artisan booking:launch-readiness --target=staging --json',
        ];

        return [
            'ok' => (bool) ($webAuth['ok'] ?? false) && (bool) ($feContractPayload['ok'] ?? true),
            'web_auth' => [
                'ok' => (bool) ($webAuth['ok'] ?? false),
                'headers' => (array) ($webAuth['headers'] ?? []),
                'checks' => (array) ($webAuth['checks'] ?? []),
                'verify' => (array) ($webAuth['verify'] ?? []),
            ],
            'fe_contract' => [
                'ok' => (bool) ($feContractPayload['ok'] ?? false),
                'spec_path' => (string) ($feContractPayload['spec_path'] ?? ''),
                'output_root' => (string) ($feContractPayload['output_root'] ?? ''),
                'summary' => (array) ($feContractPayload['summary'] ?? []),
                'artifacts' => (array) ($feContractPayload['artifacts'] ?? []),
            ],
            'golden_flows' => [
                'manifest_path' => (string) ($goldenFlows['manifest_path'] ?? ''),
                'manifest_available' => (bool) ($goldenFlows['manifest_available'] ?? false),
                'scenario_count' => count((array) ($goldenFlows['scenarios'] ?? [])),
                'scenario_keys' => $scenarioKeys,
                'runtime_gate_commands' => (array) ($goldenFlows['runtime_gate_commands'] ?? []),
                'notes' => (array) ($goldenFlows['notes'] ?? []),
            ],
            'recommended_commands' => $recommendedCommands,
        ];
    }

    private function resolveManifestPath(?string $manifestPath): ?string
    {
        $candidate = trim((string) ($manifestPath ?? ''));
        if ($candidate === '') {
            return null;
        }

        if ($this->isAbsolutePath($candidate)) {
            return $candidate;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate));
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:\\\\|\\\\\\\\|\\/)/', $path) === 1;
    }
}
