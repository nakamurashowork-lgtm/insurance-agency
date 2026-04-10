-- =====================================================================
-- 動作確認用DML: t_activity
-- 用途: 顧客・案件への活動履歴
-- 件数: 50件
-- ID範囲: 8001 - 8050
-- 依存: 01_m_customer.sql, 03_t_contract.sql, 04_t_renewal_case.sql,
--       05_t_accident_case.sql, 11_t_sales_case.sql
-- 関連DDL: config/ddl/tenant/t_activity.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ========== 顧客1001（重要法人）活動履歴 8001-8010 ==========

-- 8001: 電話（満期案件フォロー）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8001, 1001, 2001, 3002, NULL, NULL,
  '2026-04-07', '10:00:00', '10:15:00',
  'phone', 'renewal', NULL, '総務部 田村課長',
  '自動車保険満期について',
  '満期日が5/1に迫っているため確認の電話。「今年も同条件で継続したい」との意向確認。',
  '車両入替の有無を確認したところ、新車購入を検討中とのこと。詳細は来週訪問時に確認予定。',
  '2026-04-12', '訪問・新車情報ヒアリング・見積準備', 'success',
  1, 0, 1, 1
);

-- 8002: 訪問（事故対応）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8002, 1001, 2004, NULL, 4008, NULL,
  '2026-03-10', '14:00:00', '15:30:00',
  'visit', 'accident', '株式会社テストコーポレーション本社', '管理部 石川部長',
  '水濡れ損害状況確認・書類収集',
  '漏水による損害状況を確認。修理見積もり2社取得済み。アジャスター査定日程調整。',
  '電子機器5点・什器3点の損害を確認。修理業者A社320,000円・B社285,000円の見積提出。東京海上日動アジャスターを来週派遣予定。',
  '2026-03-20', 'アジャスター査定立ち会い', 'in_progress',
  1, 0, 1, 1
);

-- 8003: 電話（企業総合・更改事前連絡）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8003, 1001, 2004, 3008, NULL, NULL,
  '2026-03-15', '09:30:00', '09:45:00',
  'phone', 'renewal', NULL, '総務部 田村課長',
  '企業総合保険 更改案内',
  '10月満期の企業総合保険について、6月頃に見積提出予定とお伝え。了承いただく。',
  NULL,
  '2026-06-01', '見積書提出', 'success',
  1, 0, 1, 1
);

-- 8004: メール（賠償保険・見積書送付）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8004, 1001, 2006, 3012, NULL, NULL,
  '2026-04-05', '11:00:00', NULL,
  'email', 'renewal', NULL, '総務部 田村課長',
  '生命保険 更改見積書送付',
  '定期保険の更改見積書をメール添付で送付。保険料据え置きの内容。',
  NULL,
  '2026-04-12', '確認の返答を待つ', 'sent',
  1, 0, 1, 1
);

-- 8005: 訪問（施設賠償事故対応）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8005, 1001, 2005, NULL, 4005, NULL,
  '2026-03-22', '13:00:00', '14:30:00',
  'visit', 'accident', '株式会社テストコーポレーション本社', '総務部 田村課長・山本総務',
  '施設賠償事故 経緯確認',
  '来訪者転倒事故の詳細を聞き取り。損保ジャパン担当者に事故概要を報告。示談交渉は保険会社が代行。',
  '転倒場所の防滑対策（マット設置）を顧客に提案。被害者への初期対応は保険会社弁護士が担当する旨を説明。',
  '2026-04-05', '保険会社担当者からの連絡待ち', 'in_progress',
  1, 0, 1, 1
);

-- 8006: 訪問（周年挨拶・クロスセル）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8006, 1001, NULL, NULL, NULL, NULL,
  '2026-02-10', '10:00:00', '11:00:00',
  'visit', 'other', '株式会社テストコーポレーション本社', '代表取締役 村上社長',
  '年次訪問・サービス状況確認',
  '代表への年次訪問。全体的に満足いただいている様子。役員の生命保険について相談あり。',
  '役員3名の死亡保障・退職金準備目的の生命保険について相談を受けた。別途見積もりを提出する方針。',
  '2026-03-01', '役員向け生命保険の見積提出', 'success',
  1, 0, 1, 1
);

