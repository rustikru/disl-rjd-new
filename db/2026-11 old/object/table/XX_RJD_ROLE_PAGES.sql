DROP TABLE XX_ETW.XX_RJD_ROLE_PAGES CASCADE CONSTRAINTS;

CREATE TABLE XX_ETW.XX_RJD_ROLE_PAGES
(
  ROLE_ID  NUMBER                               NOT NULL,
  PAGE     VARCHAR2(40 BYTE)                    NOT NULL
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
