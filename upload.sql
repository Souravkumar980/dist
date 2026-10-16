-- ===================================================================
-- EXPOSED PROPAGANDUS - Official MySQL Database Schema (Zero Pre-Written Data)
-- File: upload.sql
-- Target Database: if0_37976074_exposedpropagandus
-- Host: sql200.infinityfree.com (or local MySQL / phpMyAdmin import)
-- Bharat Mata ki Jai 🇮🇳
-- All tables start completely at 0 / empty (Admin Credentials only)
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

-- -------------------------------------------------------------
-- Table: entities (Tracked Propagandus Profiles) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `entities`;
CREATE TABLE `entities` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `handle_x` VARCHAR(100) DEFAULT NULL,
  `handle_yt` VARCHAR(100) DEFAULT NULL,
  `handle_insta` VARCHAR(100) DEFAULT NULL,
  `contact_phone` VARCHAR(50) DEFAULT NULL,
  `contact_email` VARCHAR(255) DEFAULT NULL,
  `other_links` TEXT DEFAULT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `categories` TEXT NOT NULL,
  `party` VARCHAR(255) DEFAULT NULL,
  `institution` VARCHAR(255) DEFAULT NULL,
  `narrative` TEXT NOT NULL,
  `fact_check` TEXT DEFAULT NULL,
  `proof_url` TEXT DEFAULT NULL,
  `upvotes` INT NOT NULL DEFAULT 0,
  `rating_avg` FLOAT NOT NULL DEFAULT 0,
  `rating_count` INT NOT NULL DEFAULT 0,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `submitted_by_ip` VARCHAR(64) DEFAULT NULL,
  `created_at` VARCHAR(64) NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_entities_upvotes` (`upvotes`),
  INDEX `idx_entities_rating` (`rating_avg`),
  INDEX `idx_entities_party` (`party`),
  INDEX `idx_entities_institution` (`institution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: nationalist_influencers (Nationalist Creators) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `nationalist_influencers`;
CREATE TABLE `nationalist_influencers` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `handle_x` VARCHAR(100) DEFAULT NULL,
  `handle_yt` VARCHAR(100) DEFAULT NULL,
  `handle_insta` VARCHAR(100) DEFAULT NULL,
  `other_links` TEXT DEFAULT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `category` VARCHAR(150) DEFAULT 'Nationalist Creator',
  `description` TEXT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `upvotes` INT NOT NULL DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_nat_status` (`status`),
  INDEX `idx_nat_upvotes` (`upvotes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: submissions (Crowdsourced Submissions) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `submissions`;
CREATE TABLE `submissions` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `handle_x` VARCHAR(100) DEFAULT NULL,
  `handle_yt` VARCHAR(100) DEFAULT NULL,
  `handle_insta` VARCHAR(100) DEFAULT NULL,
  `categories` TEXT NOT NULL,
  `party` VARCHAR(255) DEFAULT NULL,
  `institution` VARCHAR(255) DEFAULT NULL,
  `narrative` TEXT NOT NULL,
  `proof_url` TEXT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `submitted_by_ip` VARCHAR(64) DEFAULT NULL,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_submissions_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: reports (User Reports) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` VARCHAR(100) NOT NULL,
  `entity_id` VARCHAR(100) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `reported_by_ip` VARCHAR(64) DEFAULT NULL,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_reports_entity` (`entity_id`),
  INDEX `idx_reports_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: feature_suggestions (User Suggestions to Admin) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `feature_suggestions`;
CREATE TABLE `feature_suggestions` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) DEFAULT 'New Feature',
  `message` TEXT NOT NULL,
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_sug_read` (`is_read`),
  INDEX `idx_sug_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: votes (1 Upvote per IP) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `votes`;
CREATE TABLE `votes` (
  `entity_id` VARCHAR(100) NOT NULL,
  `ip` VARCHAR(64) NOT NULL,
  `voted_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`entity_id`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: nationalist_votes (1 Upvote per IP) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `nationalist_votes`;
