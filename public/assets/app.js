(() => {
  'use strict';
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = value => {const cents=Math.max(0,Math.trunc(Number(value)||0));return 'R '+Math.trunc(cents/100).toLocaleString('en-US').replace(/,/g,' ')+'.'+String(cents%100).padStart(2,'0');};
  const statusText = value => String(value || '').replace(/_/g, ' ');
  const icon = name => `<svg class="icon" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${({arrow:'M5 12h14m-6-6 6 6-6 6',heart:'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z',plus:'M12 5v14M5 12h14',compare:'M6 3v18M18 3v18M3 7h6m6 10h6',cart:'M3 3h2l3 12h10l3-9H6M9 20h.01M18 20h.01',close:'m6 6 12 12M6 18 18 6'})[name] || ''}"/></svg>`;
  let csrf = $('meta[name="csrf-token"]')?.content || '';
  let toastTimer;
  function toast(message, error = false) {
    const node = $('.toast'); if (!node) return;
    node.textContent = message; node.classList.toggle('error', error); node.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(() => { node.hidden = true; }, 5000);
  }
  async function api(path, body, options = {}) {
    const headers = {Accept:'application/json'};
    if (body !== undefined) { headers['Content-Type']='application/json'; headers['X-CSRF-Token']=csrf; }
    const response = await fetch(path, {method:body === undefined ? 'GET':'POST', credentials:'same-origin', headers, body:body === undefined ? undefined:JSON.stringify(body), signal:options.signal});
    let data; try { data=await response.json(); } catch { throw new Error('The server returned an unreadable response. Please try again.'); }
    if (!response.ok) { const error=new Error(data.error || 'The request could not be completed.'); error.status=response.status; throw error; }
    if (data.csrf) { csrf=data.csrf; const meta=$('meta[name="csrf-token"]');if(meta)meta.content=csrf;$$('input[name="_csrf"]').forEach(el=>el.value=csrf); }
    return data;
  }
  async function action(button, fn) {
    if (button?.disabled) return;
    if (button) {button.disabled=true;button.setAttribute('aria-busy','true');}
    try {return await fn();} catch(error) {toast(error.message,true);return null;} finally {if(button){button.disabled=false;button.removeAttribute('aria-busy');}}
  }
  function updateCartCount(cart) {$$('.cart-count').forEach(node=>node.textContent=String(cart.count || 0));}
  function productPath(p) {return '/products/'+encodeURIComponent(p.slug);}
  function safeImage(value) {return /^\/assets\/[a-zA-Z0-9/_.-]+$/.test(value || '') ? value:'/assets/product-placeholder.svg';}
  function card(p) {
    const specs=Object.values(p.specs || {}).filter(x=>typeof x !== 'object').slice(0,3).join(' · ');
    return `<article class="product-card" data-product-id="${Number(p.id)}"><div class="product-visual"><a href="${productPath(p)}" tabindex="-1" aria-hidden="true"><img src="${esc(safeImage(p.image))}" alt="" loading="lazy" width="400" height="320"></a><button class="icon-button save-button" data-wishlist="${Number(p.id)}" aria-label="Save ${esc(p.name)}">${icon('heart')}</button><button class="quick-view" data-quickview="${esc(p.slug)}">Quick view</button></div><div class="product-body"><p class="brand-label">${esc(p.brand)}</p><h3><a href="${productPath(p)}">${esc(p.name)}</a></h3><p class="card-specs">${esc(specs)}</p><div class="stock-label ${p.stock>0?'available':'unavailable'}"><i></i>${document.body.dataset.demo==='true'?(p.stock>0?'Demo stock available':'Unavailable in preview'):(p.stock>0?'Available':'Check availability')}</div><div class="card-bottom"><div class="price"><strong>${money(p.price_cents)}</strong>${p.old_price_cents>p.price_cents?`<s>${money(p.old_price_cents)}</s>`:''}</div><button class="add-button" data-add="${Number(p.variant_id)}" aria-label="Add ${esc(p.name)} to basket" ${p.stock<1?'disabled':''}>${icon('plus')}</button></div><button class="compare-button" data-compare="${Number(p.id)}" data-slug="${esc(p.slug)}">${icon('compare')} Compare</button></div></article>`;
  }
  let focusBeforeDialog;
  function openDialog(html, dialog = $('#quick-view')) {
    if(!dialog)return;focusBeforeDialog=document.activeElement;
    const content=$('.dialog-content',dialog);if(content && html!==undefined)content.innerHTML=html;
    if(!dialog.open)dialog.showModal();
  }
  function closeDialog(dialog) {if(dialog?.open)dialog.close();focusBeforeDialog?.focus();}
  $$('dialog').forEach(dialog=>{
    dialog.addEventListener('click',event=>{if(event.target===dialog)closeDialog(dialog);});
    dialog.addEventListener('close',()=>focusBeforeDialog?.focus());
  });
  async function quickView(slug) {
    const p=await api('/api/products/'+encodeURIComponent(slug));
    openDialog(`<div class="quick-content"><img src="${esc(safeImage(p.image))}" alt="${esc(p.name)}" width="400" height="400"><section><p class="brand-label">${esc(p.brand)}</p><h2>${esc(p.name)}</h2><p>${esc(Object.values(p.specs || {}).filter(x=>typeof x!=='object').slice(0,4).join(' · '))}</p><div class="detail-price"><strong>${money(p.price_cents)}</strong></div><p class="catalog-note">Catalog snapshot. ${document.body.dataset.demo==='true'?'Demonstration inventory.':'Availability requires verification.'}</p><button class="button primary" data-add="${Number(p.variant_id)}" ${p.stock<1?'disabled':''}>Add to basket ${icon('cart')}</button><a class="button secondary" href="${productPath(p)}">View full details ${icon('arrow')}</a></section></div>`);
  }
  let compared=[];
  let compareDifferencesOnly=false;
  try {compared=JSON.parse(localStorage.getItem('crm-compare') || '[]').filter(p=>p && typeof p.slug==='string').slice(0,4);} catch {}
  function syncCompare() {
    try{localStorage.setItem('crm-compare',JSON.stringify(compared));}catch{}
    $$('.compare-count').forEach(el=>{el.textContent=String(compared.length);el.hidden=!compared.length;});
    $$('[data-compare]').forEach(el=>{const chosen=compared.some(p=>Number(p.id)===Number(el.dataset.compare));el.classList.toggle('selected',chosen);el.setAttribute('aria-pressed',String(chosen));});
  }
  async function renderCompare() {
    const node=$('#compare-content');if(!node)return;
    if(!compared.length){node.innerHTML='<div class="empty-state"><h2>A clearer view of your options.</h2><p>Use Compare on any product to add up to four choices.</p><a class="button primary" href="/catalog">Explore the collection</a></div>';return;}
    node.setAttribute('aria-busy','true');
    try {
      const results=await Promise.all(compared.map(p=>api('/api/products/'+encodeURIComponent(p.slug)).catch(()=>null)));
      const list=results.filter(Boolean);if(!list.length){compared=[];syncCompare();return renderCompare();}
      const keys=[...new Set(list.flatMap(p=>Object.keys(p.specs || {})))];
      const displaySpec = value => value === undefined || value === null || value === '' ? 'Not supplied' : (Array.isArray(value) ? value.join(', ') : (typeof value === 'object' ? JSON.stringify(value) : String(value)));
      const demo=document.body.dataset.demo==='true';
      const rows=[
        {label:'Recorded SKU',values:list.map(p=>displaySpec(p.sku))},
        {label:'Availability',values:list.map(p=>demo?(p.stock>0?'Demo stock available':'Unavailable in preview'):(p.stock>0?'Available':'Check availability'))},
        ...keys.map(key=>({label:key,values:list.map(p=>displaySpec(p.specs?.[key]))}))
      ].map(row=>({...row,equal:list.length>1&&row.values.every(value=>value===row.values[0])}));
      const matching=rows.filter(row=>row.equal).length;
      if(list.length<2)compareDifferencesOnly=false;
      node.classList.toggle('differences-only',compareDifferencesOnly);
      node.innerHTML=`${list.length>1?`<div class="comparison-controls"><label><input type="checkbox" data-compare-differences ${compareDifferencesOnly?'checked':''}> Show differences only</label><span data-comparison-summary>${compareDifferencesOnly?matching+' matching details hidden':'All recorded details shown'}</span></div>`:''}<div class="table-scroll compare-scroll compare-count-${list.length}"><table class="compare-table"><colgroup><col class="compare-label-column">${list.map(()=>'<col class="compare-product-column">').join('')}</colgroup><thead><tr><th scope="col" class="compare-label-heading">Compare your choices</th>${list.map(p=>`<th scope="col" class="compare-product-heading"><button class="remove-link" data-remove-compare="${Number(p.id)}" aria-label="Remove ${esc(p.name)} from comparison">Remove</button><img src="${esc(safeImage(p.image))}" alt="" width="200" height="160"><p class="brand-label">${esc(p.brand)}</p><h2><a href="${productPath(p)}">${esc(p.name)}</a></h2><strong class="compare-price">${money(p.price_cents)}</strong><button class="button primary" data-add="${Number(p.variant_id)}" ${p.stock<1?'disabled':''}>Add to basket</button></th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr data-equal="${row.equal}"><th scope="row">${esc(row.label)}</th>${row.values.map(value=>`<td>${esc(value)}</td>`).join('')}</tr>`).join('')}</tbody></table></div><p class="catalog-note">Missing specifications are shown as not supplied. Prices are recorded catalog snapshots.</p>`;
    } finally {node.removeAttribute('aria-busy');}
  }
  function formData(form) {
    const data=Object.fromEntries(new FormData(form));
    $$('input[type="checkbox"][name]',form).forEach(input=>data[input.name]=input.checked);
    for(const key of ['specs','attributes'])if(typeof data[key]==='string'&&data[key].trim()){
      try{data[key]=JSON.parse(data[key]);}catch{throw new Error(`${key} must be a valid JSON object.`);}
      if(!data[key]||Array.isArray(data[key])||typeof data[key]!=='object')throw new Error(`${key} must be a JSON object.`);
    }
    if(data.id==='')delete data.id;
    return data;
  }
  function formMessage(form,text,error=false){const el=$('.form-message',form);if(el){el.hidden=false;el.textContent=text;el.classList.toggle('error',error);el.classList.toggle('success',!error);}}
  function checkoutSuccess(order) {
    const layout=$('.checkout-layout');if(layout)layout.hidden=true;
    const node=$('#checkout-success');if(!node)return;
    node.hidden=false;node.className='success-panel';
    node.innerHTML=`<p class="eyebrow">${order.is_demo?'TEST ORDER CREATED':'ORDER REQUEST RECEIVED'}</p><h2>All your choices,<br>in one place.</h2><p>Reference <strong>${esc(order.number)}</strong></p><div class="order-summary"><div><span>Order status</span><strong>${esc(statusText(order.status))}</strong></div><div><span>Items total</span><strong>${money(order.total_cents)}</strong></div></div><p>${order.is_demo?'This is a test order. No payment has been collected. Do not make a bank transfer.':'Payment receipt and delivery arrangements require confirmation.'}</p><a class="button primary" href="/account">Your account ${icon('arrow')}</a><a class="button secondary" href="/catalog">Keep exploring</a>`;
    node.scrollIntoView({behavior:'smooth',block:'start'});updateCartCount({count:0});
  }
  async function ticketThread(id){
    const node=$(`[data-ticket-messages="${Number(id)}"]`);if(!node)return;
    const data=await api('/api/support/'+Number(id));
    node.innerHTML=data.messages.map(m=>`<article class="ticket-message"><strong>${esc(m.author || 'Customer')}${m.internal?' · Internal note':''}</strong><small>${esc(m.created_at)}</small><p>${esc(m.message).replace(/\n/g,'<br>')}</p></article>`).join('');
  }
  document.addEventListener('submit',async event=>{
    const form=event.target;if(!(form instanceof HTMLFormElement)||!form.matches('.api-form'))return;
    event.preventDefault();const button=$('button[type="submit"],button:not([type])',form);
    if(form.dataset.submitting)return;form.dataset.submitting='true';if(button)button.disabled=true;
    try{
      const data=await api(form.dataset.endpoint,formData(form));
      formMessage(form,data.message || 'Your changes have been saved.');
      if(form.dataset.endpoint==='/api/checkout'){checkoutSuccess(data.order);return;}
      if(form.dataset.endpoint==='/api/cart/coupon'){location.reload();return;}
      if(form.dataset.endpoint.includes('/reply')){await ticketThread(form.dataset.endpoint.split('/')[3]);$('textarea',form).value='';}
      if(form.dataset.endpoint==='/api/support'){formMessage(form,`Ticket ${data.number} has been created. You can follow it in your account.`);form.reset();}
      if(form.dataset.endpoint==='/api/dealers/apply'){formMessage(form,'Your application is pending administrator review. You can track it in your account.');}
      if(form.dataset.redirect){location.assign(form.dataset.redirect);return;}
      if(form.dataset.endpoint==='/api/auth/reset')formMessage(form,'Password updated. You can now sign in.');
    }catch(error){formMessage(form,error.message,true);}
    finally{delete form.dataset.submitting;if(button)button.disabled=false;}
  });
  document.addEventListener('click',event=>{
    const button=event.target.closest('button,[data-admin-edit]');if(!button)return;
    if(button.matches('.mobile-filter-toggle')){const expanded=button.getAttribute('aria-expanded')!=='true';button.setAttribute('aria-expanded',String(expanded));button.closest('.filter-panel')?.classList.toggle('filters-open',expanded);return;}
    if(button.matches('[data-close-dialog]')){closeDialog(button.closest('dialog'));return;}
    if(button.matches('[data-add]')){
      action(button,async()=>{const quantity=button.id==='detail-add'?Number($('#product-quantity')?.value || 1):1;const cart=await api('/api/cart/add',{variant_id:Number(button.dataset.add),quantity});updateCartCount(cart);toast('Added to your basket.');});return;
    }
    if(button.matches('[data-cart-remove]')){action(button,async()=>{await api('/api/cart/items',{variant_id:Number(button.dataset.cartRemove),quantity:0});location.reload();});return;}
    if(button.matches('[data-wishlist]')){
      action(button,async()=>{const result=await api('/api/wishlist',{product_id:Number(button.dataset.wishlist)});const saved=result.products.some(p=>Number(p.id)===Number(button.dataset.wishlist));button.setAttribute('aria-pressed',String(saved));button.classList.toggle('selected',saved);toast(saved?'Saved to your collection.':'Removed from your collection.');if(document.body.dataset.page==='wishlist')location.reload();});return;
    }
    if(button.matches('[data-compare]')){
      const id=Number(button.dataset.compare),found=compared.some(p=>Number(p.id)===id);
      if(found)compared=compared.filter(p=>Number(p.id)!==id);else if(compared.length<4)compared.push({id,slug:button.dataset.slug});else{toast('You can compare up to four products. Remove one to add another.',true);return;}
      syncCompare();toast(found?'Removed from comparison.':'Added to comparison. Open Compare to view your choices.');return;
    }
    if(button.matches('[data-remove-compare]')){compared=compared.filter(p=>Number(p.id)!==Number(button.dataset.removeCompare));syncCompare();renderCompare().catch(e=>toast(e.message,true));return;}
    if(button.matches('[data-quickview]')){action(button,()=>quickView(button.dataset.quickview));return;}
    if(button.matches('.image-zoom')){const image=$('img',button);openDialog(`<img class="zoom-image" src="${esc(image.src)}" alt="${esc(image.alt)}"><p>${esc(image.alt)}</p>`);return;}
    if(button.matches('[data-quantity]')){const input=$('#product-quantity');if(input&&!input.disabled)input.value=String(Math.max(Number(input.min)||1,Math.min(Number(input.max)||99,Number(input.value || 1)+Number(button.dataset.quantity))));return;}
    if(button.matches('[data-view]')){$$('.view-toggle').forEach(el=>el.setAttribute('aria-pressed',String(el===button)));$('.catalog-grid')?.classList.toggle('list-view',button.dataset.view==='list');return;}
    if(button.matches('[data-featured]')){
      action(button,async()=>{const data=await api('/api/products?limit=4'+(button.dataset.featured?'&category='+encodeURIComponent(button.dataset.featured):''));$('.featured-products').innerHTML=data.products.map(card).join('');$$('[data-featured]').forEach(el=>el.classList.toggle('active',el===button));syncCompare();});return;
    }
    if(button.matches('[data-quote-cart]')){action(button,async()=>{const quote=await api('/api/quotes',{source:'cart'});const node=$('#quote-result');if(node)node.innerHTML=`<a class="button primary full" href="${esc(quote.pdf_url)}">Download ${esc(quote.number)}</a><p>Draft quote created. Merchant review required.</p>`;toast('Your quotation is ready to download.');});return;}
    if(button.matches('.mobile-menu-toggle')){const expanded=button.getAttribute('aria-expanded')!=='true';button.setAttribute('aria-expanded',String(expanded));document.body.classList.toggle('menu-open',expanded);$('.site-header')?.classList.toggle('menu-open',expanded);$('.category-nav')?.classList.toggle('is-open',expanded);return;}
    if(button.matches('.assistant-launcher,.assistant-close')){const panel=$('.assistant-panel'),open=button.matches('.assistant-launcher')?panel.hidden:false;panel.hidden=!open;$('.assistant-launcher')?.setAttribute('aria-expanded',String(open));if(open)$('#assistant-message')?.focus();return;}
    if(button.matches('[data-order-view]')){action(button,async()=>{const o=await api('/api/orders/'+Number(button.dataset.orderView));openDialog(`<p class="eyebrow">${o.is_demo?'TEST ORDER':'ORDER'}</p><h2>${esc(o.number)}</h2><p>${esc(statusText(o.status))}</p>${o.items.map(item=>`<div class="account-row"><span>${esc(item.name)}<small>Quantity ${Number(item.quantity)}</small></span><strong>${money(item.line_total_cents)}</strong></div>`).join('')}<p><strong>Items total ${money(o.total_cents)}</strong></p><p>Delivery and payment verification are separate from this confirmation.</p>`);});return;}
    if(button.matches('[data-admin-edit],[data-admin-new-product]')){
      action(button,async()=>{const dialog=$('#admin-product-dialog'),form=$('form',dialog);form.reset();$('input[name="id"]',form).value='';$('textarea[name="specs"]',form).value='{}';
        if(button.dataset.adminEdit){const p=await api('/api/products/'+encodeURIComponent(button.dataset.adminEdit));for(const [key,value] of Object.entries(p)){const input=form.elements.namedItem(key);if(input && !(input instanceof RadioNodeList)){if(input.type==='checkbox')input.checked=Boolean(value);else input.value=key==='specs'?JSON.stringify(value,null,2):String(value??'');}}}
        const skuInput=form.elements.namedItem('sku');if(skuInput)skuInput.required=!button.dataset.adminEdit;const heading=$('h2',dialog);if(heading)heading.textContent=button.dataset.adminEdit?'Edit product':'Add product';$('.form-message',form).hidden=true;openDialog(undefined,dialog);
      });return;
    }
  });
  document.addEventListener('change',event=>{
    const el=event.target;
    if(el.matches('[data-autosubmit]'))el.form.requestSubmit();
    if(el.matches('[data-cart-quantity]'))action(null,async()=>{await api('/api/cart/items',{variant_id:Number(el.dataset.cartQuantity),quantity:Number(el.value)});location.reload();});
    if(el.matches('[data-compare-differences]')){
      compareDifferencesOnly=el.checked;const content=$('#compare-content');content?.classList.toggle('differences-only',el.checked);
      const summary=$('[data-comparison-summary]');if(summary)summary.textContent=el.checked?$$('tbody tr[data-equal="true"]',content).length+' matching details hidden':'All recorded details shown';
    }
    if(el.id==='product-quantity'&&!el.disabled)el.value=String(Math.max(Number(el.min)||1,Math.min(Number(el.max)||99,Number(el.value)||1)));
    if(el.id==='variant-select'){
      const selected=el.selectedOptions[0];if(!selected)return;
      const stock=Math.max(0,Number(selected.dataset.stock)||0),available=stock>0;
      const add=$('#detail-add');if(add){add.dataset.add=el.value;add.disabled=!available;}
      const price=$('[data-product-price]');if(price)price.textContent=money(selected.dataset.price);
      const sku=$('[data-product-sku]');if(sku)sku.textContent=selected.dataset.sku||'Not supplied';
      const oldPrice=$('[data-product-old-price]');if(oldPrice){oldPrice.textContent=money(selected.dataset.oldPrice);oldPrice.hidden=Number(selected.dataset.oldPrice||0)<=Number(selected.dataset.price||0);}
      const stockLabel=$('#product-stock');if(stockLabel){stockLabel.classList.toggle('available',available);stockLabel.classList.toggle('unavailable',!available);stockLabel.innerHTML='<i></i>';stockLabel.append(document.createTextNode(document.body.dataset.demo==='true'?(available?'Preview inventory · test purchases only':'Unavailable in preview'):(available?'Available':'Availability to be confirmed')));}
      const quantity=$('#product-quantity');if(quantity){quantity.max=String(Math.max(1,Math.min(99,stock)));quantity.disabled=!available;quantity.value=String(Math.max(1,Math.min(Number(quantity.max),Number(quantity.value)||1)));}
      $$('[data-quantity]').forEach(button=>button.disabled=!available);
    }
  });
  $$('.ticket-details').forEach(detail=>detail.addEventListener('toggle',()=>{if(detail.open)ticketThread(detail.dataset.ticket).catch(e=>toast(e.message,true));}));

  // Search suggestions remain ordinary product links and work with keyboard input.
  const search=$('#search'),suggestions=$('#search-suggestions');let searchTimer,searchAbort,activeSuggestion=-1;
  function hideSuggestions(){if(suggestions)suggestions.hidden=true;search?.setAttribute('aria-expanded','false');activeSuggestion=-1;}
  if(search && suggestions){
    search.addEventListener('input',()=>{clearTimeout(searchTimer);searchAbort?.abort();if(search.value.trim().length<2){hideSuggestions();return;}
      searchTimer=setTimeout(async()=>{searchAbort=new AbortController();try{const data=await api('/api/products?q='+encodeURIComponent(search.value.trim())+'&limit=5',undefined,{signal:searchAbort.signal});suggestions.innerHTML=data.products.length?data.products.map(p=>`<a href="${productPath(p)}"><img src="${esc(safeImage(p.image))}" alt="" width="44" height="44"><span>${esc(p.name)}<small>${esc(p.brand)}</small></span><strong>${money(p.price_cents)}</strong></a>`).join(''):`<p>No matching products. Try a brand, model or category.</p>`;suggestions.hidden=false;search.setAttribute('aria-expanded','true');activeSuggestion=-1;}catch(e){if(e.name!=='AbortError')hideSuggestions();}},220);
    });
    search.addEventListener('keydown',event=>{const links=$$('a',suggestions);if(event.key==='Escape'){hideSuggestions();return;}if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();activeSuggestion=Math.max(0,Math.min(links.length-1,activeSuggestion+(event.key==='ArrowDown'?1:-1)));links[activeSuggestion]?.focus();}});
    suggestions.addEventListener('keydown',event=>{const links=$$('a',suggestions),index=links.indexOf(document.activeElement);if(event.key==='Escape'){hideSuggestions();search.focus();}if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();const next=index+(event.key==='ArrowDown'?1:-1);if(next<0)search.focus();else links[Math.min(next,links.length-1)]?.focus();}});
    document.addEventListener('click',event=>{if(!event.target.closest('.header-search'))hideSuggestions();});
  }
  document.addEventListener('keydown',event=>{if(event.key==='/'&&!event.ctrlKey&&!event.metaKey&&!event.altKey&&!event.target.matches('input,textarea,select,[contenteditable]')){event.preventDefault();search?.focus();}if(event.key==='Escape'){$('.assistant-panel')?.setAttribute('hidden','');$('.assistant-launcher')?.setAttribute('aria-expanded','false');document.body.classList.remove('menu-open');$('.site-header')?.classList.remove('menu-open');$('.category-nav')?.classList.remove('is-open');$('.mobile-menu-toggle')?.setAttribute('aria-expanded','false');}});
  $('#assistant-form')?.addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget,input=$('#assistant-message'),button=$('button',form),messages=$('.assistant-messages'),question=input.value.trim();if(!question)return;
    await action(button,async()=>{const own=document.createElement('p');own.className='user-message';own.textContent=question;messages.append(own);input.value='';const answer=await api('/api/assistant',{message:question});const response=document.createElement('div');response.className='assistant-answer';response.innerHTML=`<p>${esc(answer.message)}</p>${(answer.products||[]).map(p=>`<a class="assistant-product" href="${productPath(p)}"><img src="${esc(safeImage(p.image))}" alt="" width="48" height="48"><span>${esc(p.name)}<strong>${money(p.price_cents)}</strong></span></a>`).join('')}`;messages.append(response);messages.scrollTop=messages.scrollHeight;});
  });

  // PC configuration prices and compatibility always come from server validation.
  let buildRequest=0,buildToken=null,lastBuild=null;
  const buildSelects=$$('.component-select');
  function buildPayload(){const components={};buildSelects.forEach(el=>{if(el.value)components[el.dataset.group]=Number(el.value);});return{components,name:$('#build-name')?.value.trim() || 'My next PC',purpose:$('#build-purpose')?.value || 'Fully Custom',budget_cents:Math.max(0,Math.round(Number($('#build-budget')?.value || 0)*100))};}
  function showBuild(data){
    lastBuild=data;$('#build-total').textContent=money(data.total_cents);$('#build-watts').textContent=String(data.estimated_watts)+' W';$('#build-count').textContent=Object.keys(data.components||{}).length+' / 8';$('#build-status').textContent=({incompatible:'Some parts need a different match',needs_review:'Technical review needed',checks_passed:'Supplied checks passed'})[data.status] || 'Awaiting your selection';
    $('.compatibility-status')?.classList.toggle('incompatible',data.status==='incompatible');
    $('#build-warnings').innerHTML=(data.warnings||[]).map(text=>`<p class="build-warning">${esc(text)}</p>`).join('');$('#build-missing').innerHTML=(data.missing||[]).map(text=>`<li>${esc(text)}</li>`).join('');
    const budget=buildPayload().budget_cents;$('#build-budget-status').textContent=budget?(data.total_cents>budget?money(data.total_cents-budget)+' over your budget':money(budget-data.total_cents)+' remaining in your budget'):'Set a budget to track your choices.';
    $('#build-cart').disabled=data.status==='incompatible'||!Object.keys(data.components||{}).length;
  }
  async function validateBuild(){if(!buildSelects.length)return;const request=++buildRequest;buildToken=null;const data=await api('/api/build/validate',buildPayload());if(request===buildRequest)showBuild(data);}
  async function saveBuild(){const saved=await api('/api/build/save',buildPayload());buildToken=saved.share_token;const url=location.origin+'/builder?build='+encodeURIComponent(buildToken);$('#build-result').innerHTML=`<p>Build saved. <a href="${esc(url)}">Open your saved build</a></p>`;return url;}
  if(buildSelects.length){
    buildSelects.forEach(el=>el.addEventListener('change',()=>validateBuild().catch(e=>toast(e.message,true))));
    $('#build-budget')?.addEventListener('input',()=>{if(lastBuild)showBuild(lastBuild);});
    $('#build-save')?.addEventListener('click',event=>action(event.currentTarget,async()=>{await saveBuild();toast('Your build has been saved.');}));
    $('#build-share')?.addEventListener('click',event=>action(event.currentTarget,async()=>{const url=buildToken?location.origin+'/builder?build='+encodeURIComponent(buildToken):await saveBuild();try{await navigator.clipboard.writeText(url);toast('Build link copied.');}catch{$('#build-result').innerHTML=`<label class="field"><span>Share this build link</span><input readonly value="${esc(url)}"></label>`;$('#build-result input')?.select();}}));
    $('#build-cart')?.addEventListener('click',event=>action(event.currentTarget,async()=>{const cart=await api('/api/build/cart',buildPayload());updateCartCount(cart);$('#build-result').innerHTML='<p>Your selected parts are in your basket. <a href="/cart">Review your basket</a></p>';toast('Build added to your basket.');}));
    $('#build-quote')?.addEventListener('click',event=>action(event.currentTarget,async()=>{const quote=await api('/api/quotes',{source:'build',...buildPayload()});$('#build-result').innerHTML=`<a class="button primary full" href="${esc(quote.pdf_url)}">Download ${esc(quote.number)}</a><p>Draft quote ready. Merchant review required.</p>`;}));
    const token=new URLSearchParams(location.search).get('build');
    if(token){api('/api/build/'+encodeURIComponent(token)).then(data=>{for(const [group,p] of Object.entries(data.components||{})){const input=$('#part-'+group);if(input)input.value=String(p.variant_id);}$('#build-name').value=data.name;$('#build-budget').value=String(Number(data.budget_cents)/100);$('#build-purpose').value=data.purpose;buildToken=token;showBuild(data);}).catch(e=>toast(e.message,true));}
    else validateBuild().catch(e=>toast(e.message,true));
  }
  syncCompare();renderCompare().catch(e=>toast(e.message,true));
  // If an asset is removed from the catalog, keep its card legible.
  document.addEventListener('error',event=>{const image=event.target;if(image instanceof HTMLImageElement&&!image.dataset.failed){image.dataset.failed='1';image.src='/assets/product-placeholder.svg';}},true);
})();
