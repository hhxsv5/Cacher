<?php
set_time_limit(0);
include_once 'CacheMgr.php';

echo '<pre>';

// CacheMgr::ClearCache();
// var_dump(CacheMgr::Set('helloKey', 'helloValue' . mt_rand(0, 10000)));
// var_dump(CacheMgr::Get('helloKey'));

for ($i = 65; $i < 91; ++$i) {
    $char = chr($i);
    var_dump(CacheMgr::Set($char, $char . '-value', 15));
    var_dump(CacheMgr::Get($char));
}
// for ($i = 0; $i < 10000; ++$i) {
// $char = 'Key' . $i;
// var_dump(CacheMgr::Set($char, $char . '-value', 15));
// var_dump(CacheMgr::Get($char));
// }
// CacheMgr::Set('key2', TRUE); // 布尔型值有可能与查找失败返回的FALSE混淆
// var_dump(CacheMgr::Get('key2'));

// CacheMgr::Set('key3', NULL);
// var_dump(CacheMgr::Get('key3'));

// CacheMgr::Set('key4', array (
// 'key1' => 'value1',
// 'key2' => 2
// ));
// var_dump(CacheMgr::Get('key4'));

// $obj = new stdClass();
// $obj -> key1 = 1;
// $obj -> key2 = 'Hello';
// CacheMgr::Set('key5', $obj);
// var_dump(CacheMgr::Get('key5'));

// var_dump(CacheMgr::Set('increment_key', 0));
// var_dump(CacheMgr::Get('increment_key'));
// var_dump(CacheMgr::Inc('increment_key', 4));
// var_dump(CacheMgr::Get('increment_key'));
// var_dump(CacheMgr::Dec('increment_key', 2));
// var_dump(CacheMgr::Get('increment_key'));

// var_dump(CacheMgr::ClearCache());

var_export(CacheMgr::CacheInfo());

echo '</pre>';