CREATE TABLE `nationalist_votes` (
  `influencer_id` VARCHAR(100) NOT NULL,
  `ip` VARCHAR(64) NOT NULL,
  `voted_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`influencer_id`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: govt_polls (2029 Citizen Mandate Poll) - Starts at 0 Votes
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `govt_polls`;
CREATE TABLE `govt_polls` (
  `id` VARCHAR(50) NOT NULL,
  `question` TEXT NOT NULL,
  `yes_votes` INT NOT NULL DEFAULT 0,
  `no_votes` INT NOT NULL DEFAULT 0,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `govt_polls` (`id`, `question`, `yes_votes`, `no_votes`, `updated_at`)
VALUES ('bjp_2029', 'Do you want BJP / NDA back in 2029?', 0, 0, '2026-10-16T00:00:00.000Z')
ON DUPLICATE KEY UPDATE `yes_votes` = 0, `no_votes` = 0, `updated_at` = VALUES(`updated_at`);

-- -------------------------------------------------------------
-- Table: govt_poll_votes (1 Vote per IP) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `govt_poll_votes`;
CREATE TABLE `govt_poll_votes` (
  `poll_id` VARCHAR(50) NOT NULL,
  `ip` VARCHAR(64) NOT NULL,
  `choice` VARCHAR(10) NOT NULL,
  `voted_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`poll_id`, `ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: system_settings (System Configuration) - Clean initial state
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`key`, `value`, `updated_at`)
VALUES 
  ('spotlight_media', '{"type":"off","title":"","videoUrl":"","channelName":"","channelUrl":"","imageUrl":"","imageCaption":""}', '2026-10-16T00:00:00.000Z'),
  ('nationalist_bulletin', '{"is_active":0,"title":"","message":"","link":""}', '2026-10-16T00:00:00.000Z')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- -------------------------------------------------------------
-- Table: admin_users (Super Admin Credentials)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` VARCHAR(64) NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_users` (`id`, `email`, `password_hash`, `created_at`, `updated_at`)
VALUES ('admin-primary', 'omkumar.working@gmail.com', 'Q0gng04bk3', '2026-10-16T00:00:00.000Z', '2026-10-16T00:00:00.000Z')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`), `password_hash` = VALUES(`password_hash`);

