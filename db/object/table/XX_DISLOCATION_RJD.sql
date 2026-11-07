DROP TABLE XX_ETW.XX_DISLOCATION_RJD CASCADE CONSTRAINTS;

CREATE TABLE XX_ETW.XX_DISLOCATION_RJD
(
  ID                     NUMBER                 NOT NULL,
  REPORT_DT              DATE                   NOT NULL,
  TYPE_REFERENCE         VARCHAR2(50 BYTE),
  WAGON_NO               VARCHAR2(500 BYTE),
  WAYBILL_NO             VARCHAR2(500 BYTE),
  WAGON_TYPE_CODE        VARCHAR2(500 BYTE),
  OWNER_ADMIN            VARCHAR2(500 BYTE),
  TRIP_START_DT          DATE,
  DEPART_STATE           VARCHAR2(500 BYTE),
  DEPART_ROAD            VARCHAR2(500 BYTE),
  DEPART_STATION         VARCHAR2(500 BYTE),
  TRIP_END_DT            DATE,
  DEST_STATE             VARCHAR2(500 BYTE),
  DEST_ROAD              VARCHAR2(500 BYTE),
  DEST_STATION           VARCHAR2(500 BYTE),
  CONSIGNOR_TGNL         VARCHAR2(500 BYTE),
  CONSIGNOR              VARCHAR2(500 BYTE),
  CONSIGNOR_OKPO         VARCHAR2(500 BYTE),
  CONSIGNOR_NAME         VARCHAR2(500 BYTE),
  CONSIGNEE_TGNL         VARCHAR2(500 BYTE),
  CONSIGNEE              VARCHAR2(500 BYTE),
  CONSIGNEE_OKPO         VARCHAR2(500 BYTE),
  CONSIGNEE_NAME         VARCHAR2(500 BYTE),
  CARGO_NAME             VARCHAR2(500 BYTE),
  CARGO_GNG              VARCHAR2(500 BYTE),
  CARGO_WEIGHT_KG        NUMBER,
  MILEAGE_LOADED_KM      NUMBER,
  MILEAGE_EMPTY_KM       NUMBER,
  MILEAGE_TOTAL_KM       NUMBER,
  MILEAGE_NORM_KM        NUMBER,
  MILEAGE_REMAIN_KM      NUMBER,
  MILEAGE_SIGN           VARCHAR2(500 BYTE),
  SPECIAL_MARKS          VARCHAR2(500 BYTE),
  PREV_CARGO             VARCHAR2(500 BYTE),
  OPER_STATION           VARCHAR2(500 BYTE),
  OPER_ROAD              VARCHAR2(500 BYTE),
  OPERATION              VARCHAR2(500 BYTE),
  OPER_MNEMONIC          VARCHAR2(500 BYTE),
  OPER_DT                DATE,
  PARK_TYPE              VARCHAR2(500 BYTE),
  HANDOVER_ROAD          VARCHAR2(500 BYTE),
  RECEIVE_ROAD           VARCHAR2(500 BYTE),
  TRAIN_INDEX            VARCHAR2(500 BYTE),
  TRAIN_NO               VARCHAR2(500 BYTE),
  WAGON_IN_TRAIN         NUMBER(5),
  PARK_NO                VARCHAR2(500 BYTE),
  TRACK_NO               VARCHAR2(500 BYTE),
  SEALS_COUNT            NUMBER(3),
  LOADED_CONTAINERS      NUMBER(3),
  EMPTY_CONTAINERS       NUMBER(3),
  CONTAINER_NOS          VARCHAR2(500 BYTE),
  NORM_DELIVERY_DT       DATE,
  DIST_PASSED_KM         NUMBER,
  DIST_REMAIN_KM         NUMBER,
  DIST_TOTAL_KM          NUMBER,
  IDLE_TIME_HHMMSS       VARCHAR2(500 BYTE),
  IDLE_TIME_DAYS         NUMBER,
  EXTRA_WAYBILL_NO       VARCHAR2(500 BYTE),
  EXTRA_SEND_ID          VARCHAR2(500 BYTE),
  ASOUP_DEPART_DT        DATE,
  ASOUP_ARRIVE_DT        DATE,
  SEND_ID                VARCHAR2(500 BYTE),
  WAYBILL_ID             VARCHAR2(500 BYTE),
  WAGON_NO2              VARCHAR2(500 BYTE),
  QUALITY_SIGN           VARCHAR2(500 BYTE),
  STATE_ASSIGN_DT        DATE,
  WAGON_STATE            VARCHAR2(500 BYTE),
  STATE_REASON           VARCHAR2(500 BYTE),
  STATE_STATION          VARCHAR2(500 BYTE),
  REG_DATE               DATE,
  BUILD_DATE             DATE,
  NEXT_REPAIR_DT         DATE,
  NEXT_REPAIR_TYPE       VARCHAR2(500 BYTE),
  FACTORY_NO             VARCHAR2(500 BYTE),
  MANUFACTURER           VARCHAR2(500 BYTE),
  WAGON_TYPE_NAME        VARCHAR2(500 BYTE),
  WAGON_MODEL            VARCHAR2(500 BYTE),
  TARE_WEIGHT            NUMBER,
  LOAD_CAPACITY          NUMBER,
  LENGTH_MM              NUMBER,
  LAST_CAP_REPAIR_DEPOT  VARCHAR2(500 BYTE),
  LAST_CAP_REPAIR_DT     DATE,
  LAST_DEP_REPAIR_DEPOT  VARCHAR2(500 BYTE),
  LAST_DEP_REPAIR_DT     DATE,
  HOME_ROAD              VARCHAR2(500 BYTE),
  HOME_DEPOT             VARCHAR2(500 BYTE),
  EXCLUDE_DATE           DATE,
  NO_TRANSIT_REASON      VARCHAR2(500 BYTE),
  PREV_WAGON_NO          VARCHAR2(500 BYTE),
  OWNER                  VARCHAR2(500 BYTE),
  OWNER_OKPO             VARCHAR2(500 BYTE),
  OWNER_LOCAL_CODE       VARCHAR2(500 BYTE),
  HOME_STATION           VARCHAR2(500 BYTE),
  THRESHOLD_SIGN         VARCHAR2(500 BYTE),
  LEASE_SIGN             NUMBER(1),
  LIFE_EXT_DATE          DATE,
  LESSEE                 VARCHAR2(500 BYTE),
  LESSEE_OKPO            VARCHAR2(500 BYTE),
  LESSEE_LOCAL_CODE      VARCHAR2(500 BYTE),
  LEASE_HOME_STATION     VARCHAR2(500 BYTE),
  LEASE_END_DATE         DATE,
  SERVICE_LIFE           DATE,
  BODY_MATERIAL_CODE     NUMBER(2),
  BODY_MATERIAL_NAME     VARCHAR2(500 BYTE),
  BODY_VOLUME            NUMBER,
  CLEARANCE              VARCHAR2(500 BYTE),
  AIR_DIST_TYPE          VARCHAR2(500 BYTE),
  AUTOMODE               VARCHAR2(500 BYTE),
  AUTO_LEVER             VARCHAR2(500 BYTE),
  BRAKE_TYPE             VARCHAR2(500 BYTE),
  COUPLER_TYPE           VARCHAR2(500 BYTE),
  BOGIE_MODEL            VARCHAR2(500 BYTE),
  SHOCK_ABSORBER         VARCHAR2(500 BYTE),
  LIFE_EXT_SIGN          NUMBER(1),
  BOILER_CALIBER         NUMBER,
  DRAIN_DEVICE           VARCHAR2(500 BYTE),
  LEVER_GEAR             VARCHAR2(500 BYTE),
  WAGON_MODEL_CODE       VARCHAR2(500 BYTE),
  REPAIR_BY_MILEAGE      NUMBER(1),
  PROXY_OPERATOR         VARCHAR2(500 BYTE),
  PROXY_OPERATOR_OKPO    VARCHAR2(500 BYTE),
  WAGON_TYPE_CODE2       VARCHAR2(500 BYTE),
  WAGON_TYPE_COND        VARCHAR2(500 BYTE),
  AXLES_COUNT            NUMBER(2),
  EXCLUDE_DEPOT          VARCHAR2(500 BYTE),
  EXCLUDE_REASON         VARCHAR2(500 BYTE),
  DAYS_TO_REPAIR         NUMBER,
  DAYS_NO_OPER           NUMBER,
  DAYS_NO_MOVE           NUMBER,
  OPER_STATION_ESR_CODE  VARCHAR2(50 BYTE),
  ORGANIZATION_ID        NUMBER                 DEFAULT 1
)
TABLESPACE USERS
PCTUSED    40
PCTFREE    10
INITRANS   1
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           )
LOGGING 
NOCOMPRESS 
NOCACHE;

