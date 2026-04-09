<?php

declare(strict_types=1);

/**
 * Legacy residue tombstone.
 *
 * The canonical staff order read controller is:
 * - app/Http/Controllers/Api/Staff/StaffOrderReadController.php
 *
 * This file previously declared the same FQCN from a non-PSR-4 path under app/Services/Staff,
 * which is dead in the current runtime contract and can create namespace/path ambiguity.
 *
 * The file is intentionally left without a class so overlay-based cleanup can neutralize the
 * dead duplicate without requiring a destructive delete step.
 */
