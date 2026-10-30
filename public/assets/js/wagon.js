(function () {
  const number = document.getElementById('wagonContent').dataset.number;
  const base = document.getElementById('wagonContent').dataset.base;
  const config = window.APP_WAGON_CONFIG || {};
  const back = document.getElementById('wagonBack');
  back.addEventListener('click', function (event) {
    if (document.referrer && new URL(document.referrer).origin === window.location.origin && window.history.length > 1) {
      event.preventDefault();
      window.history.back();
    }
  });

  // Формат даты для отображения.
  function formatDate(value, dateOnly) {
    if (value === null || value === undefined || value === '') return 'Не указано';
    const text = String(value);
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?/);
    if (!match) return text;
    const date = match[3] + '.' + match[2] + '.' + match[1];
    return dateOnly || !match[4] ? date : date + ' ' + match[4] + ':' + match[5];
  }

  function routeDistance(value) {
    if (value === null || value === undefined || String(value).trim() === '') return null;
    const distance = Number(value);
    return Number.isFinite(distance) && distance >= 0 ? distance : null;
  }

  function createRouteProgress(row) {
    const progressFields = config.progress || [];
    const passedKm = routeDistance(row[progressFields[0].key]);
    const remainingKm = routeDistance(row[progressFields[1].key]);
    const hasDistances = passedKm !== null && remainingKm !== null && passedKm + remainingKm > 0;
    const progress = document.createElement('div');
    progress.className = 'route-progress';
    const line = document.createElement('div');
    line.className = 'route-direction';
    line.setAttribute('aria-hidden', 'true');
    if (hasDistances) {
      const percent = passedKm / (passedKm + remainingKm) * 100;
      const fill = document.createElement('div');
      fill.className = 'route-passed';
      fill.style.width = percent + '%';
      const point = document.createElement('div');
      point.className = 'route-current';
      point.style.left = percent + '%';
      line.append(fill, point);
    }
    const distances = document.createElement('div');
    distances.className = 'route-distances';
    [[progressFields[0].label, passedKm], [progressFields[1].label, remainingKm]].forEach(([label, distance]) => {
      const text = document.createElement('span');
      text.textContent = label + ': ' + (distance === null ? 'нет данных' : distance.toLocaleString('ru-RU') + ' км');
      distances.append(text);
    });
    progress.append(distances, line);
    if (row.oper_station) {
      const station = document.createElement('div');
      station.className = 'route-current-station';
      const stationName = document.createElement('div');
      stationName.textContent = row.oper_station;
      station.append(stationName);
      if (row.oper_road) {
        const road = document.createElement('div');
        road.className = 'route-road';
        road.textContent = row.oper_road;
        station.append(road);
      }
      station.title = 'Текущая станция';
      if (hasDistances) {
        const percent = passedKm / (passedKm + remainingKm) * 100;
        station.style.left = percent + '%';
        station.style.transform = 'translateX(-' + percent + '%)';
      }
      const caption = document.createElement('div');
      caption.className = 'route-station-caption';
      caption.append(station);
      progress.append(caption);
    }
    return progress;
  }

  if (!number) return;
  const message = document.getElementById('wagonMessage');
  if (!/^\d{8}$/.test(number)) { message.textContent = 'Введите номер вагона из 8 цифр.'; return; }
  const fields = config.fields || [];
  message.textContent = 'Загрузка данных вагона…';
  fetch(base + '/api/technical-state/detail?' + new URLSearchParams({wagon_no:number,fields:fields.join(',')}), {headers:{Accept:'application/json'}})
    .then(async response => { if (!response.ok) throw new Error(response.status === 403 ? 'Недостаточно прав для просмотра данных.' : 'Не удалось загрузить данные.'); const type=response.headers.get('content-type')||''; if(!type.includes('application/json')) throw new Error('Сеанс завершён. Войдите в систему повторно.'); return response.json(); })
    .then(data => {
      const row = (data.rows || [])[0];
      if (!row) { message.textContent = 'Вагон не найден в актуальных справках выбранной организации.'; return; }
      message.hidden = true;
      const header = config.header || {};
      document.getElementById('wagonTitle').textContent = header.number.prefix + row[header.number.key];
      document.getElementById('wagonCaption').textContent = (row[header.type.key] || header.type.empty) + ' · ' + header.owner.label + ': ' + (row[header.owner.key] || header.owner.empty);
      const value = key => key.endsWith('_dt') ? formatDate(row[key], (config.date_only || []).indexOf(key) !== -1) : (row[key] === null || row[key] === undefined || row[key] === '' ? 'Не указано' : String(row[key]));
      const container = document.getElementById('wagonSections');
      function addSection(parent, sectionConfig) {
        const section=document.createElement('section'); section.className='wagon-panel';
        const heading=document.createElement('h2');heading.textContent=sectionConfig.title;section.append(heading);
        const list=document.createElement('dl');list.className='wagon-data';
        sectionConfig.fields.forEach(field=>{const labelElement=document.createElement('dt');labelElement.textContent=field.label;const valueElement=document.createElement('dd');valueElement.textContent=value(field.key);list.append(labelElement,valueElement);});
        section.append(list);parent.append(section);
      }
      (config.tabs || []).forEach((tab,index)=>{
        const panel=document.createElement('div');panel.className='wagon-section';panel.dataset.panel=tab.key;panel.hidden=index!==0;
        if(tab.history){
          const historyStatus=document.createElement('div');historyStatus.className='wagon-caption';historyStatus.setAttribute('role','status');panel.append(historyStatus);container.append(panel);
          return;
        }
        if(tab.key==='overview'){
          const route=document.createElement('div');route.className='wagon-route';
          (config.route || []).forEach((point,i)=>{
            if(i) route.append(createRouteProgress(row));
            const station=document.createElement('div');station.className='route-station'+(i?' route-destination':'');
            const details=document.createElement('div');const labelElement=document.createElement('small');labelElement.textContent=point.label;
            const stationText=value(point.station);const match=stationText.match(/^(.*?)\s*\((\d+)\)\s*$/);
            const name=document.createElement('b');name.textContent=match?match[1]:stationText;details.append(labelElement,name);
            if(match){const code=document.createElement('div');code.className='route-code';code.textContent='('+match[2]+')';details.append(code);}
            const roadName = row[point.road];
            if (roadName) {
              const road = document.createElement('div');
              road.className = 'route-road';
              road.textContent = roadName;
              details.append(road);
            }
            station.append(details);route.append(station);
          });panel.append(route);
        }
        addSection(panel, config.sections[tab.section]);
        container.append(panel);
      });
      const historyPanel=container.querySelector('[data-panel="history"]');
      const historyStatus=historyPanel.querySelector('[role="status"]');
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
          const tableContainer=document.createElement('div');tableContainer.className='wagon-history';
          const table=document.createElement('table');table.className='data-table';
          const columns=config.history || [];
          const head=table.createTHead().insertRow();columns.forEach(field=>{const cell=document.createElement('th');cell.textContent=field.label;head.append(cell);});
          const body=table.createTBody();result.rows.forEach(item=>{const line=body.insertRow();columns.forEach(field=>{const cell=line.insertCell();cell.textContent=field.key==='oper_dt'?formatDate(item[field.key],false):(item[field.key]||'—');});});tableContainer.append(table);historyPanel.append(tableContainer);
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
