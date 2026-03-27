CREATE TABLE IF NOT EXISTS t_sjnet_import_row (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'SJNET取込行ID',
  sjnet_import_batch_id BIGINT UNSIGNED NOT NULL COMMENT 'SJNET取込バッチID(t_sjnet_import_batch.id)',
  row_no              INT             NOT NULL COMMENT '行番号',
  raw_payload_json    LONGTEXT        NOT NULL COMMENT '元行データ(JSON文字列)',
  policy_no           VARCHAR(50)     NULL COMMENT '証券番号',
  customer_name       VARCHAR(200)    NULL COMMENT '契約者名',
  maturity_date       DATE            NULL COMMENT '満期日',
  matched_contract_id BIGINT UNSIGNED NULL COMMENT '紐づいた契約ID(t_contract.id)',
  matched_renewal_case_id BIGINT UNSIGNED NULL COMMENT '紐づいた満期案件ID(t_renewal_case.id)',
  row_status          VARCHAR(20)     NOT NULL COMMENT '行状態(insert/update/skip/error)',
  error_message       VARCHAR(1000)   NULL COMMENT 'エラーメッセージ',
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',

  PRIMARY KEY (id),
  UNIQUE KEY uq_t_sjnet_import_row_01 (sjnet_import_batch_id, row_no),
  KEY idx_t_sjnet_import_row_01 (policy_no),
  KEY idx_t_sjnet_import_row_02 (maturity_date),
  KEY idx_t_sjnet_import_row_03 (row_status),
  KEY idx_t_sjnet_import_row_04 (matched_contract_id),
  KEY idx_t_sjnet_import_row_05 (matched_renewal_case_id),
  CONSTRAINT fk_t_sjnet_import_row_01
    FOREIGN KEY (sjnet_import_batch_id) REFERENCES t_sjnet_import_batch(id),
  CONSTRAINT fk_t_sjnet_import_row_02
    FOREIGN KEY (matched_contract_id) REFERENCES t_contract(id),
  CONSTRAINT fk_t_sjnet_import_row_03
    FOREIGN KEY (matched_renewal_case_id) REFERENCES t_renewal_case(id),
  CONSTRAINT chk_t_sjnet_import_row_01 CHECK (row_status IN ('insert', 'update', 'skip', 'error'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SJNET取込行';