-- 8007: メール（SJ依頼書送付）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8007, 1001, 2002, 3004, NULL, NULL,
  '2026-04-05', '09:30:00', NULL,
  'email', 'renewal', NULL, '総務部 田村課長',
  '火災保険 更改 SJ依頼書送付',
  '火災保険の更改依頼書をメール送付。見積回答は4/20頃の予定。',
  NULL,
  '2026-04-20', 'SJ見積回答確認', 'sent',
  1, 0, 1, 1
);

-- 8008: 電話（フォローアップ）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8008, 1001, 2006, 3012, NULL, NULL,
  '2026-04-07', '16:00:00', '16:10:00',
  'phone', 'renewal', NULL, '総務部 田村課長',
  '生命保険 見積書確認フォロー',
  '見積書の確認状況を電話で確認。「来週中に返事する」との返答。',
  NULL,
  '2026-04-12', '返答フォロー', 'pending',
  1, 0, 1, 1
);

-- 8009: 来社（資料持参）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8009, 1001, NULL, NULL, NULL, NULL,
  '2026-01-15', '14:00:00', '15:00:00',
  'meeting', 'other', '当社事務所', '総務部 田村課長',
  '年始挨拶・2026年度更改スケジュール確認',
  '年始挨拶。年度内の更改スケジュール表を共有。担当変更についても事前連絡。',
  NULL,
  '2026-02-10', '周年訪問（次回）', 'success',
  1, 0, 1, 1
);

-- 8010: セミナー（リスクマネジメント）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8010, 1001, NULL, NULL, NULL, NULL,
  '2026-03-05', '13:00:00', '17:00:00',
  'seminar', 'other', '東京都千代田区 テスト会館', '代表取締役 村上社長・総務部 田村課長',
  'リスクマネジメントセミナー案内・参加',
  '三井住友海上主催のリスクマネジメントセミナーに顧客を案内。BCP（事業継続計画）の重要性を学習。',
  '業種別リスクの説明に顧客が関心を示す。企業総合保険の見直しについて前向きな姿勢。',
  '2026-04-01', '企業総合保険の見直し提案書作成', 'success',
  1, 0, 1, 1
);

-- ========== 顧客1002（運輸法人）活動履歴 8011-8015 ==========

-- 8011: 電話（フリート事故対応）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8011, 1002, 2007, NULL, 4007, NULL,
  '2026-03-30', '10:00:00', '10:20:00',
  'phone', 'accident', NULL, '運行管理部 川島課長',
  '事故案件 進捗確認（週次）',
  '対人事故（重傷）の進捗確認。被害者は入院継続中。退院見通しは4月末とのこと。',
  '三井住友海上の担当者から「示談は退院後に開始」との連絡を受けていることを報告。引き続き週次で状況確認する。',
  '2026-04-07', '週次確認電話', 'in_progress',
  1, 0, 1, 1
);

-- 8012: 訪問（フリート満期フォロー）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8012, 1002, 2007, 3014, NULL, NULL,
  '2026-04-07', '13:00:00', '14:00:00',
  'visit', 'renewal', 'テスト運輸株式会社 本社', '運行管理部 川島課長',
  'フリート自動車保険 更改確認（満期まで8日）',
  '満期が近いため訪問。「例年通りで更改したい」と担当者から確認。SJ依頼書を手渡しで受け取る。',
  '台数の変更なし（10台）。等級についても現状維持。SJ手配を本日中に実施する。',
  '2026-04-10', 'SJ確認・見積回答', 'success',
  1, 0, 1, 1
);

-- 8013: 電話（SJ依頼書送付済み確認）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8013, 1002, 2010, 3020, NULL, NULL,
  '2026-04-03', '09:00:00', '09:15:00',
  'phone', 'renewal', NULL, '総務部 橋本担当',
  '企業賠償保険 SJ依頼書受領確認',
  'SJ依頼書の受領確認の電話。「受け取った」と確認。見積回答は4月末頃の予定。',
  NULL,
  '2026-04-30', 'SJ見積確認', 'success',
  1, 0, 1, 1
);

