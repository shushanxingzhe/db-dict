<?php

/**
 * 生成mysql数据字典
 */

//数据库配置
$config = require_once('config.php');

if (isset($_GET['generator']) && $_GET['generator'] == 1) {
    generator($config['databases'], $config['db_conf']);
    header('Location: /');
}

$content = '';
$content .= '## 数据库 ' . PHP_EOL;
$content .= '#####            [重新生成](/?generator=1)' . PHP_EOL;
$content .= '' . PHP_EOL;
$content .= '|  库  |' . PHP_EOL;
$content .= '| ------ |' . PHP_EOL;
if (is_dir('./db') && $handle = opendir('./db')) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry == '.' || $entry == '..') {
            continue;
        }
        $content .= "| [{$entry}](/db/{$entry}) |" . PHP_EOL;
    }
    closedir($handle);
} else {
    generator($config['databases'], $config['db_conf']);
    if ($handle = opendir('./db')) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            $content .= "| [{$entry}](/db/{$entry}) |" . PHP_EOL;
        }
        closedir($handle);
    }
}

$html = htmlTemplate('数据库字典', $content);
echo $html;

function htmlTemplate($title, $content)
{
    //html输出
    $marked_text = htmlentities($content);
    $marked_text = $content;
    $html_tplt = <<<EOT
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>{$title} - Powered By Markdown Viewer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" type="text/css" href="/github-markdown.css">
    <script src="/marked.js"></script>
    <script src="/highlight.pack.js"></script>
    <link href="/github.css" rel="stylesheet">
</head>
<body>
<div class="markdown-body" id="content" style="margin:auto; width: 1024px;">

</div>
<div id="marked_text" style="display:none;">
{$marked_text}
</div>
<script>
var marked_text = document.getElementById('marked_text').innerText;
var renderer = new marked.Renderer();
renderer.table = function(header, body) {
    return '<table class="table table-bordered table-striped">\\n'
            + '<thead>\\n'
            + header
            + '</thead>\\n'
            + '<tbody>\\n'
            + body
            + '</tbody>\\n'
            + '</table>\\n';
};
marked.setOptions({
    renderer: renderer,
    gfm: true,
    tables: true,
    breaks: false,
    pedantic: false,
    sanitize: true,
    smartLists: true,
    smartypants: false,
    langPrefix: 'language-',
    //这里使用了highlight对代码进行高亮显示
    highlight: function (code) {
        return hljs.highlightAuto(code).value;
    }
});
document.getElementById('content').innerHTML = marked(marked_text);
  </script>
</body>
</html>
EOT;
    return $html_tplt;
}

function exportDict($dbname, $config)
{

    $title = $dbname . ' 数据字典';
    $dsn = 'mysql:dbname=' . $dbname . ';host=' . $config['host'];
    //数据库连接

    try {
        $con = new PDO($dsn, $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        die('Connection failed: ' . $e->getMessage());
    }


    $tables = $con->query('SHOW tables')->fetchAll(PDO::FETCH_COLUMN);

    //取得所有的表名
    foreach ($tables as $table) {
        $_tables[]['TABLE_NAME'] = $table;
    }

    //循环取得所有表的备注及表中列消息  
    foreach ($_tables as $k => $v) {

        $sql = 'SELECT * FROM ';
        $sql .= 'INFORMATION_SCHEMA.TABLES ';
        $sql .= 'WHERE ';
        $sql .= "table_name = '{$v['TABLE_NAME']}' AND table_schema = '{$dbname}'";
        $tr = $con->query($sql)->fetch(PDO::FETCH_ASSOC);
        $_tables[$k]['TABLE_COMMENT'] = $tr['TABLE_COMMENT'];

        $sql = 'SELECT * FROM ';
        $sql .= 'INFORMATION_SCHEMA.COLUMNS ';
        $sql .= 'WHERE ';
        $sql .= "table_name = '{$v['TABLE_NAME']}' AND table_schema = '{$dbname}'";
        $fields = [];
        $field_result = $con->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($field_result as $fr) {
            $fields[] = $fr;
        }
        $_tables[$k]['COLUMN'] = $fields;
    }
    unset($con);

    $mark = '';

    //循环所有表  
    foreach ($_tables as $k => $v) {

        $mark .= '##' . $v['TABLE_NAME'] . '  ' . $v['TABLE_COMMENT'] . PHP_EOL;
        $mark .= '' . PHP_EOL;
        $mark .= '|  字段名  |  数据类型  |  键  |  默认值  |  允许非空  |  自动递增  |  备注  |' . PHP_EOL;
        $mark .= '| ------ | ------ | ------ | ------ | ------ | ------ | ------ |' . PHP_EOL;
        foreach ($v['COLUMN'] as $f) {
            $mark .= '| ' . $f['COLUMN_NAME'] . ' | ' . $f['COLUMN_TYPE'] . ' | ' . $f['COLUMN_KEY'] . ' | ' . $f['COLUMN_DEFAULT'] . ' | ' . $f['IS_NULLABLE'] . ' | ' . ($f['EXTRA'] == 'auto_increment' ? '是' : '') . ' | ' . (empty($f['COLUMN_COMMENT']) ? '-' : str_replace('|', '/', $f['COLUMN_COMMENT'])) . ' |' . PHP_EOL;
        }
        $mark .= '' . PHP_EOL;

    }

    //markdown输出
    $md_tplt = <<<EOT
# [<<](/)   {$title}
>   本数据字典由PHP脚本自动导出,字典的备注来自数据库表及其字段的注释(`comment`).开发者在增改库表及其字段时,请在 `migration` 时写明注释,以备后来者查阅.

{$mark}
EOT;

    $html_tplt = htmlTemplate($title, $md_tplt);
    file_put_contents('./db/' . $dbname . '.html', $html_tplt);
}

function generator($dbs, $config)
{
    @mkdir('./db');
    foreach ($dbs as $db) {
        exportDict($db, $config);
    }
}

?>
