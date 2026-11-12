/* Formatted on 12.11.2026 17:10:23 (QP5 v5.417) */
  select dr.RAILCAR_ID,
         rai.CAR_TYPE,
         rai.round_id,
         SUM (NVL (dcai.WEIGHT_NET, rai.weight_net))     weight_net
    from xx_etw.xx_disl_round dr
         left join xx_etw.xx_disl_railcar_add_info rai
             on dr.round_id = rai.round_id and rai.LAST_RECORD = 'Y'
         left join xx_etw.xx_disl_cont_history dch
             on dch.place_id = dr.round_id and dch.place_type = 'railcar'
         left join xx_etw.xx_disl_cont_round dcr
             on dcr.cont_round_id = dch.cont_round_id
         left join xx_etw.xx_disl_cont_add_info dcai
             on     dcai.cont_round_id = dcr.cont_round_id
                and dcai.LAST_RECORD = 'Y'
   where     1 = 1
         and dr.RAILCAR_ID in ('94483153', '50831551', '54647409')
         and dr.STATION_HIST_ID_OUT is null
group by dr.RAILCAR_ID, rai.CAR_TYPE, rai.round_id