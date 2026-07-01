-- ============================================================
--  RUN THIS if you already have cbt_system installed
--  and want to add the "num_questions" feature.
--  Go to phpMyAdmin → cbt_system → SQL tab → paste & run
-- ============================================================
USE cbt_system;

ALTER TABLE subjects
  ADD COLUMN num_questions INT NOT NULL DEFAULT 0
  COMMENT '0 = use all questions in bank'
  AFTER duration_minutes;
