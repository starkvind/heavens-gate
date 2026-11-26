
(() => {
    if (window.__hgTraitSpecializationsBound) return;
    window.__hgTraitSpecializationsBound=true;
    const selector='.hg-trait-specialization-trigger';
    const popup=document.createElement('section');
    popup.id='hg-trait-specialization-popup';
    popup.hidden=true;
    popup.setAttribute('role','dialog');
    popup.setAttribute('aria-labelledby','hg-trait-specialization-title');
    const nativePopover=typeof popup.showPopover==='function';
    if(nativePopover) popup.setAttribute('popover','manual');
    const header=document.createElement('header'), title=document.createElement('strong'), close=document.createElement('button'), list=document.createElement('ul');
    title.id='hg-trait-specialization-title';
    title.textContent='Especializaciones';
    close.type='button'; close.textContent='Cerrar';
    header.append(title,close); popup.append(header,list); document.body.append(popup);
    let active=null,pinned=false,timer;
    const find=target=>target instanceof Element?target.closest(selector):null;
    function position(){
        if(!active)return;
        if(!active.getClientRects().length){hide(false);return;}
        const r=active.getBoundingClientRect(),w=document.documentElement.clientWidth,h=document.documentElement.clientHeight;
        if(r.bottom<0||r.top>h||r.right<0||r.left>w){hide(false);return;}
        const p=popup.getBoundingClientRect(), below=r.bottom+4;
        popup.style.left=Math.max(8,Math.min(r.left,w-p.width-8))+'px';
        popup.style.top=(below+p.height<=h-8?below:Math.max(8,Math.min(r.top-p.height-4,h-p.height-8)))+'px';
    }
    function hide(restore){
        clearTimeout(timer);
        const previous=active;if(!previous)return;
        active=null;pinned=false;previous.setAttribute('aria-expanded','false');
        if(nativePopover&&popup.matches(':popover-open'))popup.hidePopover();
        popup.hidden=true;
        if(restore&&previous.isConnected)previous.focus({preventScroll:true});
    }
    function show(trigger,pin){
        clearTimeout(timer);
        if(active&&active!==trigger)hide(false);
        let items;try{items=JSON.parse(trigger.dataset.specializations);}catch(_){return;}
        if(!Array.isArray(items)||!items.length||!items.every(item=>typeof item==='string'))return;
        active=trigger;pinned=pin;
        list.replaceChildren(...items.map(item=>{const li=document.createElement('li');li.textContent=item;return li;}));
        trigger.setAttribute('aria-expanded','true');
        popup.hidden=false;
        if(nativePopover&&!popup.matches(':popover-open'))popup.showPopover();
        position();
        if(pin&&active)close.focus({preventScroll:true});
    }
    function scheduleHide(){clearTimeout(timer);if(!pinned)timer=setTimeout(()=>hide(false),180);}
    document.addEventListener('pointerover',event=>{
        const trigger=find(event.target);
        if(!trigger||event.pointerType!=='mouse'||pinned||find(event.relatedTarget)===trigger)return;
        show(trigger,false);
    });
    document.addEventListener('pointerout',event=>{
        const trigger=find(event.target);
        if(!trigger||trigger!==active||pinned||trigger.contains(event.relatedTarget)||popup.contains(event.relatedTarget))return;
        scheduleHide();
    });
    popup.addEventListener('pointerenter',()=>clearTimeout(timer));
    popup.addEventListener('pointerleave',event=>{if(active&&!active.contains(event.relatedTarget))scheduleHide();});
    document.addEventListener('click',event=>{
        const trigger=find(event.target);
        if(trigger){if(active===trigger&&pinned)hide(true);else show(trigger,true);}
        else if(active&&!popup.contains(event.target))hide(false);
    });
    close.addEventListener('click',()=>hide(true));
    document.addEventListener('keydown',event=>{
        if(event.key!=='Escape'||!active)return;
        event.preventDefault();event.stopPropagation();hide(pinned||popup.contains(document.activeElement));
    });
    document.addEventListener('focusin',event=>{if(pinned&&active&&!popup.contains(event.target)&&!active.contains(event.target))hide(false);});
    window.addEventListener('resize',position);
    window.addEventListener('scroll',position,true);
})();