-- 8014: 来社（更改完了報告）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8014, 1002, 2009, 3018, NULL, NULL,
  '2026-03-28', '15:00:00', '15:30:00',
  'meeting', 'renewal', '当社事務所', '運行管理部 川島課長',
  '傷害保険 更改完了・証券受け渡し',
  '更改完了した傷害保険の証券を顧客に手渡し。保険料125,000円（前年比+4,167円）の説明実施。',
  NULL,
  NULL, NULL, 'success',
  1, 0, 1, 1
);

-- 8015: 電話（火災保険・書類確認）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8015, 1002, 2008, 3016, NULL, NULL,
  '2026-04-01', '13:00:00', '13:10:00',
  'phone', 'renewal', NULL, '総務部 橋本担当',
  '火災保険 更改 書類準備状況確認',
  '建物評価書の取得状況を確認。「来週中には用意できる」との回答。',
  NULL,
  '2026-04-15', '書類受け取り・SJ依頼', 'pending',
  1, 0, 1, 1
);

-- ========== 顧客1003（小売法人）活動履歴 8016-8018 ==========

-- 8016: 電話（傷害保険払込確認）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8016, 1003, 2014, 3028, NULL, NULL,
  '2026-04-06', '10:00:00', '10:20:00',
  'phone', 'renewal', NULL, '代表 小沼社長',
  '傷害保険 払込書送付・確認',
  '払込書を郵送済みの旨を連絡。「今週中に振り込む」と口頭確認。',
  '満期まで4日。入金確認後に更改完了とする。',
  '2026-04-08', '入金確認', 'success',
  2, 0, 2, 2
);

-- 8017: 訪問（自動車保険・満期フォロー）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8017, 1003, 2012, 3024, NULL, NULL,
  '2026-04-05', '15:00:00', '15:30:00',
  'visit', 'renewal', '有限会社テスト商事 本社', '代表 小沼社長',
  '自動車保険 更改案内',
  '4/25満期の自動車保険について訪問。「引き続きお願いしたい」と確認。SJ依頼の準備中。',
  NULL,
  '2026-04-10', 'SJ依頼書送付', 'success',
  2, 0, 2, 2
);

-- 8018: 電話（自動車保険ヒアリング）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8018, 1003, 2012, 3024, NULL, NULL,
  '2026-04-07', '11:30:00', '11:40:00',
  'phone', 'renewal', NULL, '代表 小沼社長',
  '車両情報確認',
  '更改に必要な車検証情報をヒアリング。登録番号・車台番号を確認。',
  NULL,
  '2026-04-12', 'SJ依頼書FAX', 'success',
  2, 0, 2, 2
);

-- ========== 顧客1006（個人）活動履歴 8019-8021 ==========

-- 8019: 電話（自動車保険満期フォロー）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8019, 1006, 2022, 3044, NULL, NULL,
  '2026-04-07', '17:30:00', '17:45:00',
  'phone', 'renewal', NULL, '山田太郎',
  '自動車保険 満期フォロー',
  '4/25満期の自動車保険について連絡。「継続でお願いします」と確認。',
  NULL,
  '2026-04-10', 'SJ依頼書手配', 'success',
  2, 0, 1, 1
);

-- 8020: 紹介者へのお礼連絡
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8020, 1006, NULL, NULL, NULL, NULL,
  '2026-02-20', '10:00:00', '10:10:00',
  'phone', 'other', NULL, '山田太郎',
  '高橋様ご紹介のお礼',
  '顧客1012（高橋良子様）をご紹介いただいたお礼の電話。ご縁に感謝。',
  NULL,
  NULL, NULL, 'success',
  2, 0, 1, 1
);

-- 8021: 事故対応フォロー
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8021, 1006, 2022, NULL, 4010, NULL,
  '2026-03-22', '13:00:00', '13:15:00',
  'phone', 'accident', NULL, '山田太郎',
  '事故案件 書類提出状況確認',
  '相手方修理完了書の送付が遅れていることを確認。三井住友海上に状況を確認中と伝達。',
  NULL,
  '2026-04-07', '三井住友海上からの連絡待ち', 'in_progress',
  2, 0, 1, 1
);

-- ========== 顧客1007（個人）活動 8022-8023 ==========

