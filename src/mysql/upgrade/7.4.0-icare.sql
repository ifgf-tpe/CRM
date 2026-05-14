-- ChurchCRM 7.4.0 — iCare (cell group) attendance tables

CREATE TABLE `icare_meeting` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `group_id`       MEDIUMINT(8) UNSIGNED NOT NULL,
    `meeting_date`   DATE            NOT NULL,
    `location`       VARCHAR(255)    DEFAULT NULL,
    `notes`          LONGTEXT        DEFAULT NULL,
    `photo_filename` VARCHAR(255)    DEFAULT NULL,
    `created_by`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `icare_meeting_group_date` (`group_id`, `meeting_date`),
    CONSTRAINT `fk_icare_meeting_group`
        FOREIGN KEY (`group_id`) REFERENCES `group_grp` (`grp_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `icare_attendance` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `meeting_id`  INT UNSIGNED NOT NULL,
    `person_id`   INT UNSIGNED NOT NULL,
    `recorded_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `icare_attend_unique` (`meeting_id`, `person_id`),
    INDEX `icare_attendance_person` (`person_id`),
    CONSTRAINT `fk_icare_attend_meeting`
        FOREIGN KEY (`meeting_id`) REFERENCES `icare_meeting` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_icare_attend_person`
        FOREIGN KEY (`person_id`) REFERENCES `person_per` (`per_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `icare_visitor` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `meeting_id`  INT UNSIGNED  NOT NULL,
    `full_name`   VARCHAR(100)  NOT NULL DEFAULT '',
    `phone`       VARCHAR(30)   DEFAULT NULL,
    `instagram`   VARCHAR(100)  DEFAULT NULL,
    `address`     VARCHAR(255)  DEFAULT NULL,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `icare_visitor_meeting` (`meeting_id`),
    CONSTRAINT `fk_icare_visitor_meeting`
        FOREIGN KEY (`meeting_id`) REFERENCES `icare_meeting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
