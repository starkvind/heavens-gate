/* global Jodit, window, document, fetch */
(function(){
  'use strict';
  function create(textarea,opts){
    if (!window.Jodit || !textarea || !window.hgMentions) throw new Error('Editor no disponible');
    const editor = Jodit.make(textarea, {
      language:'es', toolbarAdaptive:false, spellcheck:false, statusbar:false,
      height:400, buttons:['undo','redo','|','paragraph','bold','italic','underline','strikethrough','brush','fontsize','font','|','ul','ol','indent','outdent','align','|','link','table','eraser'],
      disablePlugins:['paste','pasteFromWord','drag-and-drop','filebrowser','uploader','image','video','source'],
      cleanHTML:{removeEmptyElements:false}
    });
    const root = editor.editor;
    let pending=false, destroyed=false;
    const detachMentions=window.hgMentions.attachJodit(editor,{types:opts.types});
    function inside(e){return e.target instanceof Node && root.contains(e.target);}
    function stop(e){e.preventDefault();e.stopImmediatePropagation();}
    function selection(){
      const sel=window.getSelection();
      if (!sel || !sel.rangeCount) return null;
      const r=sel.getRangeAt(0);
      return root.contains(r.commonAncestorContainer)?r.cloneRange():null;
    }
    function sameRange(a,b){
      return a && b && a.startContainer===b.startContainer && a.startOffset===b.startOffset
        && a.endContainer===b.endContainer && a.endOffset===b.endOffset;
    }
    async function paste(e){
      if (!inside(e)) return;
      stop(e);
      if (pending || destroyed || !e.clipboardData) return;
      const range=selection();
      if (!range) return;
      const original=editor.value;
      let html=e.clipboardData.getData('text/html');
      if (!html){
        const text=document.createElement('span');
        text.textContent=e.clipboardData.getData('text/plain');
        html=text.innerHTML.replace(/\r\n|\r|\n/g,'<br>');
      }
      if (!html) return;
      pending=true;
      try {
        const res=await fetch(opts.pasteUrl,{method:'POST',credentials:'same-origin',
          headers:{'Content-Type':'application/json','X-CSRF-Token':opts.csrf},body:JSON.stringify({html})});
        const data=await res.json();
        if (!res.ok || !data.ok || typeof data.data?.content!=='string') throw new Error('No se pudo validar el portapapeles.');
        if (destroyed) return;
        if (editor.value!==original || !sameRange(range,selection())) throw new Error('La selección cambió. Vuelve a pegar el contenido.');
        editor.s.insertHTML(data.data.content);
      } catch(err){if (!destroyed) window.alert(err.message || 'Pegado bloqueado.');}
      finally {pending=false;}
    }
    function drop(e){if (inside(e)) stop(e);}
    function beforeInput(e){if(inside(e) && ['insertFromPaste','insertFromDrop'].includes(e.inputType)) stop(e);}
    // Capture before the editor can parse clipboard or drag/drop HTML.
    window.addEventListener('paste',paste,true);
    window.addEventListener('drop',drop,true);
    window.addEventListener('beforeinput',beforeInput,true);
    return {
      sync(){
        if(destroyed || pending) throw new Error('Espera a que termine el pegado antes de guardar.');
        textarea.value=editor.value;
      },
      destroy(){
        destroyed=true;
        window.removeEventListener('paste',paste,true);
        window.removeEventListener('drop',drop,true);
        window.removeEventListener('beforeinput',beforeInput,true);
        detachMentions();
        editor.destruct();
      }
    };
  }
  window.HGJodit={create};
})();
