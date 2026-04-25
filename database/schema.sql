-- Privacy-first compatibility platform MVP schema
-- MySQL / MariaDB compatible (InnoDB, utf8mb4)
-- Shared-hosting friendly: PHP + PDO + cron + AJAX polling

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- Localization and language infrastructure (Persian-first, bilingual-ready)
-- ---------------------------------------------------------------------------

CREATE TABLE locales (
  code VARCHAR(10) PRIMARY KEY COMMENT 'e.g. fa, en',
  native_name VARCHAR(80) NOT NULL,
  direction ENUM('rtl','ltr') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_locales_active_default (is_active, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE i18n_texts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  namespace VARCHAR(60) NOT NULL COMMENT 'goals, notifications, ui, empty_states, etc.',
  text_key VARCHAR(120) NOT NULL,
  locale_code VARCHAR(10) NOT NULL,
  text_value TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_i18n_texts_locale FOREIGN KEY (locale_code) REFERENCES locales(code) ON DELETE CASCADE,
  UNIQUE KEY uq_i18n_texts_lookup (namespace, text_key, locale_code),
  KEY idx_i18n_texts_locale (locale_code, namespace)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Users and profile baseline
-- ---------------------------------------------------------------------------

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  preferred_locale VARCHAR(10) NOT NULL DEFAULT 'fa',
  ui_direction ENUM('rtl','ltr','auto') NOT NULL DEFAULT 'rtl',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_preferred_locale FOREIGN KEY (preferred_locale) REFERENCES locales(code) ON DELETE RESTRICT,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status_created (status, created_at),
  KEY idx_users_locale_direction (preferred_locale, ui_direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  birth_year SMALLINT UNSIGNED NOT NULL COMMENT 'Less sensitive alternative to full birth date',
  age_min_pref TINYINT UNSIGNED NULL,
  age_max_pref TINYINT UNSIGNED NULL,
  gender_identity VARCHAR(50) NULL,
  interested_in_gender VARCHAR(100) NULL,
  about_me TEXT NULL,
  looking_for TEXT NULL,
  social_energy TINYINT UNSIGNED NULL COMMENT '1-5',
  communication_style TINYINT UNSIGNED NULL COMMENT '1-5',
  emotional_openness TINYINT UNSIGNED NULL COMMENT '1-5',
  relationship_pace TINYINT UNSIGNED NULL COMMENT '1-5',
  independence_level TINYINT UNSIGNED NULL COMMENT '1-5',
  boundary_sensitivity TINYINT UNSIGNED NULL COMMENT '1-5',
  structure_vs_spontaneity TINYINT UNSIGNED NULL COMMENT '1-5',
  smoking_preference ENUM('no','yes','occasionally','prefer_not') NULL,
  drinking_preference ENUM('no','yes','occasionally','prefer_not') NULL,
  activity_level ENUM('low','moderate','high') NULL,
  diet_style VARCHAR(50) NULL,

  -- Approximate, privacy-preserving location model (no exact lat/lng)
  country_code CHAR(2) NULL,
  region_code VARCHAR(32) NULL COMMENT 'Province/state/county code (coarse)',
  location_cell_l5 VARCHAR(16) NULL COMMENT 'Coarse geocell/geohash-like token',
  location_cell_l4 VARCHAR(16) NULL COMMENT 'Broader nearby bucket for fallback matching',
  distance_radius_km SMALLINT UNSIGNED NOT NULL DEFAULT 30,

  profile_completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_profiles_user (user_id),
  KEY idx_profiles_location_l5 (country_code, region_code, location_cell_l5),
  KEY idx_profiles_location_l4 (country_code, region_code, location_cell_l4),
  KEY idx_profiles_radius (distance_radius_km),
  KEY idx_profiles_age_pref (birth_year, age_min_pref, age_max_pref),
  KEY idx_profiles_dimensions (
    social_energy,
    communication_style,
    emotional_openness,
    relationship_pace,
    independence_level,
    boundary_sensitivity,
    structure_vs_spontaneity
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profile_boundaries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  boundary_key VARCHAR(80) NOT NULL,
  boundary_value VARCHAR(255) NOT NULL,
  importance ENUM('required','preferred','avoid') NOT NULL DEFAULT 'preferred',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_boundaries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_profile_boundary (user_id, boundary_key),
  KEY idx_profile_boundaries_key_value (boundary_key, boundary_value, importance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dedicated reveal-only profile data (not exposed by default)
CREATE TABLE revealable_profile_data (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  first_name VARCHAR(80) NULL,
  photo_media_key VARCHAR(255) NULL COMMENT 'Storage key/path, not public URL',
  contact_payload_json JSON NULL COMMENT 'Encrypted/structured contact payload at app layer',
  deep_profile_payload_json JSON NULL COMMENT 'Long-form profile details for stage 3',
  first_name_reveal_stage ENUM('stage_1','stage_2','stage_3') NOT NULL DEFAULT 'stage_2',
  photo_reveal_stage ENUM('stage_1','stage_2','stage_3') NOT NULL DEFAULT 'stage_2',
  contact_reveal_stage ENUM('stage_1','stage_2','stage_3') NOT NULL DEFAULT 'stage_3',
  deep_profile_reveal_stage ENUM('stage_1','stage_2','stage_3') NOT NULL DEFAULT 'stage_3',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_revealable_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_revealable_profile_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE availability_slots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday..6=Saturday',
  start_minute SMALLINT UNSIGNED NOT NULL COMMENT 'Minutes from 00:00',
  end_minute SMALLINT UNSIGNED NOT NULL,
  timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Tehran',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_availability_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CHECK (weekday <= 6),
  CHECK (start_minute < end_minute),
  CHECK (end_minute <= 1440),
  KEY idx_availability_user_weekday (user_id, weekday),
  KEY idx_availability_weekday_time (weekday, start_minute, end_minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Goals and goal-specific dynamic definitions
-- ---------------------------------------------------------------------------

CREATE TABLE goals (
  id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL,
  title_key VARCHAR(120) NOT NULL,
  description_key VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_goals_slug (slug),
  UNIQUE KEY uq_goals_title_key (title_key),
  UNIQUE KEY uq_goals_desc_key (description_key),
  KEY idx_goals_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_goals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  goal_id SMALLINT UNSIGNED NOT NULL,
  priority TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_goals_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_user_goal (user_id, goal_id),
  KEY idx_user_goals_goal_status (goal_id, status),
  KEY idx_user_goals_user_status_priority (user_id, status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE goal_preference_definitions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id SMALLINT UNSIGNED NOT NULL,
  pref_key VARCHAR(80) NOT NULL,
  label_key VARCHAR(120) NOT NULL,
  helper_text_key VARCHAR(120) NULL,
  input_type ENUM('select','multiselect','range','number','boolean','text') NOT NULL,
  value_type ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
  allowed_values_json JSON NULL,
  min_value DECIMAL(10,2) NULL,
  max_value DECIMAL(10,2) NULL,
  weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_goal_pref_defs_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
  UNIQUE KEY uq_goal_pref_def (goal_id, pref_key),
  UNIQUE KEY uq_goal_pref_label_key (label_key),
  KEY idx_goal_pref_defs_goal_active (goal_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_goal_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_goal_id BIGINT UNSIGNED NOT NULL,
  preference_def_id INT UNSIGNED NOT NULL,
  value_string VARCHAR(255) NULL,
  value_number DECIMAL(10,2) NULL,
  value_bool TINYINT(1) NULL,
  value_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_goal_prefs_user_goal FOREIGN KEY (user_goal_id) REFERENCES user_goals(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_goal_prefs_def FOREIGN KEY (preference_def_id) REFERENCES goal_preference_definitions(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_goal_pref (user_goal_id, preference_def_id),
  KEY idx_user_goal_pref_lookup (preference_def_id, value_string, value_number, value_bool)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE goal_attribute_definitions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  goal_id SMALLINT UNSIGNED NOT NULL,
  attr_key VARCHAR(80) NOT NULL,
  label_key VARCHAR(120) NOT NULL,
  helper_text_key VARCHAR(120) NULL,
  value_type ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  is_revealable TINYINT(1) NOT NULL DEFAULT 1,
  reveal_stage ENUM('stage_1','stage_2','stage_3') NOT NULL DEFAULT 'stage_2',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_goal_attr_defs_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
  UNIQUE KEY uq_goal_attr_def (goal_id, attr_key),
  UNIQUE KEY uq_goal_attr_label_key (label_key),
  KEY idx_goal_attr_defs_goal_active (goal_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_goal_attributes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_goal_id BIGINT UNSIGNED NOT NULL,
  attribute_def_id INT UNSIGNED NOT NULL,
  value_string VARCHAR(255) NULL,
  value_number DECIMAL(10,2) NULL,
  value_bool TINYINT(1) NULL,
  value_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_goal_attrs_user_goal FOREIGN KEY (user_goal_id) REFERENCES user_goals(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_goal_attrs_def FOREIGN KEY (attribute_def_id) REFERENCES goal_attribute_definitions(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_goal_attr (user_goal_id, attribute_def_id),
  KEY idx_user_goal_attr_lookup (attribute_def_id, value_string, value_number, value_bool)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Matching pipeline and anonymous cards
-- ---------------------------------------------------------------------------

CREATE TABLE match_candidate_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  candidate_user_id BIGINT UNSIGNED NOT NULL,
  goal_id SMALLINT UNSIGNED NOT NULL,
  hard_filter_passed TINYINT(1) NOT NULL DEFAULT 0,
  compatibility_score DECIMAL(5,2) NULL,
  score_breakdown_json JSON NULL,
  rejection_reason_code VARCHAR(60) NULL,
  status ENUM('queued','scored','rejected','presented','expired') NOT NULL DEFAULT 'queued',
  queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  expires_at DATETIME NULL,
  CONSTRAINT fk_match_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_queue_candidate FOREIGN KEY (candidate_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_queue_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE RESTRICT,
  CHECK (user_id <> candidate_user_id),
  UNIQUE KEY uq_match_queue_pair_goal (user_id, candidate_user_id, goal_id),
  KEY idx_match_queue_status_user (status, user_id, goal_id),
  KEY idx_match_queue_score (user_id, goal_id, hard_filter_passed, compatibility_score),
  KEY idx_match_queue_expiry (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_a_id BIGINT UNSIGNED NOT NULL,
  user_b_id BIGINT UNSIGNED NOT NULL,
  goal_id SMALLINT UNSIGNED NOT NULL,
  score_a_to_b DECIMAL(5,2) NOT NULL,
  score_b_to_a DECIMAL(5,2) NOT NULL,
  mutual_score DECIMAL(5,2) NOT NULL,
  explanation_json JSON NOT NULL,
  status ENUM('suggested','interested_one_side','mutual','chat_open','closed') NOT NULL DEFAULT 'suggested',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  chat_opened_at DATETIME NULL,
  closed_at DATETIME NULL,
  closed_reason_code VARCHAR(60) NULL,
  CONSTRAINT fk_matches_user_a FOREIGN KEY (user_a_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_user_b FOREIGN KEY (user_b_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE RESTRICT,
  CHECK (user_a_id < user_b_id),
  UNIQUE KEY uq_matches_pair_goal (user_a_id, user_b_id, goal_id),
  KEY idx_matches_user_a_status (user_a_id, status, updated_at),
  KEY idx_matches_user_b_status (user_b_id, status, updated_at),
  KEY idx_matches_goal_status_score (goal_id, status, mutual_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Materialized current interest state (event-log remains in match_interest_actions)
CREATE TABLE match_interest_states (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  current_interest ENUM('none','interested','passed') NOT NULL DEFAULT 'none',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_interest_state_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_interest_state_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_match_interest_state (match_id, user_id),
  KEY idx_match_interest_state_interest (current_interest, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE match_interest_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  action ENUM('interested','pass','undo') NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_interest_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_interest_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_interest_match_actor_created (match_id, actor_user_id, created_at),
  KEY idx_interest_actor_created (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE match_cards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  viewer_user_id BIGINT UNSIGNED NOT NULL,
  age_range_label_key VARCHAR(120) NOT NULL,
  approx_distance_bucket VARCHAR(40) NULL COMMENT 'e.g. under_5km, between_5_20km',
  compatibility_score DECIMAL(5,2) NOT NULL,
  emotional_summary_key VARCHAR(120) NULL,
  match_reasons_json JSON NOT NULL,
  communication_boundaries_json JSON NULL,
  schedule_overlap_key VARCHAR(120) NULL,
  card_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_match_cards_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_cards_viewer FOREIGN KEY (viewer_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_match_cards_match_viewer (match_id, viewer_user_id),
  KEY idx_match_cards_viewer_created (viewer_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Chat and messaging
-- ---------------------------------------------------------------------------

CREATE TABLE chats (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  closed_by_user_id BIGINT UNSIGNED NULL,
  close_reason_code VARCHAR(60) NULL,
  CONSTRAINT fk_chats_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_chats_closed_by FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  UNIQUE KEY uq_chats_match (match_id),
  KEY idx_chats_status_opened (status, opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chat_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  message_body TEXT NOT NULL,
  message_type ENUM('text','prompt','system') NOT NULL DEFAULT 'text',
  metadata_json JSON NULL COMMENT 'Future moderation/prompt metadata',
  moderation_state ENUM('clean','flagged','hidden') NOT NULL DEFAULT 'clean',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_messages_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_messages_chat_created (chat_id, created_at, id),
  KEY idx_messages_sender_created (sender_user_id, created_at),
  KEY idx_messages_moderation (moderation_state, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Progressive reveal and consents
-- ---------------------------------------------------------------------------

CREATE TABLE reveal_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  reveal_type ENUM('first_name','photo','contact_info','deep_profile') NOT NULL,
  stage_required ENUM('stage_1','stage_2','stage_3') NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  status ENUM('pending','accepted','declined','expired','cancelled') NOT NULL DEFAULT 'pending',
  resolved_at DATETIME NULL,
  CONSTRAINT fk_reveal_requests_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_reveal_requests_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_reveal_requests_match_type (match_id, reveal_type, status),
  KEY idx_reveal_requests_requester (requested_by_user_id, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reveal_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reveal_request_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  consent_status ENUM('accepted','declined') NOT NULL,
  consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reveal_consents_request FOREIGN KEY (reveal_request_id) REFERENCES reveal_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_reveal_consents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_reveal_consents_request_user (reveal_request_id, user_id),
  KEY idx_reveal_consents_user (user_id, consented_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Safety, notifications, empty-state handling, closure feedback
-- ---------------------------------------------------------------------------

CREATE TABLE reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reported_by_user_id BIGINT UNSIGNED NOT NULL,
  reported_user_id BIGINT UNSIGNED NOT NULL,
  match_id BIGINT UNSIGNED NULL,
  chat_id BIGINT UNSIGNED NULL,
  reason_code VARCHAR(60) NOT NULL,
  details TEXT NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  resolution_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_reports_reported_by FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_reported_user FOREIGN KEY (reported_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL,
  CONSTRAINT fk_reports_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE SET NULL,
  KEY idx_reports_status_created (status, created_at),
  KEY idx_reports_reported_user (reported_user_id, created_at),
  KEY idx_reports_reported_by (reported_by_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blocker_user_id BIGINT UNSIGNED NOT NULL,
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  source ENUM('manual','report_resolution','safety_auto') NOT NULL DEFAULT 'manual',
  reason_code VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_blocks_blocker FOREIGN KEY (blocker_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CHECK (blocker_user_id <> blocked_user_id),
  UNIQUE KEY uq_blocks_pair (blocker_user_id, blocked_user_id),
  KEY idx_blocks_blocked (blocked_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(120) NOT NULL,
  category ENUM('match','chat','reveal','safety','system','empty_state') NOT NULL,
  title_text_key VARCHAR(120) NOT NULL,
  body_text_key VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notification_template_key (template_key),
  UNIQUE KEY uq_notification_title_key (title_text_key),
  UNIQUE KEY uq_notification_body_key (body_text_key),
  KEY idx_notification_templates_category (category, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  category ENUM('match','chat','reveal','safety','system','empty_state') NOT NULL,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  email_enabled TINYINT(1) NOT NULL DEFAULT 0,
  quiet_hours_start TIME NULL,
  quiet_hours_end TIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_notification_prefs_user_category (user_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  payload_json JSON NULL COMMENT 'Interpolation values for localized message rendering',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  send_after DATETIME NULL,
  delivered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_template FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE RESTRICT,
  KEY idx_notifications_user_read_created (user_id, is_read, created_at),
  KEY idx_notifications_delivery (delivered_at, send_after, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE no_match_states (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  goal_id SMALLINT UNSIGNED NULL,
  goal_scope_key SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  state ENUM('searching','expand_preferences_suggested','profile_improvement_suggested','notified_waiting') NOT NULL DEFAULT 'searching',
  context_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  next_recheck_at DATETIME NULL,
  notify_on_strong_match TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_no_match_states_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_no_match_states_goal FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE SET NULL,
  -- Note: with MySQL/MariaDB this unique key allows at most one active and at most one
  -- inactive row per (user_id, goal_scope_key). Application logic prunes old inactive rows
  -- before deactivation to avoid duplicate-key errors.
  UNIQUE KEY uq_no_match_active_scope (user_id, goal_scope_key, is_active),
  KEY idx_no_match_recheck (state, is_active, next_recheck_at),
  KEY idx_no_match_notify (notify_on_strong_match, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE closure_feedback (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  chat_id BIGINT UNSIGNED NULL,
  submitted_by_user_id BIGINT UNSIGNED NOT NULL,
  reason_code ENUM('timing_mismatch','different_goals','different_pace','no_compatibility_felt','other') NOT NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_closure_feedback_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_closure_feedback_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE SET NULL,
  CONSTRAINT fk_closure_feedback_user FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_closure_feedback_match_user (match_id, submitted_by_user_id),
  KEY idx_closure_feedback_reason (reason_code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed locale + translatable text keys + goals (key-based, not hardcoded UI)
-- ---------------------------------------------------------------------------

INSERT INTO locales (code, native_name, direction, is_active, is_default) VALUES
('fa','فارسی','rtl',1,1),
('en','English','ltr',1,0)
ON DUPLICATE KEY UPDATE
  native_name = VALUES(native_name),
  direction = VALUES(direction),
  is_active = VALUES(is_active),
  is_default = VALUES(is_default);

INSERT INTO goals (slug, title_key, description_key, is_active, sort_order) VALUES
('travel_companion','goals.travel_companion.title','goals.travel_companion.description',1,10),
('friendly_conversation','goals.friendly_conversation.title','goals.friendly_conversation.description',1,20),
('emotional_connection','goals.emotional_connection.title','goals.emotional_connection.description',1,30),
('long_term_relationship','goals.long_term_relationship.title','goals.long_term_relationship.description',1,40),
('social_activity_partner','goals.social_activity_partner.title','goals.social_activity_partner.description',1,50),
('event_companion','goals.event_companion.title','goals.event_companion.description',1,60),
('sports_companion','goals.sports_companion.title','goals.sports_companion.description',1,70),
('project_collaboration','goals.project_collaboration.title','goals.project_collaboration.description',1,80),
('co_living','goals.co_living.title','goals.co_living.description',1,90),
('casual_connection','goals.casual_connection.title','goals.casual_connection.description',1,100),
('personal_growth_connection','goals.personal_growth_connection.title','goals.personal_growth_connection.description',1,110)
ON DUPLICATE KEY UPDATE
  title_key = VALUES(title_key),
  description_key = VALUES(description_key),
  is_active = VALUES(is_active),
  sort_order = VALUES(sort_order);

INSERT INTO i18n_texts (namespace, text_key, locale_code, text_value) VALUES
('goals','goals.travel_companion.title','fa','همسفر سفر'),
('goals','goals.travel_companion.description','fa','یافتن فردی سازگار برای برنامه‌ریزی سفر'),
('goals','goals.friendly_conversation.title','fa','گفت‌وگوی دوستانه'),
('goals','goals.friendly_conversation.description','fa','گفت‌وگویی آرام و بدون فشار'),
('goals','goals.emotional_connection.title','fa','ارتباط احساسی'),
('goals','goals.emotional_connection.description','fa','ارتباطی مبتنی بر درک و حمایت متقابل'),
('goals','goals.long_term_relationship.title','fa','رابطه بلندمدت'),
('goals','goals.long_term_relationship.description','fa','آشنایی هدفمند برای رابطه پایدار'),
('goals','goals.travel_companion.title','en','Travel companion'),
('goals','goals.travel_companion.description','en','Find someone compatible for planning trips')
ON DUPLICATE KEY UPDATE text_value = VALUES(text_value);
