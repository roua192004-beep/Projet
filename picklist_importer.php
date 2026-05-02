<?php
// picklist_importer.php — Lit Excel .xlsx SANS composer (ZipArchive + SimpleXML natifs PHP)
// Format Sagemcom : A:MAPA | B:PICK LIST | C:UF | D:Ligne | E:Code Pfin | F:Emplacements | G:Reference | H:Nbre US | I:Quantite

function import_picklist_file(string $filepath, PDO $pdo, string $ext): array {
    if ($ext === 'xlsx')         $rows = _read_xlsx($filepath);
    elseif ($ext === 'xls')     $rows = _read_xls_fallback($filepath);
    else                         $rows = _read_csv($filepath);

    if ($rows === null)
        return ['success'=>false,'error'=>'Impossible de lire le fichier. Pour .xls, lancez : composer install dans le dossier du projet.'];
    if (empty($rows))
        return ['success'=>false,'error'=>'Fichier vide'];

    // Trouver en-tête et mapper colonnes
    $hi=-1; $col=['mapa'=>0,'picklist'=>1,'uf'=>2,'ligne'=>3,'code_pfin'=>4,'emplacement'=>5,'reference'=>6,'nbre_us'=>7,'quantite'=>8];
    foreach ($rows as $i=>$row) {
        foreach ($row as $j=>$cell) {
            $v=strtolower(trim((string)($cell??'')));
            if ($v==='pick list'||$v==='picklist') {
                $hi=$i;
                foreach ($row as $k=>$h) {
                    $h2=strtolower(trim((string)($h??'')));
                    if ($h2==='mapa')                              $col['mapa']=$k;
                    if ($h2==='pick list'||$h2==='picklist')       $col['picklist']=$k;
                    if ($h2==='uf')                                 $col['uf']=$k;
                    if (str_contains($h2,'ligne'))                  $col['ligne']=$k;
                    if (str_contains($h2,'pfin'))                   $col['code_pfin']=$k;
                    if (str_contains($h2,'emplace'))                $col['emplacement']=$k;
                    if ($h2==='reference'||$h2==='référence')       $col['reference']=$k;
                    if (str_contains($h2,'nbre'))                   $col['nbre_us']=$k;
                    if (str_contains($h2,'quantit')||$h2==='qty')   $col['quantite']=$k;
                }
                break 2;
            }
        }
    }
    if ($hi===-1) $hi=0;

    // Grouper par PICK LIST
    $picklists=[];
    foreach (array_slice($rows,$hi+1) as $row) {
        $ref=trim((string)($row[$col['reference']]??'')); $pl=trim((string)($row[$col['picklist']]??''));
        if (!$ref||strlen($ref)<2||!$pl) continue;
        if (!isset($picklists[$pl])) $picklists[$pl]=['mapa'=>trim((string)($row[$col['mapa']]??'')),'uf'=>trim((string)($row[$col['uf']]??'')),'ligne'=>trim((string)($row[$col['ligne']]??'')),'code_pfin'=>trim((string)($row[$col['code_pfin']]??'')),'lines'=>[]];
        $picklists[$pl]['lines'][]=['reference'=>$ref,'emplacement'=>trim((string)($row[$col['emplacement']]??'')),'nbre_us'=>max(0,(int)($row[$col['nbre_us']]??0)),'quantite'=>max(1,(int)($row[$col['quantite']]??1))];
    }
    if (empty($picklists)) return ['success'=>false,'error'=>'Aucune référence valide trouvée. Vérifier colonnes : PICK LIST, Reference, Quantite'];

    // Insérer BDD
    $total=0; $codes=[];
    foreach ($picklists as $pl_code=>$pl_data) {
        $ex=$pdo->prepare("SELECT id FROM picklist_header WHERE picklist_code=?"); $ex->execute([$pl_code]); $pid=$ex->fetchColumn();
        if ($pid) { $pdo->prepare("UPDATE picklist_header SET mapa=?,uf=?,ligne_production=?,code_pfin=?,status='active',imported_at=NOW() WHERE id=?")->execute([$pl_data['mapa'],$pl_data['uf'],$pl_data['ligne'],$pl_data['code_pfin'],$pid]); $pdo->prepare("DELETE FROM picklist_lines WHERE picklist_id=?")->execute([$pid]); }
        else { $pdo->prepare("INSERT INTO picklist_header (picklist_code,mapa,uf,ligne_production,code_pfin,status,imported_at) VALUES (?,?,?,?,?,'active',NOW())")->execute([$pl_code,$pl_data['mapa'],$pl_data['uf'],$pl_data['ligne'],$pl_data['code_pfin']]); $pid=$pdo->lastInsertId(); }
        $stmt=$pdo->prepare("INSERT INTO picklist_lines (picklist_id,reference,emplacement,nbre_us,quantite,status) VALUES (?,?,?,?,?,'pending')");
        foreach ($pl_data['lines'] as $l){$stmt->execute([$pid,$l['reference'],$l['emplacement'],$l['nbre_us'],$l['quantite']]);$total++;}
        $codes[]=$pl_code;
    }
    return ['success'=>true,'picklist_code'=>implode(', ',$codes),'count'=>$total,'nb_picklists'=>count($picklists)];
}

