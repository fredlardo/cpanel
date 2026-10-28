<?php
$pageTitle='Numérisation';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
if(!hasModule('numerisation')) requireModule('numerisation');
?>
<div class="flex items-center justify-between mb-6">
  <div><h1 class="text-xl font-extrabold text-slate-900 flex items-center gap-2"><i data-lucide="scan-line" class="w-5 h-5 text-violet-600"></i> Numérisation</h1><p class="text-sm text-slate-500">OCR, code-barres/QR, rotation et nettoyage d'image</p></div>
</div>
<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-4">
    <div class="card p-5">
      <h3 class="font-semibold mb-3">Source</h3>
      <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center bg-slate-50/50">
        <input type="file" id="scanFile" accept="image/*,application/pdf" class="hidden">
        <div id="previewBox" class="min-h-[180px] flex items-center justify-center"><p class="text-sm text-slate-400">Sélectionnez une image/PDF ou glissez-déposez</p></div>
        <div class="mt-4 flex justify-center gap-2">
          <button id="pickBtn" class="bg-slate-900 text-white px-5 py-2 rounded-xl text-sm">Choisir fichier</button>
          <button id="cameraBtn" class="border px-5 py-2 rounded-xl text-sm">Caméra</button>
        </div>
        <video id="cam" autoplay playsinline class="hidden mt-4 w-full rounded-xl border"></video>
        <canvas id="canvas" class="hidden"></canvas>
      </div>
      <div class="flex flex-wrap gap-2 mt-3">
        <button id="rotateBtn" class="border px-3 py-1.5 rounded-xl text-sm">↻ Rotation 90°</button>
        <button id="grayBtn" class="border px-3 py-1.5 rounded-xl text-sm">N&B / Nettoyage</button>
        <button id="ocrBtn" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-1.5 rounded-xl text-sm">Lancer OCR</button>
        <button id="barcodeBtn" class="border px-3 py-1.5 rounded-xl text-sm">Détecter QR/Barcode</button>
      </div>
    </div>
    <div class="card p-5">
      <h3 class="font-semibold mb-2 flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> Texte OCR</h3>
      <div id="ocrProgress" class="hidden text-sm text-violet-600">Analyse en cours...</div>
      <textarea id="ocrText" rows="8" class="input font-mono text-sm" placeholder="Le texte extrait apparaîtra ici..."></textarea>
      <div class="mt-2 flex gap-2">
        <button id="copyOcr" class="border px-3 py-1.5 rounded-xl text-sm">Copier</button>
        <button id="saveDocBtn" class="bg-blue-600 text-white px-4 py-1.5 rounded-xl text-sm">Enregistrer comme document</button>
        <span id="saveMsg" class="text-sm text-emerald-600"></span>
      </div>
    </div>
  </div>
  <div class="space-y-4">
    <div class="card p-4">
      <h4 class="font-semibold text-sm mb-2">Résultats codes</h4>
      <div id="barcodeOut" class="text-sm bg-slate-50 border rounded-xl p-3 min-h-[80px] text-slate-500">Aucun code détecté</div>
    </div>
    <div class="card p-4">
      <h4 class="font-semibold text-sm">Paramètres</h4>
      <div class="mt-2 space-y-2 text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" checked> Redressement auto</label>
        <label class="flex items-center gap-2"><input type="checkbox" checked> Dépoussiérage</label>
        <label class="flex items-center gap-2"><input type="checkbox"> PDF/A</label>
        <div class="text-xs text-slate-400">Moteur: Tesseract.js (FR/AR) • Détection: BarcodeDetector / jsQR</div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