-- 8022: 電話（自動車保険・30日境界）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8022, 1007, 2025, 3050, NULL, NULL,
  '2026-04-07', '12:00:00', '12:10:00',
  'phone', 'renewal', NULL, '鈴木花子',
  '自動車保険 更改ご連絡（5/7満期）',
  '5/7満期の自動車保険についてご連絡。「今年も継続します」と確認。',
  NULL,
  '2026-04-15', '見積書・更改書類送付', 'success',
  2, 0, 1, 1
);

-- 8023: 来社（事故解決報告）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8023, 1007, 2025, NULL, 4013, NULL,
  '2025-11-25', '14:00:00', '14:30:00',
  'meeting', 'accident', '当社事務所', '鈴木花子',
  '事故示談成立・保険金支払い報告',
  '対物事故の示談成立・保険金85,000円支払い完了の報告。顧客に書類を手渡し。',
  '顧客より「スムーズな対応で助かった」とのお言葉をいただく。安全運転についてもアドバイス。',
  NULL, NULL, 'success',
  2, 0, 1, 1
);

-- ========== 顧客1009（個人・事故中）活動 8024-8025 ==========

-- 8024: 電話（事故受付・高優先）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8024, 1009, 2030, NULL, 4002, NULL,
  '2026-04-06', '18:00:00', '18:30:00',
  'phone', 'accident', NULL, '田中美咲',
  '交通事故受付・初期対応',
  '交差点追突事故の受付。相手方に頸部打撲あり。保険会社への連絡を案内。',
  '警察への届出済みを確認。三井住友海上の事故受付に連絡し示談代行を依頼。顧客を安心させるよう丁寧に説明。',
  '2026-04-07', '保険会社からの連絡確認', 'in_progress',
  2, 0, 2, 2
);

-- 8025: 電話（満期フォロー）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8025, 1009, 2030, 3060, NULL, NULL,
  '2026-04-07', '09:30:00', '09:45:00',
  'phone', 'renewal', NULL, '田中美咲',
  '自動車保険 更改ご連絡（事故対応中顧客）',
  '4/15満期について確認。事故対応中のため慎重に説明。「継続したい」との意向確認。',
  '事故の等級への影響についても説明。1等級ダウンの見込みを伝達し、来年度の保険料について参考値を提示。',
  '2026-04-10', '見積書送付（等級変更後の金額を明示）', 'success',
  2, 0, 2, 2
);

-- ========== 顧客1011（見込み法人）活動 8026-8028 ==========

-- 8026: 訪問（新規開拓）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8026, 1011, NULL, NULL, NULL, 9001,
  '2026-03-20', '14:00:00', '15:30:00',
  'visit', 'new_business', '株式会社テスト見込商事 本社', '代表取締役 岡田社長',
  '新規開拓訪問・ヒアリング',
  '現在の損保会社への不満をヒアリング。保険料の高さと対応速度に不満とのこと。',
  '現状：大手直販系損保と契約中。保険料は年間約380,000円（複数種目合計）。補償内容の見直しも希望。当社での見積を提出予定。',
  '2026-04-05', '比較見積書提出', 'in_progress',
  1, 0, 1, 1
);

-- 8027: 電話（見積提出フォロー）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8027, 1011, NULL, NULL, NULL, 9001,
  '2026-04-07', '11:00:00', '11:20:00',
  'phone', 'new_business', NULL, '代表取締役 岡田社長',
  '比較見積書 提出後フォロー',
  '見積書を4/5に提出済み。社内での検討状況を確認したところ、「取締役会で議論中」とのこと。',
  '「補償内容は評価している。保険料は現在のより少し高いが検討する」との回答。4月末に返答予定。',
  '2026-04-25', '返答確認・追加資料提出', 'pending',
  1, 0, 1, 1
);

-- 8028: メール（追加資料送付）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8028, 1011, NULL, NULL, NULL, 9001,
  '2026-04-07', '15:00:00', NULL,
  'email', 'new_business', NULL, '代表取締役 岡田社長',
  '事故対応実績レポート送付',
  '当社の事故対応実績レポートをメールで送付。補償内容・対応品質の優位性をアピール。',
  NULL,
  '2026-04-25', '最終判断の確認', 'sent',
  1, 0, 1, 1
);

