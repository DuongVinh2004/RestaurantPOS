<?php

declare(strict_types=1);

namespace App\Support;

final class ApiErrorCategory
{
    public const VALIDATION_ERROR = 'validation_error';

    public const AUTHENTICATION_REQUIRED = 'authentication_required';

    public const FORBIDDEN_CAPABILITY = 'forbidden_capability';

    public const OWNER_SCOPE_DENIED = 'owner_scope_denied';

    public const POLICY_DENIED = 'policy_denied';

    public const RESOURCE_CONFLICT = 'resource_conflict';

    public const STALE_WRITE = 'stale_write';

    public const NOT_FOUND = 'not_found';

    public const DOMAIN_INVARIANT_VIOLATION = 'domain_invariant_violation';

    public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';

    public const RATE_LIMITED = 'rate_limited';

    public const HTTP_ERROR = 'http_error';

    public const DATABASE_ERROR = 'database_error';

    public const DEPENDENCY_UNAVAILABLE = 'dependency_unavailable';

    public const INTERNAL_ERROR = 'internal_error';
}