COMMENT ON TABLE XX_ETW.XX_DISLOCATION_RJD IS 'Дислокация вагонов РЖД - 126 колонок из ЛК клиента РЖД';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.REPORT_DT IS 'Системное [Дата справки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.TYPE_REFERENCE IS 'Системное [Тип справки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_NO IS 'Данные о вагоне [Номер вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAYBILL_NO IS 'Данные о вагоне [Номер накладной]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_TYPE_CODE IS 'Данные о вагоне [Тип вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OWNER_ADMIN IS 'Данные о вагоне [Администрация собственника]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.TRIP_START_DT IS 'Данные о вагоне [Дата начала рейса]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DEPART_STATE IS 'Данные о вагоне [Государство отправления]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DEPART_ROAD IS 'Данные о вагоне [Дорога отправления]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DEPART_STATION IS 'Данные о вагоне [Станция отправления]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.TRIP_END_DT IS 'Данные о вагоне [Дата окончания рейса]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DEST_STATE IS 'Данные о вагоне [Государство назначения]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DEST_ROAD IS 'Данные о вагоне [Дорога назначения]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DEST_STATION IS 'Данные о вагоне [Станция назначения]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNOR_TGNL IS 'Данные о вагоне [ТГНЛ грузоотправителя]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNOR IS 'Данные о вагоне [Грузоотправитель]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNOR_OKPO IS 'Данные о вагоне [ОКПО грузоотправителя]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNOR_NAME IS 'Данные о вагоне [Наименование грузоотправителя]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNEE_TGNL IS 'Данные о вагоне [ТГНЛ грузополучателя]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNEE IS 'Данные о вагоне [Грузополучатель]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNEE_OKPO IS 'Данные о вагоне [ОКПО грузополучателя]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONSIGNEE_NAME IS 'Данные о вагоне [Наименование грузополучателя]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CARGO_NAME IS 'Данные о вагоне [Наименование груза]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CARGO_GNG IS 'Данные о вагоне [Код ГНГ]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CARGO_WEIGHT_KG IS 'Данные о вагоне [Масса груза (кг)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.MILEAGE_LOADED_KM IS 'Данные о вагоне [Пробег гружёный (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.MILEAGE_EMPTY_KM IS 'Данные о вагоне [Пробег порожний (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.MILEAGE_TOTAL_KM IS 'Данные о вагоне [Пробег общий (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.MILEAGE_NORM_KM IS 'Данные о вагоне [Пробег нормативный (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.MILEAGE_REMAIN_KM IS 'Данные о вагоне [Пробег остаток (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.MILEAGE_SIGN IS 'Данные о вагоне [Знак пробега]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.SPECIAL_MARKS IS 'Данные о вагоне [Особые отметки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.PREV_CARGO IS 'Данные о вагоне [Предыдущий груз]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OPER_STATION IS 'Дислокация вагона [Станция операции]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OPER_ROAD IS 'Дислокация вагона [Дорога операции]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OPERATION IS 'Дислокация вагона [Операция]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OPER_MNEMONIC IS 'Дислокация вагона [Мнемоника операции]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OPER_DT IS 'Дислокация вагона [Дата операции]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.PARK_TYPE IS 'Дислокация вагона [Признак парка]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.HANDOVER_ROAD IS 'Дислокация вагона [Дорога сдачи]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.RECEIVE_ROAD IS 'Дислокация вагона [Дорога приёма]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.TRAIN_INDEX IS 'Дислокация вагона [Индекс поезда]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.TRAIN_NO IS 'Дислокация вагона [Номер поезда]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_IN_TRAIN IS 'Дислокация вагона [Позиция вагона в составе]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.PARK_NO IS 'Дислокация вагона [Номер парка]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.TRACK_NO IS 'Дислокация вагона [Номер пути]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.SEALS_COUNT IS 'Дислокация вагона [Количество пломб]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LOADED_CONTAINERS IS 'Дислокация вагона [Гружёные контейнеры]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.EMPTY_CONTAINERS IS 'Дислокация вагона [Порожние контейнеры]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CONTAINER_NOS IS 'Дислокация вагона [Номера контейнеров]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.NORM_DELIVERY_DT IS 'Дислокация вагона [Нормативная дата доставки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DIST_PASSED_KM IS 'Дислокация вагона [Расстояние пройдено (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DIST_REMAIN_KM IS 'Дислокация вагона [Расстояние остаток (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DIST_TOTAL_KM IS 'Дислокация вагона [Расстояние общее (км)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.IDLE_TIME_HHMMSS IS 'Дислокация вагона [Простой (ЧЧ:ММ:СС)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.IDLE_TIME_DAYS IS 'Дислокация вагона [Простой (сут.)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.EXTRA_WAYBILL_NO IS 'Дислокация вагона [Номер досылочной накладной]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.EXTRA_SEND_ID IS 'Дислокация вагона [Идентификатор досылки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.ASOUP_DEPART_DT IS 'Дислокация вагона [Дата отправления АСОУП]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.ASOUP_ARRIVE_DT IS 'Дислокация вагона [Дата прибытия АСОУП]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.SEND_ID IS 'Дислокация вагона [Идентификатор отправки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAYBILL_ID IS 'Дислокация вагона [Идентификатор накладной]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_NO2 IS 'Техническое состояние вагона [Номер вагона (дубль)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.QUALITY_SIGN IS 'Техническое состояние вагона [Признак качества]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.STATE_ASSIGN_DT IS 'Техническое состояние вагона [Дата присвоения состояния]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_STATE IS 'Техническое состояние вагона [Состояние вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.STATE_REASON IS 'Техническое состояние вагона [Причина состояния]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.STATE_STATION IS 'Техническое состояние вагона [Станция состояния]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.REG_DATE IS 'Техническое состояние вагона [Дата регистрации]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.BUILD_DATE IS 'Техническое состояние вагона [Дата постройки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.NEXT_REPAIR_DT IS 'Техническое состояние вагона [Дата следующего ремонта]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.NEXT_REPAIR_TYPE IS 'Техническое состояние вагона [Тип следующего ремонта]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.FACTORY_NO IS 'Техническое состояние вагона [Заводской номер]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.MANUFACTURER IS 'Техническое состояние вагона [Завод-изготовитель]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_TYPE_NAME IS 'Техническое состояние вагона [Наименование типа вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_MODEL IS 'Техническое состояние вагона [Модель вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.TARE_WEIGHT IS 'Техническое состояние вагона [Масса тары]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LOAD_CAPACITY IS 'Техническое состояние вагона [Грузоподъёмность]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LENGTH_MM IS 'Техническое состояние вагона [Длина (мм)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LAST_CAP_REPAIR_DEPOT IS 'Техническое состояние вагона [Депо последнего капитального ремонта]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LAST_CAP_REPAIR_DT IS 'Техническое состояние вагона [Дата последнего капитального ремонта]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LAST_DEP_REPAIR_DEPOT IS 'Техническое состояние вагона [Депо последнего деповского ремонта]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LAST_DEP_REPAIR_DT IS 'Техническое состояние вагона [Дата последнего деповского ремонта]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.HOME_ROAD IS 'Техническое состояние вагона [Дорога приписки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.HOME_DEPOT IS 'Техническое состояние вагона [Депо приписки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.EXCLUDE_DATE IS 'Техническое состояние вагона [Дата исключения]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.NO_TRANSIT_REASON IS 'Техническое состояние вагона [Причина запрета транзита]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.PREV_WAGON_NO IS 'Техническое состояние вагона [Предыдущий номер вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OWNER IS 'Техническое состояние вагона [Собственник]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OWNER_OKPO IS 'Техническое состояние вагона [ОКПО собственника]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.OWNER_LOCAL_CODE IS 'Техническое состояние вагона [Местный код собственника]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.HOME_STATION IS 'Техническое состояние вагона [Станция приписки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.THRESHOLD_SIGN IS 'Техническое состояние вагона [Признак порога]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LEASE_SIGN IS 'Техническое состояние вагона [Признак аренды]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LIFE_EXT_DATE IS 'Техническое состояние вагона [Дата продления срока службы]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LESSEE IS 'Техническое состояние вагона [Арендатор]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LESSEE_OKPO IS 'Техническое состояние вагона [ОКПО арендатора]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LESSEE_LOCAL_CODE IS 'Техническое состояние вагона [Местный код арендатора]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LEASE_HOME_STATION IS 'Техническое состояние вагона [Станция приписки арендатора]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LEASE_END_DATE IS 'Техническое состояние вагона [Дата окончания аренды]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.SERVICE_LIFE IS 'Техническое состояние вагона [Срок службы (дата окончания)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.BODY_MATERIAL_CODE IS 'Техническое состояние вагона [Код материала кузова]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.BODY_MATERIAL_NAME IS 'Техническое состояние вагона [Материал кузова]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.BODY_VOLUME IS 'Техническое состояние вагона [Объём кузова (м?)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.CLEARANCE IS 'Техническое состояние вагона [Габарит]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.AIR_DIST_TYPE IS 'Техническое состояние вагона [Тип воздухораспределителя]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.AUTOMODE IS 'Техническое состояние вагона [Авторежим]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.AUTO_LEVER IS 'Техническое состояние вагона [Авторычаг]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.BRAKE_TYPE IS 'Техническое состояние вагона [Тип тормоза]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.COUPLER_TYPE IS 'Техническое состояние вагона [Тип автосцепки]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.BOGIE_MODEL IS 'Техническое состояние вагона [Модели тележек]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.SHOCK_ABSORBER IS 'Техническое состояние вагона [Поглощающий аппарат]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LIFE_EXT_SIGN IS 'Техническое состояние вагона [Признак продления срока службы]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.BOILER_CALIBER IS 'Техническое состояние вагона [Калибр котла]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DRAIN_DEVICE IS 'Техническое состояние вагона [Сливной прибор]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.LEVER_GEAR IS 'Техническое состояние вагона [Рычажная передача]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_MODEL_CODE IS 'Техническое состояние вагона [Код модели вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.REPAIR_BY_MILEAGE IS 'Техническое состояние вагона [Ремонт по пробегу]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.PROXY_OPERATOR IS 'Техническое состояние вагона [Оператор по доверенности]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.PROXY_OPERATOR_OKPO IS 'Техническое состояние вагона [ОКПО оператора по доверенности]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_TYPE_CODE2 IS 'Техническое состояние вагона [Тип вагона (дубль)]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.WAGON_TYPE_COND IS 'Техническое состояние вагона [Условный тип вагона]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.AXLES_COUNT IS 'Техническое состояние вагона [Количество осей]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.EXCLUDE_DEPOT IS 'Техническое состояние вагона [Депо исключения]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.EXCLUDE_REASON IS 'Техническое состояние вагона [Причина исключения]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DAYS_TO_REPAIR IS 'Техническое состояние вагона [Дней до ремонта]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DAYS_NO_OPER IS 'Техническое состояние вагона [Дней без операций]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.DAYS_NO_MOVE IS 'Техническое состояние вагона [Дней без движения]';

COMMENT ON COLUMN XX_ETW.XX_DISLOCATION_RJD.ORGANIZATION_ID IS 'Организация, из личного кабинета которой получена справка';


ALTER TABLE XX_ETW.XX_DISLOCATION_RJD ADD (
  PRIMARY KEY
  (ID)
  USING INDEX
    TABLESPACE USERS
    PCTFREE    10
    INITRANS   2
    MAXTRANS   255
    STORAGE    (
                INITIAL          64K
                NEXT             1M
                MINEXTENTS       1
                MAXEXTENTS       UNLIMITED
                PCTINCREASE      0
                FREELISTS        1
                FREELIST GROUPS  1
                BUFFER_POOL      DEFAULT
               )
  ENABLE VALIDATE);


CREATE INDEX XX_ETW.IDX_XX_RJD_CARGO ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, CARGO_NAME)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_CARGO_W ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, CARGO_WEIGHT_KG)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_DEPART ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, DEPART_ROAD, DEPART_STATION)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_DEST ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, DEST_ROAD, DEST_STATION)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_IDLE ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, IDLE_TIME_DAYS)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_OPER ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, OPER_ROAD, OPER_STATION)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_PARK_TYPE ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, PARK_TYPE)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_REPORT_DT ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_TYPE_REF ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, TYPE_REFERENCE)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_WAGON_NO ON XX_ETW.XX_DISLOCATION_RJD
(WAGON_NO)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IDX_XX_RJD_WAGON_TYPE ON XX_ETW.XX_DISLOCATION_RJD
(REPORT_DT, WAGON_TYPE_CODE)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.IX_XX_DISL_ORG_TYPE_DT ON XX_ETW.XX_DISLOCATION_RJD
(ORGANIZATION_ID, TYPE_REFERENCE, REPORT_DT)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

--  There is no statement for index XX_ETW.SYS_C006168503.
--  The object is created when the parent object is created.
CREATE INDEX XX_ETW.XX_DISLOCATION_RJD_N01 ON XX_ETW.XX_DISLOCATION_RJD
(TRUNC("REPORT_DT"))
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.XX_DISLOCATION_RJD_N10 ON XX_ETW.XX_DISLOCATION_RJD
(TYPE_REFERENCE)
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.XX_DISLOCATION_RJD_N2 ON XX_ETW.XX_DISLOCATION_RJD
(UPPER("DEST_STATION"))
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE INDEX XX_ETW.XX_DISLOCATION_RJD_N20 ON XX_ETW.XX_DISLOCATION_RJD
(TRUNC("OPER_DT"))
LOGGING
TABLESPACE USERS
PCTFREE    10
INITRANS   2
MAXTRANS   255
STORAGE    (
            INITIAL          64K
            NEXT             1M
            MINEXTENTS       1
            MAXEXTENTS       UNLIMITED
            PCTINCREASE      0
            FREELISTS        1
            FREELIST GROUPS  1
            BUFFER_POOL      DEFAULT
           );

CREATE OR REPLACE TRIGGER XX_ETW.TRG_XX_DISLOCATION_RJD_ESR
BEFORE INSERT ON XX_ETW.XX_DISLOCATION_RJD
FOR EACH ROW
DECLARE
    v_esr VARCHAR2(100);
BEGIN
    -- Проверяем, что исходное поле dest_station не пустое
    IF :NEW.oper_station IS NOT NULL THEN

        -- Ищем внутри скобок от 1 до 8 цифр
        v_esr := REGEXP_SUBSTR(:NEW.oper_station, '\((\d{1,8})\)', 1, 1, NULL, 1);

        -- Если код ЕСР найден, записываем его в нужное поле
        IF v_esr IS NOT NULL THEN
            :NEW.oper_station_esr_code := trim(v_esr);
        END IF;

    END IF;
END;
/


DROP SEQUENCE XX_ETW.XX_DISLOCATION_RJD_SEQ;

CREATE SEQUENCE XX_ETW.XX_DISLOCATION_RJD_SEQ
  START WITH 4350588
  MAXVALUE 9999999999999999999999999999
  MINVALUE 1
  NOCYCLE
  NOCACHE
  NOORDER;


CREATE OR REPLACE TRIGGER XX_ETW.XX_DISLOCATION_RJD_ID
BEFORE INSERT ON XX_ETW.XX_DISLOCATION_RJD
FOR EACH ROW
BEGIN
  IF :NEW.id IS NULL THEN
    :NEW.id := xx_dislocation_rjd_seq.NEXTVAL;
  END IF;
END;
/


GRANT DELETE, INSERT, UPDATE ON XX_ETW.XX_DISLOCATION_RJD TO XX_PRTL;
