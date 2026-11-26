<?php
require_once __DIR__ . '/../app/presentation/characters/trait_specializations.php';
function expect($condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$items=hg_trait_specializations_normalize(" Tradiciones vampíricas \r\nRituales de sangre\n\nTradiciones vampíricas");
expect($items===['Tradiciones vampíricas','Rituales de sangre'],'Normalization');
expect(hg_trait_specializations_decode(json_encode($items))===$items,'Roundtrip');
expect(hg_trait_specializations_decode('{"a":"b"}')===[],'Invalid JSON shape');
expect(hg_trait_specializations_label('Ocultismo','Ocultismo',[],':')==='Ocultismo:','Unchanged empty markup');
$html=hg_trait_specializations_label('Ocultismo','Ocultismo',['<script>alert(1)</script>'],':');
expect(str_contains($html,'aria-expanded="false"')&&!str_contains($html,'<script>'),'Escaping');
$catalog=[['id'=>1,'kind'=>'Conocimientos'],['id'=>2,'kind'=>'Trasfondos']];
expect(hg_trait_specializations_validate('{"1":"Uno\nDos"}',$catalog,[1=>5])===[1=>['Uno','Dos']],'Valid delta');
expect(hg_trait_specializations_validate('{"1":""}',$catalog,[1=>5])===[1=>[]],'Explicit clear');
foreach(['{"2":"No"}','{"1":[]}','{"3":"No"}','[]'] as $invalid){
    try{hg_trait_specializations_validate($invalid,$catalog,[1=>5,2=>1]);}catch(InvalidArgumentException $e){continue;}
    throw new RuntimeException('Invalid accepted: '.$invalid);
}
try{hg_trait_specializations_validate('{"1":"No"}',$catalog,[1=>0]);throw new RuntimeException('Zero accepted');}catch(InvalidArgumentException $e){}
echo "Trait specialization tests OK\n";
