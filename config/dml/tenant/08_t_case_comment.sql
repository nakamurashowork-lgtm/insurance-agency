-- =====================================================================
-- 動作確認用DML: t_case_comment
-- 用途: 満期案件・事故案件へのコメント
-- 件数: 30件（満期15件 + 事故15件）
-- ID範囲: 6001 - 6030
-- 依存: 04_t_renewal_case.sql, 05_t_accident_case.sql
-- 関連DDL: config/ddl/tenant/t_case_comment.sql
-- CHECK制約:
--   target_type='renewal_case'  → renewal_case_id IS NOT NULL, accident_case_id IS NULL
--   target_type='accident_case' → accident_case_id IS NOT NULL, renewal_case_id IS NULL
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 満期案件コメント（target_type='renewal_case'）==========

-- 6001: 満期案件3002（顧客1001, 自動車, 満期近し）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6001, 'renewal_case', 3002, NULL,
  '4/7 顧客に電話連絡。満期の件を伝えたところ「今年も続けたい」とのこと。見積もり準備中。',
  0, 1, 1
);

-- 6002: 満期案件3002（続き）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6002, 'renewal_case', 3002, NULL,
  '車両入替の可能性あり。新車購入検討中とのこと。詳細ヒアリング予定（4/12）。',
  0, 2, 2
);

-- 6003: 満期案件3004（顧客1001, 火災, SJ依頼済み）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6003, 'renewal_case', 3004, NULL,
  'SJ依頼書を4/5にメールで送付済み。折り返しの見積回答を待ち中。',
  0, 1, 1
);

-- 6004: 満期案件3012（顧客1001, 生命, quote_sent）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6004, 'renewal_case', 3012, NULL,
  '4/5 見積書をメールで送付。保険料据え置き予定。顧客の確認待ち。',
  0, 1, 1
);

-- 6005: 満期案件3012（続き）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6005, 'renewal_case', 3012, NULL,
  '4/8 担当者より「来週中に返答する」と連絡あり。4/12 までフォローする。',
  0, 2, 2
);

-- 6006: 満期案件3014（顧客1002, フリート, 緊急！）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6006, 'renewal_case', 3014, NULL,
  '満期まで残り8日。顧客に電話するも不在。折り返し待ち。至急対応要。',
  0, 1, 1
);

-- 6007: 満期案件3014（続き）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6007, 'renewal_case', 3014, NULL,
  '4/7 夕方に担当者より折り返し。「例年通りで更改希望」と確認。SJ依頼手配中。',
  0, 1, 1
);

-- 6008: 満期案件3022（失注）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6008, 'renewal_case', 3022, NULL,
  '8/5 顧客より「競合他社の方が保険料が3割安い」と連絡。価格交渉も折り合わず、最終的に失注。',
  0, 1, 1
);

-- 6009: 満期案件3022（失注後フォロー）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6009, 'renewal_case', 3022, NULL,
  '競合他社はネット損保経由とのこと。来年度に向けて補償内容の優位性を丁寧にフォローする方針。',
  0, 2, 2
);

-- 6010: 満期案件3028（待ち・払込待ち）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6010, 'renewal_case', 3028, NULL,
  '4/6 払込書を郵送。「今週中に振り込む」と口頭確認。満期3日前なので入金確認急ぐ。',
  0, 2, 2
);

-- 6011: 満期案件3032（顧客1004, フリート, 未対応）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6011, 'renewal_case', 3032, NULL,
  'フリート台数を5台→3台に変更検討中。来週訪問してヒアリングする予定。',
  0, 1, 1
);

-- 6012: 満期案件3064（顧客1010, SJ依頼済み）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6012, 'renewal_case', 3064, NULL,
  '4/4 SJ依頼書FAX送信。回答は4/18 頃の予定。',
  0, 1, 1
);

-- 6013: 満期案件3018（最近完了）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6013, 'renewal_case', 3018, NULL,
  '3/28 更改手続き完了。保険料125,000円（昨年比+4,167円）。顧客も了承済み。',
  0, 1, 1
);

-- 6014: 満期案件3056（書類準備中）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6014, 'renewal_case', 3056, NULL,
  '車検証のコピーを顧客に依頼。「来週持参する」とのこと。4/14 に来社予定。',
  0, 2, 2
);