-- -------------------------------------------------------------
-- Table: playlist_tracks (Music & Audio Tracks) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `playlist_tracks`;
CREATE TABLE `playlist_tracks` (
  `id` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `video_id` VARCHAR(64) NOT NULL,
  `author` VARCHAR(255) DEFAULT NULL,
  `thumbnail_url` TEXT DEFAULT NULL,
  `duration` VARCHAR(50) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: site_categories (Categories for Directory)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `site_categories`;
CREATE TABLE `site_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `created_at` VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: admin_posts (Admin Bulletins & Dispatches) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `admin_posts`;
CREATE TABLE `admin_posts` (
  `id` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `image_url` TEXT DEFAULT NULL,
  `badge` VARCHAR(100) DEFAULT 'DISPATCH',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `likes` INT NOT NULL DEFAULT 0,
  `views` INT NOT NULL DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: comparisons (Comparison Cards) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `comparisons`;
CREATE TABLE `comparisons` (
  `id` VARCHAR(50) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `upa_period` VARCHAR(100) DEFAULT '2004 - 2014 (UPA)',
  `upa_value` VARCHAR(255) NOT NULL,
  `upa_details` TEXT,
  `nda_period` VARCHAR(100) DEFAULT '2014 - Present (NDA)',
  `nda_value` VARCHAR(255) NOT NULL,
  `nda_details` TEXT,
  `growth_badge` VARCHAR(100) DEFAULT NULL,
  `source` VARCHAR(255) DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: community_users (Patriotic Community Users)
-- Only Master Admin Om Kumar pre-seeded so admin can manage
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `community_users`;
CREATE TABLE `community_users` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(50) DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `verified_type` VARCHAR(50) NOT NULL DEFAULT 'none',
  `ip_address` VARCHAR(64) DEFAULT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
  `name_change_count` INT NOT NULL DEFAULT 0,
  `username_change_count` INT NOT NULL DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_comm_username` (`username`),
  UNIQUE KEY `idx_comm_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `community_users` (`id`, `name`, `username`, `email`, `phone`, `password_hash`, `avatar_url`, `verified_type`, `is_admin`, `is_banned`, `created_at`, `updated_at`)
VALUES ('usr_admin', 'Om Kumar (Admin)', 'omkumar_admin', 'omkumar.working@gmail.com', '9876543210', 'Q0gng04bk3', 'https://api.dicebear.com/7.x/bottts/svg?seed=OmKumarAdmin', 'influencer', 1, 0, '2026-10-16 00:00:00', '2026-10-16 00:00:00')
ON DUPLICATE KEY UPDATE `is_admin` = 1, `password_hash` = VALUES(`password_hash`);

-- -------------------------------------------------------------
-- Table: community_messages (Chat Messages) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `community_messages`;
CREATE TABLE `community_messages` (
  `id` VARCHAR(100) NOT NULL,
  `channel_id` VARCHAR(100) NOT NULL DEFAULT 'public',
  `user_id` VARCHAR(100) NOT NULL,
  `user_name` VARCHAR(255) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `user_avatar` TEXT DEFAULT NULL,
  `verified_type` VARCHAR(50) NOT NULL DEFAULT 'none',
  `content` TEXT NOT NULL,
  `attachments` MEDIUMTEXT DEFAULT NULL,
  `media_links` TEXT DEFAULT NULL,
  `gif_url` TEXT DEFAULT NULL,
  `reactions` TEXT DEFAULT NULL,
  `reply_to` TEXT DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_timestamp` BIGINT NOT NULL DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_comm_channel` (`channel_id`),
  INDEX `idx_comm_msg_user` (`user_id`),
  INDEX `idx_comm_msg_ts` (`created_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: propagandu_ratings (5-Star Wanted Level per IP)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `propagandu_ratings`;
CREATE TABLE `propagandu_ratings` (
  `id` VARCHAR(100) NOT NULL,
  `entity_id` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(64) NOT NULL,
  `rating` INT NOT NULL,
  `created_at` VARCHAR(64) NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_entity_ip` (`entity_id`, `ip_address`),
  INDEX `idx_rating_entity` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: community_channels (Private Channels) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `community_channels`;
CREATE TABLE `community_channels` (
  `id` VARCHAR(100) NOT NULL,
  `creator_id` VARCHAR(100) NOT NULL,
  `creator_name` VARCHAR(255) NOT NULL,
  `creator_username` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `join_token` VARCHAR(100) NOT NULL,
  `is_private` TINYINT(1) NOT NULL DEFAULT 1,
  `members` TEXT DEFAULT NULL,
  `pending_requests` TEXT DEFAULT NULL,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_comm_join_token` (`join_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: blocked_ips (Security Shield Blocklist) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `blocked_ips`;
CREATE TABLE `blocked_ips` (
  `ip` VARCHAR(64) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `blocked_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: traffic_logs (Real Visited Traffic Only) - Starts at 0
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `traffic_logs`;
CREATE TABLE `traffic_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip` VARCHAR(64) NOT NULL,
  `page` VARCHAR(255) NOT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `referrer` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `created_at` VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: site_visitors (Deduplicated Real Visitor Directory - 1 row per IP)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `site_visitors`;
CREATE TABLE `site_visitors` (
  `ip` VARCHAR(64) NOT NULL,
  `device_type` VARCHAR(50) DEFAULT 'Desktop',
  `os` VARCHAR(100) DEFAULT 'Unknown OS',
  `browser` VARCHAR(100) DEFAULT 'Unknown Browser',
  `user_agent` TEXT DEFAULT NULL,
  `visit_count` INT NOT NULL DEFAULT 1,
  `current_page` VARCHAR(120) DEFAULT 'Homepage',
  `first_visited` VARCHAR(64) NOT NULL,
  `last_seen` VARCHAR(64) NOT NULL,
  `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: visitor_traffic_logs (Real-Time Raw Session Hits)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `visitor_traffic_logs`;
CREATE TABLE `visitor_traffic_logs` (
  `id` VARCHAR(100) NOT NULL,
  `ip` VARCHAR(64) NOT NULL,
  `device_type` VARCHAR(50) DEFAULT 'Desktop',
  `os` VARCHAR(100) DEFAULT 'Unknown OS',
  `browser` VARCHAR(100) DEFAULT 'Unknown Browser',
  `page` VARCHAR(255) DEFAULT 'Homepage',
  `user_agent` TEXT DEFAULT NULL,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vtrf_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: activity_logs (Admin & Security Events)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip` VARCHAR(64) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: announcements (Global Broadcast Bulletin)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  `updated_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: community_contact_queries (Citizen Inquiries to Admin)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `community_contact_queries`;
CREATE TABLE `community_contact_queries` (
  `id` VARCHAR(100) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `user_id` VARCHAR(100) DEFAULT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `social_account_id` VARCHAR(255) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `proof_file_name` VARCHAR(255) DEFAULT NULL,
  `proof_file` MEDIUMTEXT DEFAULT NULL,
  `ip` VARCHAR(64) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: community_notifications (Chat & Channel Alerts)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `community_notifications`;
CREATE TABLE `community_notifications` (
  `id` VARCHAR(100) NOT NULL,
  `recipient_id` VARCHAR(100) NOT NULL,
  `sender_id` VARCHAR(100) NOT NULL,
  `sender_name` VARCHAR(255) NOT NULL,
  `sender_username` VARCHAR(100) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `content` TEXT NOT NULL,
  `channel_id` VARCHAR(100) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_recipient` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: community_channel_members (Channel Memberships)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `community_channel_members`;
CREATE TABLE `community_channel_members` (
  `channel_id` VARCHAR(100) NOT NULL,
  `user_id` VARCHAR(100) NOT NULL,
  `joined_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`channel_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: community_channel_requests (Private Channel Join Requests)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `community_channel_requests`;
CREATE TABLE `community_channel_requests` (
  `id` VARCHAR(100) NOT NULL,
  `channel_id` VARCHAR(100) NOT NULL,
  `user_id` VARCHAR(100) NOT NULL,
  `user_name` VARCHAR(255) NOT NULL,
  `user_username` VARCHAR(100) NOT NULL,
  `user_avatar` TEXT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `created_at` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

