select to_char(OPER_DT,'YYYY-MM-DD HH24:MI:SS') OPER_DT_h, x.*
from xx_rjd_dislocation_archive x
where 1=1
and wagon_no ='50015759'
--group by WAGON_NO, WAYBILL_NO, OPERATION, to_char(OPER_DT,'YYYY-MM-DD HH24:MI:SS'), TRAIN_NO, TRAIN_INDEX
--having count(1) > 1