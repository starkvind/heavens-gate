<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', 'hg-ci-only', 'hg_fixture');
$db->set_charset('utf8mb4');
$db->query('CREATE TABLE fact_docs (id INT PRIMARY KEY, content MEDIUMTEXT)');
$db->query("CREATE USER 'hg_fixture_reader'@'%' IDENTIFIED BY 'hg-ci-reader'");
$db->query("GRANT SELECT ON hg_fixture.* TO 'hg_fixture_reader'@'%'");
$fixture = [
    1=>'<p>Texto</p>',
    2=>'<p onclick="void(0)">Texto</p>',
    3=>'<table><tbody><tr><td colspan="2">A</td></tr></tbody></table>',
];
$st=$db->prepare('INSERT INTO fact_docs (id,content) VALUES (?,?)');
foreach ($fixture as $id=>$body) { $st->bind_param('is',$id,$body); $st->execute(); }
$st->close();
$cfg=tempnam(sys_get_temp_dir(),'hg-fixture-');
if ($cfg===false) throw new RuntimeException('No temporary config');
try {
    chmod($cfg,0600);
    file_put_contents($cfg,"MYSQL_HOST=127.0.0.1\nMYSQL_USER=hg_fixture_reader\nMYSQL_PWD=hg-ci-reader\nMYSQL_BDD=hg_fixture\n");
    $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__,2).'/tools/audit_rich_html.php')
        .' --config='.escapeshellarg($cfg).' --only=fact_docs.content --batch=1';
    exec($command,$lines,$status);
    if ($status!==0) throw new RuntimeException('Inventory exit '.$status);
    $rows=array_map(static fn($line)=>json_decode($line,true,512,JSON_THROW_ON_ERROR),$lines);
    $summary=array_values(array_filter($rows,static fn($row)=>($row['status']??'')==='summary'))[0]??[];
    if (($summary['scanned']??0)!==3 || ($summary['rows_with_tables']??0)!==1
        || ($summary['tables_after']??0)!==1 || ($summary['merged_cells_after']??0)!==1
        || ($summary['changed']??0)<1 || ($summary['errors']??-1)!==0) {
        throw new RuntimeException('Unexpected inventory summary');
    }
    $actual=[];
    $result=$db->query('SELECT id,content FROM fact_docs ORDER BY id');
    while ($r=$result->fetch_assoc()) $actual[(int)$r['id']]=$r['content'];
    if ($actual!==$fixture) throw new RuntimeException('Historical content changed');
    $missing=[];
    exec(str_replace('--only=fact_docs.content','--only=dim_seasons.opening',$command),$missing,$status);
    if ($status!==4) throw new RuntimeException('Missing column was not reported');
    echo "Historic inventory MariaDB regression: OK\n";
} finally {
    unlink($cfg);
    $db->close();
}
