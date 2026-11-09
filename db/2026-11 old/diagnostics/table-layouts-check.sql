-- Персональные настройки таблиц. Только чтение.
SET PAGESIZE 300
SET LINESIZE 240

PROMPT === Раскладки пользователей ===
SELECT l.id, u.username, l.report_code, l.use_in_mailing,
       COUNT(c.id) AS column_count, l.updated_at
  FROM xx_rjd_table_layouts l
  JOIN xx_rjd_users u ON u.id = l.user_id
  LEFT JOIN xx_rjd_table_layout_columns c ON c.layout_id = l.id
 GROUP BY l.id, u.username, l.report_code, l.use_in_mailing, l.updated_at
 ORDER BY u.username, l.report_code;

PROMPT === Раскладки без колонок ===
SELECT l.id, l.user_id, l.report_code, l.updated_at
  FROM xx_rjd_table_layouts l
 WHERE NOT EXISTS (
       SELECT 1 FROM xx_rjd_table_layout_columns c WHERE c.layout_id = l.id
   )
 ORDER BY l.updated_at DESC;

PROMPT === Повторяющиеся позиции колонок ===
SELECT layout_id, position_no, COUNT(*) AS row_count,
       LISTAGG(column_key, ', ') WITHIN GROUP (ORDER BY column_key) AS columns_at_position
  FROM xx_rjd_table_layout_columns
 GROUP BY layout_id, position_no
HAVING COUNT(*) > 1
 ORDER BY layout_id, position_no;

PROMPT === Некорректные ширины колонок ===
SELECT layout_id, column_key, column_width
  FROM xx_rjd_table_layout_columns
 WHERE column_width IS NOT NULL
   AND (column_width < 70 OR column_width > 800)
 ORDER BY layout_id, position_no;

PROMPT === Колонки без родительской раскладки ===
SELECT c.id, c.layout_id, c.column_key, c.position_no
  FROM xx_rjd_table_layout_columns c
  LEFT JOIN xx_rjd_table_layouts l ON l.id = c.layout_id
 WHERE l.id IS NULL
 ORDER BY c.layout_id, c.position_no;
