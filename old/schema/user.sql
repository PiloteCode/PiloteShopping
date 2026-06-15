-- ============================================
-- TABLE: user_privacy_settings
-- Description: Paramètres de confidentialité des profils utilisateurs
-- ============================================

CREATE TABLE IF NOT EXISTS `user_privacy_settings` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `hide_full_name` tinyint(1) DEFAULT 0 COMMENT 'Masquer le prénom et nom (afficher uniquement le pseudo)',
  `hide_email` tinyint(1) DEFAULT 1 COMMENT 'Masquer l\'email du profil public',
  `hide_phone` tinyint(1) DEFAULT 1 COMMENT 'Masquer le téléphone du profil public',
  `hide_stats` tinyint(1) DEFAULT 0 COMMENT 'Masquer les statistiques (commandes, dépenses)',
  `hide_member_since` tinyint(1) DEFAULT 0 COMMENT 'Masquer la date d\'inscription',
  `hide_last_activity` tinyint(1) DEFAULT 0 COMMENT 'Masquer la dernière activité',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_privacy` (`user_id`),
  KEY `idx_user_privacy` (`user_id`),
  CONSTRAINT `fk_user_privacy_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paramètres de confidentialité des profils utilisateurs';

-- ============================================
-- INDEX pour optimiser les requêtes
-- ============================================

CREATE INDEX `idx_hide_full_name` ON `user_privacy_settings` (`hide_full_name`);
CREATE INDEX `idx_hide_stats` ON `user_privacy_settings` (`hide_stats`);
