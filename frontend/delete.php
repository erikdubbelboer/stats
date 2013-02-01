<?

require 'config.inc.php';
require '../statsapi.inc.php';
require 'predis/autoload.php';


Stats::bind($config['collector']);

$redis = new Predis\Client('tcp://' . $config['redis']['ip'] . ':' . $config['redis']['port']);


function delkey($name) {
  Stats::send($name . ':d');
}


if (isset($_GET['tree'])) {
  $keys = $redis->keys($_GET['tree'] . '.*');

  $keys = array_map(function($key) {
    return substr($key, 0, -2);
  }, $keys);

  $keys = array_unique($keys);

  foreach ($keys as $key) {
    delkey($key);
  }
} else {
  delkey($_GET['key']);
}

header('Location: ' . $_SERVER['HTTP_REFERER']);