-- ========== 顧客1012（見込み個人）活動 8029-8030 ==========

-- 8029: 電話（紹介案件初回連絡）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8029, 1012, NULL, NULL, NULL, 9002,
  '2026-02-05', '16:00:00', '16:20:00',
  'phone', 'new_business', NULL, '高橋良子',
  '自動車保険 見積相談（山田様ご紹介）',
  '山田太郎様（顧客1006）のご紹介。現在の保険料への不満から乗り換えを検討中とのこと。',
  '現在：ダイレクト系損保。保険料は年間82,000円。事故対応の不満が主な理由。',
  '2026-02-15', '見積書郵送', 'success',
  2, 0, 1, 1
);

-- 8030: 来社（成約）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8030, 1012, NULL, NULL, NULL, 9002,
  '2026-02-12', '10:00:00', '11:00:00',
  'meeting', 'new_business', '当社事務所', '高橋良子',
  '自動車保険 申込手続き',
  '当社への乗り換えを決定。契約書類の記入・取り付けを完了。3/1始期で成約。',
  NULL,
  NULL, NULL, 'success',
  2, 0, 1, 1
);

-- ========== 顧客1020（自動車販売法人）活動 8031-8035 ==========

-- 8031: 訪問（フリート更改・複数件）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8031, 1020, NULL, NULL, NULL, NULL,
  '2026-04-06', '10:00:00', '11:30:00',
  'visit', 'renewal', 'テスト自動車販売株式会社 本社', '総務部 西山部長',
  'フリート保険 4/30・5/1 更改案内',
  '4/30・5/1 満期のフリートA・B2契約について訪問。継続意向を確認。返却待ちの書類督促も実施。',
  'フリートAは問題なし。フリートBの継続承認書類（SJ書類）が未返送。「来週中に返送する」と約束。',
  '2026-04-15', '書類返送確認', 'in_progress',
  1, 0, 1, 1
);

-- 8032: 電話（フリートC SJ依頼確認）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8032, 1020, 2046, 3092, NULL, NULL,
  '2026-04-06', '16:00:00', '16:10:00',
  'phone', 'renewal', NULL, '総務部 西山部長',
  'フリートC 6/1満期 SJ依頼書送付確認',
  'SJ依頼書をFAXで送付済みの旨を確認。4月末頃に見積回答の予定。',
  NULL,
  '2026-04-25', 'SJ見積確認', 'success',
  1, 0, 1, 1
);

-- 8033: 電話（試乗車事故フォロー）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8033, 1020, 2044, NULL, 4006, NULL,
  '2026-03-28', '14:00:00', '14:20:00',
  'phone', 'accident', NULL, '総務部 西山部長',
  '試乗車事故 アジャスター派遣日程確認',
  '三井住友海上アジャスターの派遣日程を来週に設定済みと報告。修理見積も業者より取得中。',
  NULL,
  '2026-04-05', 'アジャスター査定後の報告', 'in_progress',
  1, 0, 1, 1
);

-- 8034: 訪問（新種保険・動産総合）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8034, 1020, 2050, 3100, NULL, NULL,
  '2026-03-10', '15:00:00', '16:00:00',
  'visit', 'renewal', 'テスト自動車販売株式会社 本社', '代表取締役 安田社長',
  '動産総合保険 11月満期 更改事前案内',
  '在庫車両動産保険について事前案内。在庫台数増加に対応した補償額の見直しを提案。',
  '在庫台数が昨年比120%に増加。補償額を現在の+20%増額する提案を検討中。',
  '2026-05-01', '補償額見直し提案書提出', 'in_progress',
  1, 0, 1, 1
);

-- 8035: 電話（火災保険・書類準備）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8035, 1020, 2047, 3094, NULL, NULL,
  '2026-04-01', '11:00:00', '11:20:00',
  'phone', 'renewal', NULL, '総務部 西山部長',
  '火災保険 新ショールーム建物評価について',
  '新ショールームの建物評価再算定の依頼。不動産鑑定士の選定を進めている旨を確認。',
  NULL,
  '2026-05-01', '建物評価書受領・SJ依頼', 'in_progress',
  1, 0, 1, 1
);

-- ========== 各種活動 8036-8050 ==========

