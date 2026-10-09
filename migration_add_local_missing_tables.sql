-- Add the tables present in the localhost dump but absent from the deployed dump.
-- Select the deployed LMS database before running this script.
-- This creates empty tables only; it does not insert localhost data or modify existing tables.

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `teacher_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ann_course` (`course_id`, `pinned`, `created_at`),
  KEY `fk_ann_teacher` (`teacher_id`),
  CONSTRAINT `fk_ann_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ann_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `teacher_id` int(10) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `instructions` text NOT NULL,
  `due_at` int(10) unsigned DEFAULT NULL,
  `max_points` int(10) unsigned NOT NULL DEFAULT 100,
  `allow_late` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_assign_course` (`course_id`),
  KEY `idx_assign_due` (`due_at`),
  KEY `fk_assign_teacher` (`teacher_id`),
  CONSTRAINT `fk_assign_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lesson_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL DEFAULT 0,
  `body` text NOT NULL,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_post_material` (`material_id`, `created_at`),
  KEY `fk_post_user` (`user_id`),
  CONSTRAINT `fk_post_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `body` text NOT NULL,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `orig_name` varchar(255) NOT NULL DEFAULT '',
  `mime` varchar(100) NOT NULL DEFAULT '',
  `size` int(10) unsigned NOT NULL DEFAULT 0,
  `submitted_at` int(10) unsigned NOT NULL DEFAULT 0,
  `grade` int(11) DEFAULT NULL,
  `feedback` text NOT NULL,
  `graded_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_once` (`assignment_id`, `user_id`),
  KEY `idx_sub_assign` (`assignment_id`),
  KEY `idx_sub_user` (`user_id`),
  CONSTRAINT `fk_sub_assign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
