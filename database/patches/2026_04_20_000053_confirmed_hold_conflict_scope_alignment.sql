SET NAMES utf8mb4;

-- Confirmed holds are reservation linkage artifacts. Runtime overlap guards must
-- follow live reservations plus unexpired Holding/Pending holds only, matching
-- App\Modules\BranchScheduling\Domain\Guards\HoldConflictScope.

DROP TRIGGER IF EXISTS `trg_reservation_tables__bi_prevent_overlap`;
DROP TRIGGER IF EXISTS `trg_reservation_tables__bu_prevent_overlap`;
DROP TRIGGER IF EXISTS `trg_table_hold_details__bi_prevent_overlap`;
DROP TRIGGER IF EXISTS `trg_table_hold_details__bu_prevent_overlap`;

DELIMITER $$

CREATE TRIGGER `trg_reservation_tables__bi_prevent_overlap`
BEFORE INSERT ON `reservation_tables`
FOR EACH ROW
BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `status`
      INTO v_start, v_end, v_status
      FROM `reservations`
     WHERE `reservation_id` = NEW.`reservation_id`
     LIMIT 1;

    IF v_status IN ('Confirmed', 'Reserved') THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND rt.`reservation_id` <> NEW.`reservation_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with another active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND th.`hold_status` IN ('Holding', 'Pending')
           AND th.`expire_at` > CURRENT_TIMESTAMP(6)
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start
           AND (th.`confirmed_reservation_id` IS NULL OR th.`confirmed_reservation_id` <> NEW.`reservation_id`);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with active table hold';
        END IF;
    END IF;
END $$

CREATE TRIGGER `trg_reservation_tables__bu_prevent_overlap`
BEFORE UPDATE ON `reservation_tables`
FOR EACH ROW
BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `status`
      INTO v_start, v_end, v_status
      FROM `reservations`
     WHERE `reservation_id` = NEW.`reservation_id`
     LIMIT 1;

    IF v_status IN ('Confirmed', 'Reserved') THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND rt.`reservation_id` <> NEW.`reservation_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with another active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND th.`hold_status` IN ('Holding', 'Pending')
           AND th.`expire_at` > CURRENT_TIMESTAMP(6)
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start
           AND (th.`confirmed_reservation_id` IS NULL OR th.`confirmed_reservation_id` <> NEW.`reservation_id`);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'reservation_tables overlap conflict with active table hold';
        END IF;
    END IF;
END $$

CREATE TRIGGER `trg_table_hold_details__bi_prevent_overlap`
BEFORE INSERT ON `table_hold_details`
FOR EACH ROW
BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_expire_at DATETIME(6);
    DECLARE v_confirmed_reservation_id INT UNSIGNED;
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `hold_status`, `expire_at`, `confirmed_reservation_id`
      INTO v_start, v_end, v_status, v_expire_at, v_confirmed_reservation_id
      FROM `table_holds`
     WHERE `hold_id` = NEW.`hold_id`
     LIMIT 1;

    IF v_status IN ('Holding', 'Pending') AND v_expire_at > CURRENT_TIMESTAMP(6) THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start
           AND (v_confirmed_reservation_id IS NULL OR rt.`reservation_id` <> v_confirmed_reservation_id);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND thd.`hold_id` <> NEW.`hold_id`
           AND th.`hold_status` IN ('Holding', 'Pending')
           AND th.`expire_at` > CURRENT_TIMESTAMP(6)
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with another active hold';
        END IF;
    END IF;
END $$

CREATE TRIGGER `trg_table_hold_details__bu_prevent_overlap`
BEFORE UPDATE ON `table_hold_details`
FOR EACH ROW
BEGIN
    DECLARE v_start DATETIME(6);
    DECLARE v_end DATETIME(6);
    DECLARE v_status VARCHAR(20);
    DECLARE v_expire_at DATETIME(6);
    DECLARE v_confirmed_reservation_id INT UNSIGNED;
    DECLARE v_conflict_count BIGINT DEFAULT 0;

    SELECT `start_time`, `end_time`, `hold_status`, `expire_at`, `confirmed_reservation_id`
      INTO v_start, v_end, v_status, v_expire_at, v_confirmed_reservation_id
      FROM `table_holds`
     WHERE `hold_id` = NEW.`hold_id`
     LIMIT 1;

    IF v_status IN ('Holding', 'Pending') AND v_expire_at > CURRENT_TIMESTAMP(6) THEN
        SELECT COUNT(*) INTO v_conflict_count
          FROM `reservation_tables` rt
          JOIN `reservations` r ON r.`reservation_id` = rt.`reservation_id`
         WHERE rt.`table_id` = NEW.`table_id`
           AND r.`status` IN ('Confirmed', 'Reserved')
           AND r.`start_time` < v_end
           AND r.`end_time` > v_start
           AND (v_confirmed_reservation_id IS NULL OR rt.`reservation_id` <> v_confirmed_reservation_id);
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with active reservation';
        END IF;

        SELECT COUNT(*) INTO v_conflict_count
          FROM `table_hold_details` thd
          JOIN `table_holds` th ON th.`hold_id` = thd.`hold_id`
         WHERE thd.`table_id` = NEW.`table_id`
           AND thd.`hold_id` <> NEW.`hold_id`
           AND th.`hold_status` IN ('Holding', 'Pending')
           AND th.`expire_at` > CURRENT_TIMESTAMP(6)
           AND th.`start_time` < v_end
           AND th.`end_time` > v_start;
        IF v_conflict_count > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'table_hold_details overlap conflict with another active hold';
        END IF;
    END IF;
END $$

DELIMITER ;