-- 8036: 顧客1004（建設）工事事故対応
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8036, 1004, 2017, NULL, 4003, NULL,
  '2026-04-07', '09:00:00', '10:00:00',
  'visit', 'accident', '東京都江東区 事故現場', '現場監督 田島主任',
  '工事飛散物事故 現地確認',
  '近隣民家外壁へのひびを現地確認。損保ジャパンに速報連絡済み。アジャスター手配を要請。',
  '外壁のひびは長さ約50cm、幅2mm程度。住民への謝罪訪問も実施。工事一時中断の検討も必要。',
  '2026-04-10', 'アジャスター査定立ち会い', 'in_progress',
  1, 0, 1, 1
);

-- 8037: 顧客1004 建設賠償フォロー
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8037, 1004, 2019, NULL, 4009, NULL,
  '2026-04-01', '14:00:00', '14:20:00',
  'phone', 'accident', NULL, '代表取締役 田中社長',
  '建設賠償 損害額協議状況報告',
  '建物調査完了・補修費用約150,000円の見積もりを損保ジャパンに提出中と報告。',
  NULL,
  '2026-04-15', '損保ジャパンとの協議結果確認', 'in_progress',
  1, 0, 1, 1
);

-- 8038: 顧客1005 医師賠償事故フォロー
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8038, 1005, 2020, NULL, 4011, NULL,
  '2026-04-05', '11:00:00', '12:00:00',
  'visit', 'accident', '医療法人テストクリニック', '院長 山田医師',
  '医師賠償 書類準備状況確認・弁護士費用補償説明',
  '診療記録の準備状況を確認。東京海上日動の弁護士費用補償制度を詳しく説明。',
  '弁護士費用補償の上限（300万円）について説明。保険会社指定弁護士の選任を勧める。来週中に弁護士選任を進める予定。',
  '2026-04-12', '弁護士選任確認', 'in_progress',
  2, 0, 2, 2
);

-- 8039: 顧客1013（見込み個人）初回訪問
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8039, 1013, NULL, NULL, NULL, 9003,
  '2026-02-20', '14:00:00', '15:00:00',
  'meeting', 'new_business', '当社事務所', '中村浩',
  '生命保険 相談来社',
  '生命保険の新規相談来社。30代男性で医療保険と死亡保障の見直しを希望。',
  '現状：職場団体保険のみ加入。医療保険の上乗せと死亡保障の強化を検討中。住友生命の医療保険を提案予定。',
  '2026-03-01', '医療保険提案書送付', 'success',
  2, 0, 2, 2
);

-- 8040: 顧客1013 成約
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8040, 1013, NULL, NULL, NULL, 9003,
  '2026-03-10', '10:00:00', '11:00:00',
  'meeting', 'new_business', '当社事務所', '中村浩',
  '医療保険 申込手続き',
  '住友生命 医療保険の申込書類一式を記入・取得完了。4/1始期で成約。',
  NULL,
  NULL, NULL, 'success',
  2, 0, 2, 2
);

-- 8041-8050: 過去年度の活動履歴（参照データ補完）

-- 8041: 過去（2025年度）顧客1001 周年訪問
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8041, 1001, NULL, NULL, NULL, NULL,
  '2025-02-10', '10:00:00', '11:00:00',
  'visit', 'other', '株式会社テストコーポレーション本社', '代表取締役 村上社長',
  '年次訪問（2025年）',
  '前年度の更改完了報告と今年度スケジュール案内。',
  NULL, NULL, NULL, 'success',
  1, 0, 1, 1
);

-- 8042: 2025年度 顧客1002 フリート事故前期
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8042, 1002, 2007, NULL, 4015, NULL,
  '2025-05-20', '10:00:00', '10:30:00',
  'phone', 'accident', NULL, '運行管理部 川島課長',
  '事故案件（4015）進捗確認',
  '対人対物事故（4015）示談交渉中。保険会社担当者から連絡あり。',
  NULL, '2025-05-27', '週次確認', 'in_progress',
  1, 0, 1, 1
);

