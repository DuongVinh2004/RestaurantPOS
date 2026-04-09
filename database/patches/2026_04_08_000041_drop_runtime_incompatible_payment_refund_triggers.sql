-- MySQL rejects this self-referencing payments trigger pattern at runtime with ERROR 1442
-- during refund execution. Keep refund lineage and cap invariants enforced in the
-- canonical checkout/refund services and end bootstrap without reinstalling the triggers.
DROP TRIGGER IF EXISTS `trg_payments__bi_refund_cap`;
DROP TRIGGER IF EXISTS `trg_payments__bu_refund_cap`;
DROP TRIGGER IF EXISTS `trg_payments__bi_refund_lineage_guard`;
DROP TRIGGER IF EXISTS `trg_payments__bu_refund_lineage_guard`;
