(function(){
  const search = document.querySelector('.search-input');
  const results = document.querySelector('.search-results');
  const data = window.HANDBOOK_SEARCH || [];
  const toggle = document.querySelector('.menu-toggle');
  const overlay = document.querySelector('.mobile-overlay');
  const norm = s => String(s || '').toLocaleLowerCase('ru-RU').replace(/ё/g,'е');
  function renderSearch(){
    const q = norm(search.value).trim();
    if(!q){results.classList.remove('open');results.innerHTML='';return;}
    const terms=q.split(/\s+/).filter(Boolean);
    const found=data.map(item=>{
      const hay=norm(item.title+' '+item.text);
      const score=terms.reduce((n,t)=>n+(hay.includes(t)?1:0),0);
      return {item,score};
    }).filter(x=>x.score===terms.length).sort((a,b)=>b.score-a.score).slice(0,10);
    results.innerHTML='';
    if(!found.length){
      results.innerHTML='<div class="search-result"><strong>Ничего не найдено</strong><span>Попробуйте изменить запрос.</span></div>';
    }else{
      found.forEach(({item})=>{
        const a=document.createElement('a');a.className='search-result';a.href=item.path;
        const title=document.createElement('strong');title.textContent=item.title;
        const desc=document.createElement('span');desc.textContent=item.text.slice(0,170)+(item.text.length>170?'…':'');
        a.append(title,desc);results.append(a);
      });
    }
    results.classList.add('open');
  }
  if(search){
    search.addEventListener('input',renderSearch);
    search.addEventListener('keydown',e=>{if(e.key==='Escape'){results.classList.remove('open');search.blur();}if(e.key==='Enter'){const first=results.querySelector('a');if(first)location.href=first.href;}});
    document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();search.focus();}});
    document.addEventListener('click',e=>{if(!e.target.closest('.search-wrap'))results.classList.remove('open');});
  }
  function closeNav(){document.body.classList.remove('nav-open');}
  if(toggle)toggle.addEventListener('click',()=>document.body.classList.toggle('nav-open'));
  if(overlay)overlay.addEventListener('click',closeNav);
  document.querySelectorAll('.sidebar a').forEach(a=>a.addEventListener('click',closeNav));
  document.querySelectorAll('.code-block').forEach(pre=>{
    const button=document.createElement('button');button.type='button';button.className='copy-code';button.textContent='Копировать';
    button.addEventListener('click',async()=>{
      const code=pre.innerText;
      try{await navigator.clipboard.writeText(code);button.textContent='Скопировано';}
      catch(e){button.textContent='Не удалось';}
      setTimeout(()=>button.textContent='Копировать',1600);
    });
    pre.append(button);
  });
})();