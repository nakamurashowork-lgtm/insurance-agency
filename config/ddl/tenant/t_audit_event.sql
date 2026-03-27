CREATE TABLE IF NOT EXISTS t_audit_event (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '監査イベントID',
  entity_type         VARCHAR(30)     NOT NULL COMMENT '対象種別(customer/contract/renewal_case/accident_case/activity/sales_performance/comment)',
  entity_id           BIGINT UNSIGNED NOT NULL COMMENT '対象レコードID',
  action_type         VARCHAR(20)     NOT NULL COMMENT '操作種別(INSERT/UPDATE/DELETE/IMPORT/SYSTEM_UPDATE)',
  change_source       VARCHAR(20)     NOT NULL COMMENT '変更元(SCREEN/SJNET_IMPORT/BATCH/API)',
  changed_by          BIGINT UNSIGNED NOT NULL COMMENT '変更者(common.users.id)',
  changed_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '変更日時',
  request_id          VARCHAR(100)    NULL COMMENT 'リクエストID',
  note                VARCHAR(500)    NULL COMMENT '補足',

  PRIMARY KEY (id),
  KEY idx_t_audit_event_01 (entity_type, entity_id, changed_at),
  KEY idx_t_audit_event_02 (changed_by, changed_at),
  CONSTRAINT chk_t_audit_event_01 CHECK (action_type IN ('INSERT', 'UPDATE', 'DELETE', 'IMPORT', 'SYSTEM_UPDATE')),
  CONSTRAINT chk_t_audit_event_02 CHECK (change_source IN ('SCREEN', 'SJNET_IMPORT', 'BATCH', 'API'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='監査イベント';