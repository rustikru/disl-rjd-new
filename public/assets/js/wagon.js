(function () {
  const number = document.getElementById('wagonContent').dataset.number;
  const base = document.getElementById('wagonContent').dataset.base;
  // Даты справки без преобразования часового пояса.
  function formatDate(value, dateOnly) {
    if (value === null || value === undefined || value === '') return 'Не указано';
    const text = String(value);
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?/);
    if (!match) return text;
    const date = match[3] + '.' + match[2] + '.' + match[1];
    return dateOnly || !match[4] ? date : date + ' ' + match[4] + ':' + match[5];
  }

  if (!number) return;
  const message = document.getElementById('wagonMessage');
  if (!/^\d{8}$/.test(number)) { message.textContent = 'Введите номер вагона из 8 цифр.'; return; }
  const sections = [
    ['Основная информация', [['wagon_type_code','Тип вагона'],['wagon_state','Состояние'],['park_type','Признак парка'],['owner','Собственник'],['lessee','Арендатор'],['lease_home_station','Станция приписки арендатора']]],
    ['Груз и документы', [['cargo_name','Груз'],['cargo_weight_kg','Вес, кг'],['prev_cargo','Ранее выгруженный груз'],['waybill_no','Накладная'],['consignor_name','Грузоотправитель'],['consignee_name','Грузополучатель']]],
    ['Маршрут и последняя операция', [['depart_station','Станция отправления'],['dest_station','Станция назначения'],['oper_station','Текущая станция'],['oper_road','Дорога операции'],['operation','Операция'],['oper_dt','Дата операции'],['train_no','Номер поезда'],['trip_start_dt','Начало рейса'],['trip_end_dt','Окончание рейса']]],
    ['Технические данные', [['last_dep_repair_dt','Последний деповской ремонт'],['last_dep_repair_depot','Депо ремонта'],['last_cap_repair_dt','Последний капитальный ремонт'],['next_repair_dt','Следующий ремонт'],['next_repair_type','Вид ремонта'],['home_road','Дорога приписки']]]
  ];
  const fields = ['wagon_no','report_dt','type_reference','last_cap_repair_dt','last_dep_repair_depot','next_repair_type','home_road', ...sections.flatMap(section => section[1].map(field => field[0]))];
  message.textContent = 'Загрузка данных вагона…';
  fetch(base + '/api/technical-state/detail?' + new URLSearchParams({wagon_no:number,fields:fields.join(',')}), {headers:{Accept:'application/json'}})
    .then(async response => { if (!response.ok) throw new Error(response.status === 403 ? 'Недостаточно прав для просмотра данных.' : 'Не удалось загрузить данные.'); const type=response.headers.get('content-type')||''; if(!type.includes('application/json')) throw new Error('Сеанс завершён. Войдите в систему повторно.'); return response.json(); })
    .then(data => {
      const row = (data.rows || [])[0];
      if (!row) { message.textContent = 'Вагон не найден в актуальных справках выбранной организации.'; return; }
      message.hidden = true;
      document.getElementById('wagonTitle').textContent = 'Вагон № ' + row.wagon_no;
      document.getElementById('wagonCaption').textContent = (row.wagon_type_code || 'Тип не указан') + ' · Собственник: ' + (row.owner || 'Не указан');
      const value = key => key.endsWith('_dt') ? formatDate(row[key], key.includes('repair')) : (row[key] === null || row[key] === undefined || row[key] === '' ? 'Не указано' : String(row[key]));
      const container = document.getElementById('wagonSections');
      function addSection(parent, title, fields) {
        const section=document.createElement('section'); section.className='wagon-panel';
        const heading=document.createElement('h2');heading.textContent=title;section.append(heading);
        const list=document.createElement('dl');list.className='wagon-data';
        fields.forEach(([key,label])=>{const dt=document.createElement('dt');dt.textContent=label;const dd=document.createElement('dd');dd.textContent=value(key);list.append(dt,dd);});
        section.append(list);parent.append(section);
      }
      ['overview','movement','cargo','technical'].forEach((key,index)=>{
        const panel=document.createElement('div');panel.className='wagon-section';panel.dataset.panel=key;panel.hidden=index!==0;
        if(key==='overview'){
          const route=document.createElement('div');route.className='wagon-route';
          [['Отправление','depart_station'],['Назначение','dest_station']].forEach(([label,field],i)=>{
            if(i){const line=document.createElement('div');line.className='route-direction';line.setAttribute('aria-hidden','true');route.append(line);}
            const station=document.createElement('div');station.className='route-station'+(i?' route-destination':'');
            const details=document.createElement('div');const small=document.createElement('small');small.textContent=label;
            const raw=value(field);const match=raw.match(/^(.*?)\s*\((\d+)\)\s*$/);
            const name=document.createElement('b');name.textContent=match?match[1]:raw;details.append(small,name);
            if(match){const code=document.createElement('div');code.className='route-code';code.textContent='('+match[2]+')';details.append(code);}
            station.append(details);route.append(station);
          });panel.append(route);
          const grid=document.createElement('div');grid.className='wagon-grid';addSection(grid,sections[0][0],sections[0][1]);addSection(grid,'Текущие данные',[['cargo_name','Груз'],['waybill_no','Накладная'],['oper_dt','Дата операции'],['train_no','Номер поезда'],['next_repair_dt','Следующий ремонт']]);panel.append(grid);
        }else{const source=sections[{movement:2,cargo:1,technical:3}[key]];addSection(panel,source[0],source[1]);}
        container.append(panel);
      });
      const historyPanel=document.createElement('div');historyPanel.className='wagon-section';historyPanel.dataset.panel='history';historyPanel.hidden=true;
      const historyStatus=document.createElement('div');historyStatus.className='wagon-caption';historyStatus.setAttribute('role','status');historyPanel.append(historyStatus);container.append(historyPanel);
      let historyLoaded=false;
      async function loadMovement() {
        if(historyLoaded)return;
        historyLoaded=true;historyStatus.textContent='Загрузка операций из сохранённых справок…';
        try {
          const response=await fetch(base+'/api/wagon/movement?'+new URLSearchParams({wagon_no:number}),{headers:{Accept:'application/json'}});
          if(!response.ok)throw new Error('Не удалось загрузить движение вагона.');
          if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('Сеанс завершён. Войдите повторно.');
          const result=await response.json();
          historyStatus.textContent=result.message||('Операций текущего рейса: '+result.rows.length);
          if(!result.rows.length)return;
          const wrap=document.createElement('div');wrap.className='wagon-history';
          const table=document.createElement('table');table.className='data-table';
          const cols=[['oper_dt','Дата и время'],['operation','Операция'],['oper_station','Станция'],['oper_road','Дорога'],['train_no','Поезд'],['waybill_no','Накладная']];
          const head=table.createTHead().insertRow();cols.forEach(([,label])=>{const cell=document.createElement('th');cell.textContent=label;head.append(cell);});
          const body=table.createTBody();result.rows.forEach(item=>{const line=body.insertRow();cols.forEach(([key])=>{const cell=line.insertCell();cell.textContent=key==='oper_dt'?formatDate(item[key],false):(item[key]||'—');});});wrap.append(table);historyPanel.append(wrap);
        }catch(error){historyStatus.textContent=error.message;historyLoaded=false;}
      }
      document.querySelectorAll('.wagon-tab').forEach(button=>button.addEventListener('click',()=>{
        document.querySelectorAll('.wagon-tab').forEach(tab=>{const active=tab===button;tab.classList.toggle('active',active);tab.setAttribute('aria-pressed',String(active));});
        document.querySelectorAll('.wagon-section').forEach(panel=>panel.hidden=panel.dataset.panel!==button.dataset.tab);
        if(button.dataset.tab==='history')loadMovement();
      }));
      document.getElementById('wagonContent').hidden=false;
    }).catch(error=>{message.textContent=error.message;});
})();
