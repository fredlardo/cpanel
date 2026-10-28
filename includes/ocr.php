<?php
function ocrExtract(string $filePath): array {
  $ext=strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
  if(in_array($ext,['pdf'])){ // try pdftotext then tesseract
    $tmpTxt=sys_get_temp_dir().'/ocr_'.uniqid().'.txt';
    @exec("pdftotext ".escapeshellarg($filePath)." ".escapeshellarg($tmpTxt)." 2>&1", $out, $ret);
    if($ret===0 && file_exists($tmpTxt)){ $t=file_get_contents($tmpTxt); @unlink($tmpTxt); if(trim($t)!=='') return ['text'=>$t,'status'=>'DONE']; }
  }
  // images + pdf fallback tesseract
  $cmd="tesseract ".escapeshellarg($filePath)." stdout -l fra 2>&1";
  @exec($cmd, $out2, $ret2);
  if($ret2===0 && !empty($out2)){ return ['text'=>implode("\n",$out2),'status'=>'DONE']; }
  return ['text'=>null,'status'=>'PENDING'];
}
