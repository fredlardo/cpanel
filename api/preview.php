<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$docId=$_GET['id']??''; if(!$docId) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'id requis']); exit; }
$pdo=db();
$s=$pdo->prepare("SELECT * FROM documents WHERE id=?"); $s->execute([$docId]); $doc=$s->fetch(); if(!$doc){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'introuvable']); exit; }
$path=__DIR__.'/../'.ltrim($doc['filePath'],'/');
$ext=strtolower(pathinfo($doc['originalName'],PATHINFO_EXTENSION));
header('Content-Type: application/json; charset=utf-8');
if(!file_exists($path)){ echo json_encode(['ok'=>false,'msg'=>'fichier manquant']); exit; }

if(in_array($ext,['docx'])){
  $zip=new ZipArchive(); if($zip->open($path)===true){
    $xml=$zip->getFromName('word/document.xml');
    $zip->close();
    if($xml){
      $xml=preg_replace('/<w:tab[^>]*>/',' ',$xml);
      // tables : extraction simple <w:tbl> -> <table>
      $tblHtml='';
      if(strpos($xml,'<w:tbl')!==false){
        preg_match_all('/<w:tbl[^>]*>(.*?)<\/w:tbl>/s',$xml,$tbls);
        foreach($tbls[1] as $tbl){
          $tblHtml.='<table border="1" cellpadding="6" style="border-collapse:collapse;width:100%;margin:8px 0;font-size:12px">';
          preg_match_all('/<w:tr[^>]*>(.*?)<\/w:tr>/s',$tbl,$trs);
          foreach($trs[1] as $tr){
            $tblHtml.='<tr>';
            preg_match_all('/<w:tc[^>]*>(.*?)<\/w:tc>/s',$tr,$tcs);
            foreach($tcs[1] as $tc){
              preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s',$tc,$mt); $cell=htmlspecialchars(implode('', array_map(fn($x)=>html_entity_decode($x,ENT_QUOTES,'UTF-8'), $mt[1])));
              $tblHtml.='<td>'.$cell.'</td>';
            }
            $tblHtml.='</tr>';
          }
          $tblHtml.='</table>';
        }
      }
      preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s',$xml,$paras);
      $htmlParas='';
      foreach($paras[1] as $p){
        // ignore p inside tables already handled
        if(strpos($p,'<w:tbl')!==false) continue;
        $heading=''; $isList=strpos($p,'<w:numPr')!==false; $align='left';
        if(preg_match('/<w:pStyle[^>]*w:val="([^"]+)"/',$p,$sm)){ $s=$sm[1]; if(preg_match('/Heading1/i',$s)) $heading='h1'; elseif(preg_match('/Heading2/i',$s)) $heading='h2'; elseif(preg_match('/Heading3/i',$s)) $heading='h3'; elseif(preg_match('/Title/i',$s)) $heading='h1'; }
        if(preg_match('/<w:jc[^>]*w:val="([^"]+)"/',$p,$am)) $align=$am[1];
        $paraHtml='';
        preg_match_all('/<w:r[^>]*>(.*?)<\/w:r>/s',$p,$runs);
        if(empty($runs[1])){
          preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s',$p,$m);
          $txt=htmlspecialchars(implode('', array_map(fn($x)=>html_entity_decode($x,ENT_QUOTES,'UTF-8'), $m[1])));
          $paraHtml=$txt;
        } else {
          foreach($runs[1] as $run){
            preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s',$run,$mt);
            $txt=implode('', array_map(fn($x)=>html_entity_decode($x,ENT_QUOTES,'UTF-8'), $mt[1]));
            if($txt==='') continue;
            $txt=htmlspecialchars($txt);
            $isBold=strpos($run,'<w:b')!==false; $isItalic=strpos($run,'<w:i')!==false; $isU=strpos($run,'<w:u ')!==false;
            if($isBold) $txt='<strong>'.$txt.'</strong>';
            if($isItalic) $txt='<em>'.$txt.'</em>';
            if($isU) $txt='<u>'.$txt.'</u>';
            $paraHtml.=$txt;
          }
        }
        $paraHtml=trim($paraHtml);
        if($paraHtml==='') continue;
        if($isList) $paraHtml='• '.$paraHtml;
        $style='margin:4px 0;line-height:1.6;'.($align==='center'?'text-align:center;':($align==='right'?'text-align:right;':($align==='both'?'text-align:justify;':'')));
        if($heading==='h1') $htmlParas.='<h2 style="'.$style.'font-size:18px;font-weight:700">'.$paraHtml.'</h2>';
        elseif($heading==='h2') $htmlParas.='<h3 style="'.$style.'font-size:15px;font-weight:700">'.$paraHtml.'</h3>';
        elseif($heading==='h3') $htmlParas.='<h4 style="'.$style.'font-size:13px;font-weight:700">'.$paraHtml.'</h4>';
        else $htmlParas.='<p style="'.$style.'font-size:13px">'.$paraHtml.'</p>';
      }
      $html='<div style="font-family:system-ui">'.($htmlParas ?: '<p style="color:#999">Document vide — téléchargez pour ouvrir dans Word.</p>').$tblHtml.'</div>';
      $plain=strip_tags(str_replace(['<br>','</p>','</h2>'], "\n", $htmlParas));
      $plain=mb_substr($plain,0,8000);
      if(trim(strip_tags($htmlParas))==='') $html='<p style="color:#999">Document vide ou non lisible — téléchargez pour ouvrir dans Word.</p>';
      echo json_encode(['ok'=>true,'html'=>$html,'text'=>$plain]); exit;
    }
  }
  echo json_encode(['ok'=>false,'msg'=>'docx illisible']); exit;
}
if(in_array($ext,['xlsx','xls'])){
  $zip=new ZipArchive(); if($zip->open($path)===true){
    $shared=[]; $ss=$zip->getFromName('xl/sharedStrings.xml'); if($ss){ preg_match_all('/<t[^>]*>(.*?)<\/t>/s',$ss,$m); $shared=$m[1]; }
    $html='<div style="font-size:12px"><table border=1 cellpadding=6 style="border-collapse:collapse;min-width:100%">'; 
    for($i=1;$i<=5;$i++){ // first 5 sheets max
      $sheet=$zip->getFromName("xl/worksheets/sheet$i.xml"); if(!$sheet) break;
      preg_match_all('/<row[^>]*>(.*?)<\/row>/s',$sheet,$rows);
      $html.='<tr><td colspan=20 style="background:#f1f5f9;font-weight:600">Feuille '.$i.'</td></tr>';
      foreach(array_slice($rows[1],0,40) as $r){
        $html.='<tr>';
        preg_match_all('/<c[^>]*>(.*?)<\/c>/s',$r,$cells);
        foreach($cells[1] as $c){
          if(strpos($c,'<v>')!==false){ preg_match('/<v>(.*?)<\/v>/',$c,$vm); $v=$vm[1]??''; 
            if(strpos($c,'t=\"s\"')!==false){ $idx=(int)$v; $v=htmlspecialchars($shared[$idx]??$v); } else $v=htmlspecialchars($v);
          } else $v='';
          $html.='<td>'.$v.'</td>';
        }
        $html.='</tr>';
      }
    }
    $html.='</table><p style="color:#999;margin-top:8px">Aperçu limité à 40 lignes / 5 feuilles — téléchargez pour le fichier complet.</p></div>';
    $zip->close();
    echo json_encode(['ok'=>true,'html'=>$html]); exit;
  }
  echo json_encode(['ok'=>false,'msg'=>'xls illisible']); exit;
}
if(in_array($ext,['doc'])){ echo json_encode(['ok'=>false,'msg'=>'Format .doc binaire — ouvrez dans Word ou convertissez en .docx pour prévisualiser.','download'=>BASE_URL.'/'.$doc['filePath']]); exit; }
echo json_encode(['ok'=>false,'msg'=>'Type non géré']);
