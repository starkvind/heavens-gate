<?php
require_once __DIR__.'/../../app/helpers/content_api_auth.php';
$expiry=strtotime('2026-11-18T00:00:00+01:00');
$cases=[
    ['empty credential rejected',hg_content_api_authenticate('','',$expiry-100)===false],
    ['wrong credential rejected',hg_content_api_authenticate('not-the-staging-token','',$expiry-100)===false],
    ['expired staging credential rejected',hg_content_api_authenticate('not-the-staging-token','',$expiry)===false],
    ['configured credential accepted',hg_content_api_authenticate('production-example','production-example',$expiry+100)===true],
    ['configured credential mismatch rejected',hg_content_api_authenticate('bad','production-example',$expiry-100)===false],
    ['expired staging not accepted',hg_content_api_authenticate('production-example','',$expiry+100)===false]
];
foreach($cases as [$description,$passes]) {
    if (!$passes) {fwrite(STDERR,"FAIL: $description\n");exit(1);}
    echo "PASS: $description\n";
}
