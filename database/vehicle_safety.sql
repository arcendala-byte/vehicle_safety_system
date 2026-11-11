-- ============================================================
-- VEHISAFE — DATABASE SCHEMA
-- AI-Based Intelligent Vehicle Safety Monitoring and
-- Road Black Spot Prediction System
-- ============================================================
-- Author:  Dickson Munene Muchiri (DIT/2025/40888)
-- School:  Mount Kenya University — Computing & Informatics
-- Engine:  InnoDB (foreign keys, transactions)
-- Charset: utf8mb4 (full Unicode support)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";  -- Africa/Nairobi

-- ------------------------------------------------------------
-- DATABASE CREATION
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `vehicle_safety`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_general_ci;

USE `vehicle_safety`;

-- ============================================================
-- PART 3A: CORE IDENTITY TABLES
-- users → drivers → vehicles
-- ============================================================

-- ------------------------------------------------------------
-- TABLE: users
-- Central authentication table for admin and driver accounts.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `user_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(50)  NOT NULL,
    `email`          VARCHAR(120) NOT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `role`           ENUM('admin', 'driver') NOT NULL DEFAULT 'driver',
    `full_name`      VARCHAR(100) NOT NULL,
    `status`         ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `last_login`     DATETIME NULL DEFAULT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email`    (`email`),
    KEY `idx_users_role`           (`role`),
    KEY `idx_users_status`         (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- TABLE: drivers
-- Extended driver profile. Links to a user account.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `drivers`;
CREATE TABLE `drivers` (
    `driver_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NULL,
    `full_name`       VARCHAR(100) NOT NULL,
    `phone`           VARCHAR(20)  NULL,
    `email`           VARCHAR(120) NULL,
    `license_number`  VARCHAR(50)  NOT NULL,
    `license_expiry`  DATE         NULL,
    `status`          ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `current_score`   TINYINT UNSIGNED NOT NULL DEFAULT 100,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`driver_id`),
    UNIQUE KEY `uk_drivers_license`  (`license_number`),
    KEY `idx_drivers_user`           (`user_id`),
    KEY `idx_drivers_status`         (`status`),
    KEY `idx_drivers_score`          (`current_score`),

    CONSTRAINT `fk_drivers_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- TABLE: vehicles
-- Vehicle registry, optionally assigned to a driver.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE `vehicles` (
    `vehicle_id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `registration_number`   VARCHAR(20)  NOT NULL,
    `make`                  VARCHAR(50)  NULL,
    `model`                 VARCHAR(50)  NULL,
    `year`                  YEAR         NULL,
    `vehicle_type`          ENUM('private', 'commercial', 'psv', 'truck', 'bus', 'other') NOT NULL DEFAULT 'private',
    `assigned_driver_id`    INT UNSIGNED NULL,
    `status`                ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`vehicle_id`),
    UNIQUE KEY `uk_vehicles_reg`     (`registration_number`),
    KEY `idx_vehicles_driver`        (`assigned_driver_id`),
    KEY `idx_vehicles_status`        (`status`),
    KEY `idx_vehicles_type`          (`vehicle_type`),

    CONSTRAINT `fk_vehicles_driver`
        FOREIGN KEY (`assigned_driver_id`) REFERENCES `drivers` (`driver_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- END OF PART 3A
-- ============================================================


-- ============================================================
-- PART 3B: OPERATIONAL TABLES
-- trips → driver_violations → gps_locations → driver_safety_scores
-- ============================================================

-- ------------------------------------------------------------
-- TABLE: trips
-- A single journey made by a driver using a vehicle.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `trips`;
CREATE TABLE `trips` (
    `trip_id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `driver_id`          INT UNSIGNED NOT NULL,
    `vehicle_id`         INT UNSIGNED NOT NULL,
    `start_time`         DATETIME NOT NULL,
    `end_time`           DATETIME NULL DEFAULT NULL,
    `start_latitude`     DECIMAL(10, 7) NULL,
    `start_longitude`    DECIMAL(10, 7) NULL,
    `end_latitude`       DECIMAL(10, 7) NULL,
    `end_longitude`      DECIMAL(10, 7) NULL,
    `distance_km`        DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
    `violation_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `safety_score`       TINYINT UNSIGNED NULL,
    `status`             ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    `notes`              TEXT NULL,
    `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`trip_id`),
    KEY `idx_trips_driver`       (`driver_id`),
    KEY `idx_trips_vehicle`      (`vehicle_id`),
    KEY `idx_trips_status`       (`status`),
    KEY `idx_trips_start_time`   (`start_time`),

    CONSTRAINT `fk_trips_driver`
        FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_trips_vehicle`
        FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- TABLE: driver_violations
-- A single recorded unsafe driving event.
-- Can optionally belong to a trip.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `driver_violations`;
CREATE TABLE `driver_violations` (
    `violation_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `driver_id`      INT UNSIGNED NOT NULL,
    `vehicle_id`     INT UNSIGNED NULL,
    `trip_id`        INT UNSIGNED NULL,
    `event_type`     ENUM(
                        'seatbelt_violation',
                        'phone_usage',
                        'drowsiness',
                        'distraction',
                        'speeding',
                        'blackspot_warning',
                        'other'
                     ) NOT NULL,
    `severity`       ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `confidence`     DECIMAL(4, 3) NULL,       -- e.g. 0.945
    `latitude`       DECIMAL(10, 7) NULL,
    `longitude`      DECIMAL(10, 7) NULL,
    `description`    VARCHAR(255) NULL,
    `status`         ENUM('open', 'acknowledged', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    `occurred_at`    DATETIME NOT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`violation_id`),
    KEY `idx_violations_driver`      (`driver_id`),
    KEY `idx_violations_vehicle`     (`vehicle_id`),
    KEY `idx_violations_trip`        (`trip_id`),
    KEY `idx_violations_type`        (`event_type`),
    KEY `idx_violations_severity`    (`severity`),
    KEY `idx_violations_status`      (`status`),
    KEY `idx_violations_occurred`    (`occurred_at`),

    CONSTRAINT `fk_violations_driver`
        FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_violations_vehicle`
        FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `fk_violations_trip`
        FOREIGN KEY (`trip_id`) REFERENCES `trips` (`trip_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- TABLE: gps_locations
-- Time-series GPS breadcrumbs recorded during a trip.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `gps_locations`;
CREATE TABLE `gps_locations` (
    `location_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trip_id`       INT UNSIGNED NOT NULL,
    `driver_id`     INT UNSIGNED NOT NULL,
    `latitude`      DECIMAL(10, 7) NOT NULL,
    `longitude`     DECIMAL(10, 7) NOT NULL,
    `speed_kmh`     DECIMAL(6, 2) NULL,
    `heading_deg`   SMALLINT UNSIGNED NULL,
    `recorded_at`   DATETIME NOT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`location_id`),
    KEY `idx_gps_trip`       (`trip_id`),
    KEY `idx_gps_driver`     (`driver_id`),
    KEY `idx_gps_recorded`   (`recorded_at`),

    CONSTRAINT `fk_gps_trip`
        FOREIGN KEY (`trip_id`) REFERENCES `trips` (`trip_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_gps_driver`
        FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- TABLE: driver_safety_scores
-- Historical record of a driver's safety score.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `driver_safety_scores`;
CREATE TABLE `driver_safety_scores` (
    `score_id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `driver_id`         INT UNSIGNED NOT NULL,
    `trip_id`           INT UNSIGNED NULL,
    `score`             TINYINT UNSIGNED NOT NULL,
    `previous_score`    TINYINT UNSIGNED NULL,
    `violation_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `reason`            VARCHAR(255) NULL,
    `calculated_at`     DATETIME NOT NULL,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`score_id`),
    KEY `idx_scores_driver`      (`driver_id`),
    KEY `idx_scores_trip`        (`trip_id`),
    KEY `idx_scores_calculated`  (`calculated_at`),

    CONSTRAINT `fk_scores_driver`
        FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_scores_trip`
        FOREIGN KEY (`trip_id`) REFERENCES `trips` (`trip_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- END OF PART 3B
-- ============================================================