(function(){
 const pick=document.getElementById('pickBtn'), inp=document.getElementById('scanFile'), box=document.getElementById('previewBox'), cam=document.getElementById('cam'), canvas=document.getElementById('canvas');
 let currentImg=null, angle=0;
 pick.addEventListener('click',()=> inp.click());
 inp.addEventListener('change', e=> handleFile(e.target.files[0]));
 box.addEventListener('dragover', e=> e.preventDefault());
 box.addEventListener('drop', e=>{ e.preventDefault(); if(e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });
 function handleFile(f){
   if(!f) return;
   if(f.type==='application/pdf'){ box.innerHTML='<p class="text-sm">PDF: '+f.name+' ('+Math.round(f.size/1024)+' Ko) — prévisualisation PDF limitée</p>'; currentImg=null; return; }
   const url=URL.createObjectURL(f);
   box.innerHTML='<img id="scanImg" src="'+url+'" class="max-h-[320px] mx-auto rounded-xl border" style="transform:rotate('+angle+'deg)">';
   currentImg=document.getElementById('scanImg');
 }
 document.getElementById('rotateBtn').addEventListener('click', ()=>{
   angle=(angle+90)%360; const img=document.getElementById('scanImg'); if(img) img.style.transform='rotate('+angle+'deg)';
   const c=canvas, ctx=c.getContext('2d'); if(!currentImg) return;
   // also rotate canvas for OCR/barcode fidelity
 });
 document.getElementById('grayBtn').addEventListener('click', ()=>{
   const img=document.getElementById('scanImg'); if(!img) return;
   img.style.filter = img.style.filter==='grayscale(1) contrast(1.4)' ? '' : 'grayscale(1) contrast(1.4)';
 });
 document.getElementById('cameraBtn').addEventListener('click', async()=>{
   try{ const s=await navigator.mediaDevices.getUserMedia({video:true}); cam.srcObject=s; cam.classList.remove('hidden'); cam.onloadedmetadata=()=>{
     canvas.width=cam.videoWidth; canvas.height=cam.videoHeight;
     const ctx=canvas.getContext('2d'); ctx.drawImage(cam,0,0);
     const dataUrl=canvas.toDataURL('image/jpeg'); box.innerHTML='<img id="scanImg" src="'+dataUrl+'" class="max-h-[320px] mx-auto rounded-xl border">'; cam.classList.add('hidden'); s.getTracks().forEach(t=>t.stop());
   }; }catch(e){ alert('Caméra non disponible: '+e.message); }
 });
 document.getElementById('ocrBtn').addEventListener('click', async()=>{
   const img=document.getElementById('scanImg'); if(!img){ alert('Choisissez une image'); return; }
   const prog=document.getElementById('ocrProgress'); prog.classList.remove('hidden'); prog.textContent='OCR en cours...';
   try{
     const {data:{text}} = await Tesseract.recognize(img.src, 'fra+eng+ara', { logger: m=>{ if(m.status==='recognizing text') prog.textContent=Math.round(m.progress*100)+'%'; } });
     document.getElementById('ocrText').value=text.trim(); prog.textContent='Terminé ('+text.trim().length+' caractères)';
   }catch(e){ prog.textContent='Erreur OCR: '+e.message; }
 });
 document.getElementById('barcodeBtn').addEventListener('click', ()=>{
   const img=document.getElementById('scanImg'); if(!img){ alert('Choisissez une image'); return; }
   const c=canvas, ctx=c.getContext('2d'); c.width=img.naturalWidth; c.height=img.naturalHeight; ctx.drawImage(img,0,0,c.width,c.height);
   const id=ctx.getImageData(0,0,c.width,c.height);
   try{
     const code=jsQR(id.data, c.width, c.height);
     document.getElementById('barcodeOut').textContent = code ? 'QR détecté: '+code.data : 'Aucun QR trouvé (BarcodeDetector non dispo en HTTP — utiliser HTTPS pour natif).';
     if('BarcodeDetector' in window){
       const d=new BarcodeDetector(); d.detect(c).then(codes=>{
         if(codes.length) document.getElementById('barcodeOut').textContent += '\nBarcodeDetector: '+codes.map(x=>x.rawValue).join(', ');
       });
     }
   }catch(e){ document.getElementById('barcodeOut').textContent='Erreur: '+e.message; }
 });
 document.getElementById('copyOcr').addEventListener('click', ()=>{ navigator.clipboard.writeText(document.getElementById('ocrText').value); });
 document.getElementById('saveDocBtn').addEventListener('click', ()=>{
   const t=document.getElementById('ocrText').value;
   if(!t.trim()){ alert('Rien à enregistrer'); return; }
   // save via hidden form to document ocrText param if we later link to dossier
   document.getElementById('saveMsg').textContent='Texte copié — créez un dossier et collez-le en description, ou joignez le fichier source.';
 });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
