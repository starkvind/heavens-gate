<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/helpers/html_sanitizer.php';
$initial = hg_sanitize_rich_html('<p>Inicio </p><table><tbody><tr><td colspan="2">Celda</td></tr></tbody></table>');
$clipboard = '<p onclick="void(0)">Pegado <strong>seguro</strong></p><table><tbody><tr><td>Dato</td></tr></tbody></table>';
$clean = hg_sanitize_rich_html($clipboard);
?>
<!doctype html><html><head><meta charset="UTF-8">
<link rel="stylesheet" href="editor-lab/jodit/es2021/jodit.min.css"></head><body>
<textarea id="source"></textarea><pre id="result">{"ok":false,"error":"not executed"}</pre>
<script src="editor-lab/jodit/es2021/jodit.min.js"></script>
<script src="assets/js/hg_mentions.js"></script>
<script src="assets/js/admin/hg-jodit-editor.js"></script>
<script>
(async function(){
  const result={ok:false};
  let requests=0, alerts=[];
  const initial=<?= json_encode($initial,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
  const clipboard=<?= json_encode($clipboard,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
  const clean=<?= json_encode($clean,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
  window.alert=message=>alerts.push(message);
  window.fetch=async (url,options)=>{
    if(String(url).includes('/ajax/mentions')) return {ok:true,json:async()=>({ok:true,items:[{type:'character',id:42,label:'Personaje'}]})};
    requests++;
    if(options.headers['X-CSRF-Token']!=='fixture-csrf' || JSON.parse(options.body).html!==clipboard) throw Error('Unexpected paste request');
    return {ok:true,json:async()=>({ok:true,data:{content:clean}})};
  };
  const delay=(ms=50)=>new Promise(r=>setTimeout(r,ms));
  const ta=document.getElementById('source');
  ta.value=initial;
  let handle;
  try {
    handle=HGJodit.create(ta,{types:['character'],csrf:'fixture-csrf',pasteUrl:'/fixture-paste'});
    await delay();
    const root=document.querySelector('.jodit-wysiwyg');
    if(!root) throw Error('Missing editor');
    const selectEnd=node=>{
      root.focus();
      const range=document.createRange();range.selectNodeContents(node);range.collapse(false);
      const sel=window.getSelection();sel.removeAllRanges();sel.addRange(range);
    };
    result.initial_table=!!root.querySelector('td[colspan="2"]');
    selectEnd(root.querySelector('p'));
    const data=new DataTransfer();data.setData('text/html',clipboard);
    const paste=new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true});
    root.dispatchEvent(paste);
    await delay(150);
    handle.sync();
    result.paste_guard=paste.defaultPrevented && requests===1 && !root.querySelector('[onclick]') && root.textContent.includes('Pegado seguro') && root.textContent.includes('Dato');
    result.paste_table=root.querySelectorAll('table').length===2;
    const drop=new DragEvent('drop',{bubbles:true,cancelable:true,dataTransfer:data});root.dispatchEvent(drop);
    result.drop_blocked=drop.defaultPrevented;
    const p=document.createElement('p');p.textContent='@character Pers';root.appendChild(p);
    selectEnd(p.firstChild);
    root.dispatchEvent(new Event('input',{bubbles:true}));
    await delay(350);
    const choice=document.querySelector('.hg-mention-item');
    if(!choice) throw Error('Mention suggestion missing');
    choice.click();
    await delay();
    handle.sync();
    result.mention_token=ta.value.includes('[@character:42]');
    result.mentions_removed_after_destroy=false;
    handle.destroy();handle=null;
    result.mentions_removed_after_destroy=!document.querySelector('.hg-mention-box');
    result.no_alerts=alerts.length===0;
    result.ok=Object.entries(result).filter(([k])=>k!=='ok').every(([,v])=>v===true);
  } catch(e){result.error=String(e);result.alerts=alerts;}
  finally{if(handle)handle.destroy();}
  document.getElementById('result').textContent=JSON.stringify(result);
})();
</script></body></html>
