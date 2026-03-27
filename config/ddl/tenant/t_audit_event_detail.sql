CREATE TABLE IF NOT EXISTS t_audit_event_detail (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '監査イベント詳細ID',
  audit_event_id      BIGINT UNSIGNED NOT NULL COMMENT '監査イベントID(t_audit_event.id)',
  field_key           VARCHAR(100)    NOT NULL COMMENT '項目キー',
  field_label         VARCHAR(100)    NULL COMMENT '項目名',
  value_type          VARCHAR(20)     NOT NULL COMMENT '値型(STRING/NUMBER/DATE/DATETIME/BOOLEAN/JSON/NULL)',
  before_value_text   TEXT            NULL COMMENT '変更前テキスト値',
  after_value_text    TEXT            NULL COMMENT '変更後テキスト値',
  before_value_json   LONGTEXT        NULL COMMENT '変更前JSON文字列',
  after_value_json    LONGTEXT        NULL COMMENT '変更後JSON文字列',

  PRIMARY KEY (id),
  UNIQUE KEY uq_t_audit_event_detail_01 (audit_event_id, field_key),
  KEY idx_t_audit_event_detail_01 (audit_event_id),
  CONSTRAINT fk_t_audit_event_detail_01
    FOREIGN KEY (audit_event_id) REFERENCES t_audit_event(id),
  CONSTRAINT chk_t_audit_event_detail_01 CHECK (value_type IN ('STRING', 'NUMBER', 'DATE', 'DATETIME', 'BOOLEAN', 'JSON', 'NULL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='監査イベント詳細';