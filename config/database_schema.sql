-- Database Schema for Football Manager
-- File: config/database_schema.sql
-- Directory: /config/

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET
foreign_key_checks = 0;

-- Users table
CREATE TABLE `users`
(
    `id`                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trainer_name`              VARCHAR(50)  NOT NULL UNIQUE,
    `email`                     VARCHAR(255) NOT NULL UNIQUE,
    `password_hash`             VARCHAR(255) NOT NULL,
    `email_verified`            BOOLEAN      NOT NULL DEFAULT FALSE,
    `email_verification_token`  VARCHAR(64) NULL UNIQUE,
    `email_verified_at`         TIMESTAMP NULL,
    `status`                    ENUM('pending_verification', 'active', 'suspended', 'deleted') NOT NULL DEFAULT 'pending_verification',
    `password_reset_token`      VARCHAR(64) NULL UNIQUE,
    `password_reset_expires_at` TIMESTAMP NULL,
    `registration_ip`           VARCHAR(45)  NOT NULL,
    `user_agent`                TEXT NULL,
    `last_login_at`             TIMESTAMP NULL,
    `last_login_ip`             VARCHAR(45) NULL,
    `last_login_user_agent`     TEXT NULL,
    `created_at`                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`                TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX                       `idx_email` (`email`),
    INDEX                       `idx_trainer_name` (`trainer_name`),
    INDEX                       `idx_email_verification_token` (`email_verification_token`),
    INDEX                       `idx_password_reset_token` (`password_reset_token`),
    INDEX                       `idx_status` (`status`),
    INDEX                       `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leagues table
CREATE TABLE `leagues`
(
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `league_number`     INT UNSIGNED NOT NULL UNIQUE,
    `name`              VARCHAR(100) NOT NULL,
    `status`            TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=inactive, 1=active, 2=finished',
    `max_teams`         TINYINT UNSIGNED NOT NULL DEFAULT 18,
    `current_teams`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `season`            INT UNSIGNED NOT NULL DEFAULT 1,
    `season_start_date` TIMESTAMP NULL,
    `season_end_date`   TIMESTAMP NULL,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX               `idx_league_number` (`league_number`),
    INDEX               `idx_status` (`status`),
    INDEX               `idx_current_teams` (`current_teams`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teams table
CREATE TABLE `teams`
(
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,
    `league_id`         INT UNSIGNED NOT NULL,
    `name`              VARCHAR(50)  NOT NULL UNIQUE,
    `cash`              BIGINT       NOT NULL DEFAULT 10000000 COMMENT 'Money in Taler',
    `as_credits`        INT UNSIGNED NOT NULL DEFAULT 200 COMMENT 'A$ premium credits',
    `pitch_quality`     ENUM('kuhkoppel', 'normal', 'british') NOT NULL DEFAULT 'british',
    `standing_capacity` INT UNSIGNED NOT NULL DEFAULT 5000,
    `seating_capacity`  INT UNSIGNED NOT NULL DEFAULT 0,
    `vip_capacity`      INT UNSIGNED NOT NULL DEFAULT 0,
    `stadium_name`      VARCHAR(100) NOT NULL,
    `points`            INT          NOT NULL DEFAULT 0,
    `goals_for`         INT UNSIGNED NOT NULL DEFAULT 0,
    `goals_against`     INT UNSIGNED NOT NULL DEFAULT 0,
    `wins`              INT UNSIGNED NOT NULL DEFAULT 0,
    `draws`             INT UNSIGNED NOT NULL DEFAULT 0,
    `losses`            INT UNSIGNED NOT NULL DEFAULT 0,
    `founded_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    INDEX               `idx_league_id` (`league_id`),
    INDEX               `idx_name` (`name`),
    INDEX               `idx_points` (`points` DESC),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Players table
CREATE TABLE `players`
(
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `team_id`           INT UNSIGNED NOT NULL,
    `first_name`        VARCHAR(50) NOT NULL,
    `last_name`         VARCHAR(50) NOT NULL,
    `position`          ENUM('GK', 'LB', 'LWB', 'RB', 'RWB', 'CB', 'LM', 'RM', 'CAM', 'CDM', 'LW', 'RW', 'ST') NOT NULL,
    `age`               TINYINT UNSIGNED NOT NULL,
    `strength`          TINYINT UNSIGNED NOT NULL COMMENT 'Overall strength 5-7',
    `condition`         TINYINT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Fitness level 0-100',
    `form`              TINYINT UNSIGNED NOT NULL DEFAULT 20 COMMENT 'Current form 0-100',
    `freshness`         TINYINT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Match freshness 0-100',
    `motivation`        TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Player motivation 0-100',
    `contract_duration` TINYINT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'Seasons remaining',
    `salary`            INT UNSIGNED NOT NULL COMMENT 'Weekly salary in Taler',
    `market_value`      BIGINT UNSIGNED NOT NULL COMMENT 'Current market value in Taler',
    `yellow_cards`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `red_cards`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `yellow_red_cards`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `appearances`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `goals`             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `assists`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`            ENUM('ok', 'injured', 'suspended') NOT NULL DEFAULT 'ok',
    `injury_days`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `suspension_games`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX               `idx_team_id` (`team_id`),
    INDEX               `idx_position` (`position`),
    INDEX               `idx_strength` (`strength`),
    INDEX               `idx_status` (`status`),
    INDEX               `idx_market_value` (`market_value` DESC),
    FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting table
CREATE TABLE `rate_limits`
(
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identifier` VARCHAR(255) NOT NULL COMMENT 'IP address or user identifier',
    `action`     VARCHAR(100) NOT NULL COMMENT 'Action being rate limited',
    `attempts`   INT UNSIGNED NOT NULL DEFAULT 1,
    `reset_time` TIMESTAMP    NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_identifier_action` (`identifier`, `action`),
    INDEX        `idx_reset_time` (`reset_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue table
CREATE TABLE `email_queue`
(
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `to_email`      VARCHAR(255) NOT NULL,
    `to_name`       VARCHAR(255) NOT NULL,
    `subject`       VARCHAR(255) NOT NULL,
    `template`      VARCHAR(100) NOT NULL,
    `template_data` JSON NULL,
    `priority`      TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
    `status`        ENUM('pending', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `error_message` TEXT NULL,
    `scheduled_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at`       TIMESTAMP NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX           `idx_status_priority` (`status`, `priority`),
    INDEX           `idx_scheduled_at` (`scheduled_at`),
    INDEX           `idx_to_email` (`to_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Game settings table
CREATE TABLE `game_settings`
(
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` JSON         NOT NULL,
    `description`   TEXT NULL,
    `updated_by`    INT UNSIGNED NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX           `idx_setting_key` (`setting_key`),
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log table
CREATE TABLE `audit_logs`
(
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NULL,
    `action`         VARCHAR(100) NOT NULL,
    `resource_type`  VARCHAR(100) NOT NULL,
    `resource_id`    INT UNSIGNED NULL,
    `old_values`     JSON NULL,
    `new_values`     JSON NULL,
    `ip_address`     VARCHAR(45)  NOT NULL,
    `user_agent`     TEXT NULL,
    `correlation_id` VARCHAR(50) NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX            `idx_user_id` (`user_id`),
    INDEX            `idx_action` (`action`),
    INDEX            `idx_resource` (`resource_type`, `resource_id`),
    INDEX            `idx_correlation_id` (`correlation_id`),
    INDEX            `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default league
INSERT INTO `leagues` (`league_number`, `name`, `status`, `max_teams`, `current_teams`, `season`)
VALUES (1, 'Liga 1', 0, 18, 0, 1);

-- Insert default game settings
INSERT INTO `game_settings` (`setting_key`, `setting_value`, `description`)
VALUES ('registration_enabled', 'true', 'Whether new user registration is enabled'),
       ('max_teams_per_league', '18', 'Maximum number of teams per league'),
       ('starting_cash', '10000000', 'Starting cash for new teams in Taler'),
       ('starting_as_credits', '200', 'Starting A$ credits for new teams'),
       ('player_count_per_team', '20', 'Number of players generated per team'),
       ('default_contract_duration', '4', 'Default contract duration in seasons'),
       ('email_verification_required', 'true', 'Whether email verification is required'),
       ('team_name_blacklist_enabled', 'true', 'Whether team name blacklist is enforced');

-- Create indexes for better performance
CREATE INDEX `idx_users_email_verified` ON `users` (`email_verified`);
CREATE INDEX `idx_users_status_created` ON `users` (`status`, `created_at`);
CREATE INDEX `idx_teams_league_points` ON `teams` (`league_id`, `points` DESC);
CREATE INDEX `idx_players_team_position` ON `players` (`team_id`, `position`);
CREATE INDEX `idx_players_strength_age` ON `players` (`strength`, `age`);

SET
foreign_key_checks = 1;