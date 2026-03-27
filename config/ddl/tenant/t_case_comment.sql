CREATE TABLE IF NOT EXISTS t_case_comment (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'コメントID',
  target_type         VARCHAR(20)     NOT NULL COMMENT '対象種別(renewal_case/accident_case)',
  renewal_case_id     BIGINT UNSIGNED NULL COMMENT '満期案件ID(t_renewal_case.id)',
  accident_case_id    BIGINT UNSIGNED NULL COMMENT '事故案件ID(t_accident_case.id)',
  comment_body        TEXT            NOT NULL COMMENT 'コメント本文',
  is_deleted          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '削除フラグ',

  created_by          BIGINT UNSIGNED NOT NULL COMMENT '投稿者(common.users.id)',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '投稿日時',
  updated_by          BIGINT UNSIGNED NOT NULL COMMENT '最終更新者(common.users.id)',
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最終更新日時',

  PRIMARY KEY (id),
  KEY idx_t_case_comment_01 (renewal_case_id, created_at),
  KEY idx_t_case_comment_02 (accident_case_id, created_at),
  KEY idx_t_case_comment_03 (is_deleted),
  CONSTRAINT fk_t_case_comment_01
    FOREIGN KEY (renewal_case_id) REFERENCES t_renewal_case(id),
  CONSTRAINT fk_t_case_comment_02
    FOREIGN KEY (accident_case_id) REFERENCES t_accident_case(id),
  CONSTRAINT chk_t_case_comment_01 CHECK (
    (target_type = 'renewal_case' AND renewal_case_id IS NOT NULL AND accident_case_id IS NULL)
    OR
    (target_type = 'accident_case' AND accident_case_id IS NOT NULL AND renewal_case_id IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='案件コメント';