// XLSX sans composer — ZipArchive + SimpleXML (natifs PHP)
function _read_xlsx(string $fp): ?array {
    if (!class_exists('ZipArchive')) return null;
    $zip=new ZipArchive();
    if ($zip->open($fp)!==true) return null;

    // Chaînes partagées
    $shared=[];
    $ssXml=$zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ss=@simplexml_load_string($ssXml);
        if ($ss) foreach ($ss->si as $si) {
            $text='';
            if (isset($si->r)) { foreach ($si->r as $r) $text.=(string)($r->t??''); }
            else { $text=(string)($si->t??''); }
            $shared[]=$text;
        }
    }

    // Feuille 1
    $shXml=$zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$shXml) return null;
    $sh=@simplexml_load_string($shXml);
    if (!$sh) return null;

    $rows=[];
    foreach ($sh->sheetData->row??[] as $row) {
        $rd=[]; $mx=0;
        foreach ($row->c as $cell) {
            preg_match('/^([A-Z]+)/',(string)$cell['r'],$m);
            $ci=_col2idx($m[1]??'A');
            $type=(string)($cell['t']??''); $val=(string)($cell->v??'');
            if ($type==='s') $val=$shared[(int)$val]??'';
            elseif ($type==='inlineStr') $val=(string)($cell->is->t??'');
            $rd[$ci]=$val; $mx=max($mx,$ci);
        }
        $arr=[]; for($i=0;$i<=$mx;$i++) $arr[]=$rd[$i]??'';
        if (array_filter($arr,fn($v)=>trim($v)!=='')) $rows[]=$arr;
    }
    return $rows;
}

function _col2idx(string $l): int {
    $idx=0; $l=strtoupper($l);
    for($i=0;$i<strlen($l);$i++) $idx=$idx*26+(ord($l[$i])-64);
    return $idx-1;
}

function _read_xls_fallback(string $fp): ?array {
    $v=__DIR__.'/vendor/autoload.php';
    if (!file_exists($v)) return null;
    require_once $v;
    try { $r=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($fp); $r->setReadDataOnly(true); return $r->load($fp)->getActiveSheet()->toArray(null,true,true,false); }
    catch(\Exception $e){return null;}
}

function _read_csv(string $fp): array {
    $raw=file_get_contents($fp); $enc=mb_detect_encoding($raw,['UTF-8','ISO-8859-1','Windows-1252'],true);
    if($enc&&$enc!=='UTF-8') $raw=mb_convert_encoding($raw,'UTF-8',$enc);
    $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$raw));
    $first=trim($lines[0]??''); $sep=substr_count($first,';')>=substr_count($first,',') ? ';':',';
    $rows=[];
    foreach ($lines as $l){$l=trim($l);if($l==='')continue;$rows[]=str_getcsv($l,$sep);}
    return $rows;
}
