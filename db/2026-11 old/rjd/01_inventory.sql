-- Список таблиц, представлений и последовательностей XX_RJD_% в текущей схеме.
SELECT object_type, object_name, status
  FROM user_objects
 WHERE object_name LIKE 'XX_RJD_%'
 ORDER BY object_type, object_name;
