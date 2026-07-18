import assert from 'node:assert/strict';
import test from 'node:test';

import { evaluateAuditPolicy } from '../dependency-security-gate.mjs';

function auditReport(counts, vulnerabilities = {}) {
  return {
    metadata: {
      vulnerabilities: {
        info: 0,
        low: 0,
        moderate: 0,
        high: 0,
        critical: 0,
        total: 0,
        ...counts,
      },
    },
    vulnerabilities,
  };
}

test('production High or Critical advisories block the dependency policy', () => {
  const report = auditReport(
    { high: 1, total: 1 },
    {
      next: {
        isDirect: true,
        severity: 'high',
        range: '<16.2.10',
        fixAvailable: { name: 'next', version: '16.2.10', isSemVerMajor: false },
      },
    },
  );

  const summary = evaluateAuditPolicy(report, 'customer-web');

  assert.equal(summary.decision, 'block');
  assert.equal(summary.blocking_count, 1);
  assert.deepEqual(summary.direct_vulnerabilities.map(({ name }) => name), ['next']);
});

test('non-High production advisories remain visible without violating the B10 threshold', () => {
  const report = auditReport(
    { moderate: 1, total: 1 },
    {
      example: {
        isDirect: true,
        severity: 'moderate',
        range: '<2.0.0',
        fixAvailable: true,
      },
    },
  );

  const summary = evaluateAuditPolicy(report, 'staff-web');

  assert.equal(summary.decision, 'pass');
  assert.equal(summary.counts.moderate, 1);
  assert.equal(summary.direct_vulnerabilities[0].severity, 'moderate');
});

test('missing npm audit metadata fails closed', () => {
  assert.throws(
    () => evaluateAuditPolicy({}, 'root'),
    /did not return vulnerability metadata/,
  );
});
