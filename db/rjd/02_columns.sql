-- Колонки объектов XX_RJD_%. Это диагностический запрос без изменений схемы.
SELECT table_name,
       column_id,
       column_name,
       data_type,
       data_length,
       nullable
  FROM user_tab_columns
 WHERE table_name LIKE 'XX_RJD_%'
 ORDER BY table_name, column_id;