-- 6015: 満期案件3090（顧客1020, 返却待ち）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6015, 'renewal_case', 3090, NULL,
  '継続承認書類を4/2に郵送。「連休明けには返送する」と電話あり。GW後に確認フォロー予定。',
  0, 1, 1
);

-- ========== 事故案件コメント（target_type='accident_case'）==========

-- 6016: 事故案件4001（当て逃げ・未対応）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6016, 'accident_case', NULL, 4001,
  '4/5 顧客より事故報告受付。警察への届出済みとのこと。東京海上日動へ連絡して事故受付番号を取得する。',
  0, 1, 1
);

-- 6017: 事故案件4002（対人・高優先度）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6017, 'accident_case', NULL, 4002,
  '4/6 事故受付。相手方は通院中。三井住友海上に連絡して示談代行を依頼する方針。顧客に詳細確認要。',
  0, 2, 2
);

-- 6018: 事故案件4002（続き）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6018, 'accident_case', NULL, 4002,
  '4/7 保険会社に連絡。示談代行開始。相手方の治療費見込み50,000円程度。進捗は週次で確認。',
  0, 2, 2
);

-- 6019: 事故案件4003（建設工事・緊急）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6019, 'accident_case', NULL, 4003,
  '4/7 緊急受付。近隣住民からの申し出あり。本日中に現地確認を予定。損保ジャパンへ速報連絡済み。',
  0, 1, 1
);

-- 6020: 事故案件4007（対人重傷・長期対応中）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6020, 'accident_case', NULL, 4007,
  '2025/11/10 受付。重傷者は入院中。三井住友海上担当者と週次連絡中。',
  0, 1, 1
);

-- 6021: 事故案件4007（続き）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6021, 'accident_case', NULL, 4007,
  '2026/3/30 確認。入院先より退院見通し「4月末」の連絡あり。示談は退院後に開始予定。引き続き週次確認継続。',
  0, 1, 1
);

-- 6022: 事故案件4008（水濡れ損害）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6022, 'accident_case', NULL, 4008,
  '修理業者A社：320,000円、B社：285,000円の見積もり取得。東京海上日動に報告。アジャスターの査定待ち。',
  0, 1, 1
);

-- 6023: 事故案件4009（建設賠償）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6023, 'accident_case', NULL, 4009,
  '建物診断士による調査完了。ひび割れは構造上の問題ではなく表面的なものと判断。補修費用約150,000円の見積もり提出中。',
  0, 1, 1
);

-- 6024: 事故案件4010（書類待ち）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6024, 'accident_case', NULL, 4010,
  '3/22 三井住友海上担当者より「相手方修理が完了次第、書類送付する」との連絡。目安は4月上旬。',
  0, 1, 1
);

-- 6025: 事故案件4011（医師賠償・訴訟）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6025, 'accident_case', NULL, 4011,
  '患者側弁護士より内容証明受領。東京海上日動の弁護士費用補償を活用し、保険会社指定弁護士を選任予定。',
  0, 2, 2
);

-- 6026: 事故案件4011（続き）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6026, 'accident_case', NULL, 4011,
  '診療記録（カルテコピー）の準備を病院に依頼中。来週中に提出予定。保険会社担当者と毎週連絡中。',
  0, 2, 2
);

-- 6027: 事故案件4015（完了・クローズ）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6027, 'accident_case', NULL, 4015,
  '10/31 示談成立・保険金支払い完了。対人250,000円・対物180,000円。顧客への説明・承認取得済み。',
  0, 1, 1
);

-- 6028: 事故案件4013（解決済み）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6028, 'accident_case', NULL, 4013,
  '11/20 示談成立。対物85,000円を保険金として支払い。相手方も納得済み。案件クローズ。',
  0, 1, 1
);

-- 6029: 事故案件4014（解決済み）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6029, 'accident_case', NULL, 4014,
  '9/15 修繕工事完了確認。保険金600,000円支払済み。顧客より「スムーズな対応に感謝」との言葉をいただいた。',
  0, 1, 1
);

-- 6030: 事故案件4006（フリート車両損傷・連絡済み）
INSERT INTO t_case_comment (
  id, target_type, renewal_case_id, accident_case_id,
  comment_body, is_deleted, created_by, updated_by
) VALUES (
  6030, 'accident_case', NULL, 4006,
  '三井住友海上事故受付番号：MS-2026-03-1122 取得。アジャスター派遣は来週の予定。修理見積もりは業者から取得中（80,000～120,000円の見込み）。',
  0, 1, 1
);
