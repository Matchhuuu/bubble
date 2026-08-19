-- Run this SQL in your Aiven database to create the sessions table
-- Go to: https://console.aiven.io → your MySQL service → Query Editor

CREATE TABLE IF NOT EXISTS `sessions` (
    `id`        VARCHAR(128) NOT NULL,
    `data`      MEDIUMTEXT   NOT NULL,
    `timestamp` INT(11)      NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
