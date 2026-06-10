-- ================================================================
-- Схема PostgreSQL — Система дислокации вагонов
-- Выполнить: psql -d disl_rzd -f postgres_schema.sql
-- ================================================================

-- Пользователи системы
CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(100) UNIQUE NOT NULL,
    display_name  VARCHAR(255) NOT NULL,
    email         VARCHAR(255) DEFAULT '',
    password_hash VARCHAR(255) NOT NULL DEFAULT '',
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP DEFAULT NOW()
);

-- Сводная дислокация (одна строка = один тип вагона в одном подразделе)
CREATE TABLE IF NOT EXISTS wagon_dislocation (
    id            SERIAL PRIMARY KEY,
    report_date   DATE NOT NULL,
    section_id    VARCHAR(50) NOT NULL,     -- transit | siding | repair | empty_approach | recipients
    section_name  VARCHAR(255) NOT NULL,
    subsection    VARCHAR(255),
    park          VARCHAR(255),
    wagon_type    VARCHAR(100) NOT NULL,    -- КФК ПТС, Метанол ПТС, ...
    wagon_group   VARCHAR(50) NOT NULL,     -- Цистерны | Прочие
    wagon_count   INTEGER NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_disloc_date ON wagon_dislocation(report_date);
CREATE INDEX IF NOT EXISTS idx_disloc_section ON wagon_dislocation(report_date, section_id);

-- Расширенная дислокация (по отдельным вагонам)
CREATE TABLE IF NOT EXISTS wagon_extended (
    id               SERIAL PRIMARY KEY,
    report_date      DATE NOT NULL,
    wagon_no         VARCHAR(20) NOT NULL,
    train            VARCHAR(20),
    current_station  VARCHAR(255),
    from_station     VARCHAR(255),
    to_station       VARCHAR(255),
    cargo            VARCHAR(100),
    wagon_count      INTEGER DEFAULT 1,
    status           VARCHAR(20) DEFAULT 'empty',  -- loaded | empty
    status_label     VARCHAR(50),
    days_en_route    INTEGER DEFAULT 0,
    expected_arrival VARCHAR(20),
    park             VARCHAR(50)
);

-- Подход вагонов
CREATE TABLE IF NOT EXISTS wagon_approach (
    id                  SERIAL PRIMARY KEY,
    report_date         DATE NOT NULL,
    road                VARCHAR(255) NOT NULL,
    direction           VARCHAR(10) NOT NULL CHECK (direction IN ('arrive', 'depart')),
    wagon_count         INTEGER NOT NULL DEFAULT 0,
    wagon_type          VARCHAR(100) NOT NULL,
    destination_station VARCHAR(255),
    expected_time       VARCHAR(20)
);

-- ================================================================
-- Демо-данные (удалить на production)
-- ================================================================

-- Администратор (пароль: admin123)
INSERT INTO users (username, display_name, email, password_hash) VALUES
  ('admin', 'Администратор', 'admin@company.local',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON CONFLICT (username) DO NOTHING;

-- Дислокация (04.06.2026)
INSERT INTO wagon_dislocation (report_date, section_id, section_name, subsection, park, wagon_type, wagon_group, wagon_count) VALUES
  ('2026-06-04','transit','В пути к потребителям','В пути',NULL,'КФК ПТС','Цистерны',17),
  ('2026-06-04','transit','В пути к потребителям','В пути',NULL,'Метанол АР ПТС','Цистерны',55),
  ('2026-06-04','transit','В пути к потребителям','В пути',NULL,'Метанол ПТС','Цистерны',126),
  ('2026-06-04','transit','В пути к потребителям','В пути',NULL,'Формалин ПТС','Цистерны',3),
  ('2026-06-04','transit','В пути к потребителям','В пути',NULL,'КарбамидАрПТС','Прочие',59),
  ('2026-06-04','transit','В пути к потребителям','В пути',NULL,'РазоваяСделка','Прочие',5),
  ('2026-06-04','transit','В пути к потребителям','Тек. отс. ремонт','Нерабочий парк','Метанол ПТС','Цистерны',1),
  ('2026-06-04','transit','В пути к потребителям','Тек. отс. ремонт','Нерабочий парк','КарбамидАрПТС','Прочие',5),
  ('2026-06-04','repair','В ремонте','В депо',NULL,'Метанол АР ПТС','Цистерны',4),
  ('2026-06-04','repair','В ремонте','В депо',NULL,'Метанол ПТС','Цистерны',1),
  ('2026-06-04','repair','В ремонте','В депо',NULL,'Склад Карб Аренда','Прочие',1),
  ('2026-06-04','repair','В ремонте','В пути в депо',NULL,'Метанол ПТС','Цистерны',6),
  ('2026-06-04','siding','На подъездных путях','Груженые без документов',NULL,'Метанол АР ПТС','Цистерны',48),
  ('2026-06-04','siding','На подъездных путях','Груженые без документов',NULL,'Метанол ПТС','Цистерны',425),
  ('2026-06-04','siding','На подъездных путях','Груженые без документов',NULL,'Формалин ПТС','Цистерны',24),
  ('2026-06-04','siding','На подъездных путях','Остальные','Годные под погрузку','Метанол ПТС','Цистерны',16),
  ('2026-06-04','siding','На подъездных путях','Остальные','Годные под погрузку','СМАМД','Цистерны',43),
  ('2026-06-04','siding','На подъездных путях','Остальные','Нерабочий парк','КФК ПТС','Цистерны',108),
  ('2026-06-04','empty_approach','Подход порожних вагонов','Остальные',NULL,'Метанол АР ПТС','Цистерны',8),
  ('2026-06-04','empty_approach','Подход порожних вагонов','Остальные',NULL,'Метанол ПТС','Цистерны',131),
  ('2026-06-04','empty_approach','Подход порожних вагонов','Остальные','По ст. Пермь-Сорт.','КФК ПТС','Цистерны',7),
  ('2026-06-04','recipients','У потребителей','Груженые',NULL,'Метанол ПТС','Цистерны',48),
  ('2026-06-04','recipients','У потребителей','Груженые',NULL,'КарбамидАрПТС','Прочие',23),
  ('2026-06-04','recipients','У потребителей','Ожидание отгрузки',NULL,'КФК ПТС','Цистерны',5),
  ('2026-06-04','recipients','У потребителей','Ожидание отгрузки',NULL,'Метанол АР ПТС','Цистерны',12);

-- Подход вагонов
INSERT INTO wagon_approach (report_date, road, direction, wagon_count, wagon_type, destination_station, expected_time) VALUES
  ('2026-06-04','Горьковская','arrive',24,'Метанол ПТС','Кунгур','04.06 19:00'),
  ('2026-06-04','Свердловская','arrive',16,'СМАМД','Пермь-II','04.06 21:30'),
  ('2026-06-04','Пермский уч.','depart',32,'КФК ПТС','Березники','05.06 04:00'),
  ('2026-06-04','Московская','arrive',8,'КарбамидАрПТС','Соликамск','05.06 08:15'),
  ('2026-06-04','Казанская','arrive',45,'Метанол АР ПТС','Чайковский','05.06 12:00'),
  ('2026-06-04','Куйбышевская','arrive',37,'Метанол ПТС','Пермь-Сортировочная','05.06 20:00');