-- 8043: 2025年度 顧客1003 更改完了
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8043, 1003, 2012, 3023, NULL, NULL,
  '2025-04-15', '15:00:00', '15:30:00',
  'meeting', 'renewal', '当社事務所', '代表 小沼社長',
  '自動車保険 更改完了・証券手渡し',
  '更改完了した自動車保険証券を手渡し。前年同条件での継続確認。',
  NULL, NULL, NULL, 'success',
  2, 0, 2, 2
);

-- 8044: 2025年度 顧客1006 紹介依頼
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8044, 1006, NULL, NULL, NULL, NULL,
  '2025-12-01', '17:00:00', '17:15:00',
  'phone', 'other', NULL, '山田太郎',
  '年末ご挨拶・紹介のお願い',
  '年末挨拶の電話。紹介案件のお願いを伝えたところ、知人を紹介いただく約束をいただく（→高橋様）。',
  NULL, NULL, NULL, 'success',
  2, 0, 1, 1
);

-- 8045: 2025年度 顧客1020 台数変更
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8045, 1020, 2044, NULL, NULL, NULL,
  '2026-02-01', '10:00:00', '10:30:00',
  'visit', 'other', 'テスト自動車販売株式会社 本社', '総務部 西山部長',
  'フリートB 台数変更手続き',
  'フリートグループBから車両1台除外の手続き。保険料の差額を精算。',
  NULL, NULL, NULL, 'success',
  1, 0, 1, 1
);

-- 8046: 顧客1002 訪問（年始挨拶）
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8046, 1002, NULL, NULL, NULL, NULL,
  '2026-01-10', '10:00:00', '10:30:00',
  'visit', 'other', 'テスト運輸株式会社 本社', '代表取締役 岸本社長',
  '年始挨拶',
  '年始訪問。今年度の更改スケジュールを案内。',
  NULL, NULL, NULL, 'success',
  1, 0, 1, 1
);

-- 8047: 顧客1004 フリート更改訪問
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8047, 1004, 2016, 3032, NULL, NULL,
  '2026-04-07', '16:00:00', '16:30:00',
  'phone', 'renewal', NULL, '代表取締役 田中社長',
  'フリート自動車保険 4/30満期 更改確認',
  '4/30満期のフリート保険について確認。「継続で」との意向確認。SJ依頼書の準備開始。',
  NULL,
  '2026-04-12', 'SJ依頼書送付', 'success',
  1, 0, 1, 1
);

-- 8048: 顧客1001 役員生命保険提案
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8048, 1001, NULL, NULL, NULL, 9004,
  '2026-03-20', '14:00:00', '15:30:00',
  'visit', 'new_business', '株式会社テストコーポレーション本社', '代表取締役 村上社長',
  '役員向け生命保険 提案書説明',
  '役員3名向けの死亡保障・退職金準備を目的とした逓増定期保険を提案。',
  '役員3名の保障合計：死亡保険金3億円、退職金準備効果・節税効果を説明。社長は前向きな反応。',
  '2026-04-15', '稟議提出結果確認', 'in_progress',
  1, 0, 1, 1
);

-- 8049: 顧客1010（個人）電話フォロー
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8049, 1010, 2032, 3064, NULL, NULL,
  '2026-04-04', '12:00:00', '12:15:00',
  'phone', 'renewal', NULL, '伊藤誠',
  '自動車保険 SJ依頼書送付確認',
  'SJ依頼書をFAX送信済みの旨を確認。「受け取りました」と確認。',
  NULL,
  '2026-04-18', 'SJ見積確認', 'success',
  1, 0, 1, 1
);

-- 8050: 顧客1008（個人）電話
INSERT INTO t_activity (
  id, customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
  activity_date, start_time, end_time,
  activity_type, purpose_type, visit_place, interviewee_name,
  subject, content_summary, detail_text,
  next_action_date, next_action_note, result_type,
  staff_id, is_deleted, created_by, updated_by
) VALUES (
  8050, 1008, 2028, 3056, NULL, NULL,
  '2026-03-28', '17:00:00', '17:15:00',
  'phone', 'renewal', NULL, '佐藤健一',
  '自動車保険 書類確認（車検証）',
  '更改に必要な車検証コピーの提出を依頼。「来週持参します」との約束。',
  NULL,
  '2026-04-14', '書類受け取り', 'success',
  2, 0, 2, 2
);
