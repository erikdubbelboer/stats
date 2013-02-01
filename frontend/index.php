<?

require 'config.inc.php';
require 'predis/autoload.php';


$redis = new Predis\Client('tcp://' . $config['redis']['ip'] . ':' . $config['redis']['port']);

// We need this multiple times so only build it once.
$zeroes = array_fill(0, 288, 0);

$limit = (isset($_GET['limit']) && is_numeric($_GET['limit'])) ? $_GET['limit'] : 20;



function format_html($str) {
  return htmlentities($str, ENT_COMPAT, 'UTF-8');
}



function render($what, $k, $interval, $timeFormat, $title, $desc) {
  global $redis, $zeroes, $keys, $limit;

  $data = array();

  foreach ($keys as $key) {
    if (is_array($key)) {
      $sum = $zeroes;

      foreach ($key[1] as $ky) {
        $d = array_slice(array_merge($zeroes, $redis->lrange($ky . $k, -288, -1)), -288);

        foreach ($d as $i => $v) {
          $sum[$i] += $v;
        }
      }

      $data[$key[0]] = $sum;
    } else {
      $data[$key] = array_slice(array_merge($zeroes, $redis->lrange($key . $k, -288, -1)), -288);
    }
  }


  //if (count($data) > 20) {
    $totals = array_map('array_sum', $data);
    
    uksort($data, function($a, $b) use($totals) {
      if ($totals[$a] == $totals[$b]) {
        return 0;
      }

      return ($totals[$a] > $totals[$b]) ? -1 : 1;
    });

    $data = array_slice($data, 0, $limit);

    //var_dump($data);
    //die;
  //}


  ?>
  <h2><?=$title?></h2>
  <p style="font-size: small">1 second average taken in <?=$desc?> intervals.</p>
  <div id=<?=$what?>></div>
  <?

  $graph = array(
    'cols' => array(
      array(
        'label' => 'Ago',
        'type'  => 'string'
      )
    ),
    'rows' => array()
  );

  $count = 0;
  foreach (array_keys($data) as $key) {
    if (++$count > $limit) {
      $graph['cols'][] = array(
        'label' => 'Other',
        'type'  => 'number'
      );

      break;
    }

    $graph['cols'][] = array(
      'label' => $key,
      'type'  => 'number'
    );
  }

  $time = time();

  for ($n = 0; $n < 288; ++$n) {
    $row = array(
      'c' => array(
        array('v' => date($timeFormat, $time - ((288 - $n) * $interval)))
      )
    );

    $count = 0;
    foreach ($data as $values) {
      if (++$count > $limit) {
        $row['c'][$limit]['v'] += floatval($values[$n]);
      } else {
        $row['c'][] = array('v' => floatval($values[$n]));
      }
    }

    $graph['rows'][] = $row;
  }

  ?>
  <script>var <?=$what?> = <?=json_encode($graph)?>;</script>
  <?



  ?>
  <script>
  google.load('visualization', '1', {packages: ['columnchart']});

  google.setOnLoadCallback(function() {
    new google.visualization.LineChart(document.getElementById('<?=$what?>')).draw(
      new google.visualization.DataTable(<?=$what?>, 0.6), {
        width: '100%',
        height: 600,
        min: 0,
        legend: 'bottom',
        legendFontSize: 13,
        axisFontSize: 12
      }
    );
  });
  </script>
  <?
}



$getKeys = explode('&', $_SERVER['QUERY_STRING']);

if (empty($getKeys[0])) {
  $getKeys = array();
} else {
  $getKeys = array_filter($getKeys, function($key) {
    return (strpos($key, 'limit=') !== 0);
  });

  // Reverse sort the list so all the -'s are at the end.
  rsort($getKeys);
}



$title = 'stats';

if (count($getKeys) > 0) {
  $title .= ' for ' . implode(' & ', $getKeys);
}


?>
<!doctype html>
<html lang=en>
<head>
<meta charset=utf-8>

<title><?=format_html($title)?></title>
<script src="http://www.google.com/jsapi"></script>

<link rel=stylesheet href="css/stats.css" media=all>

<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.js"></script>
<script src="js/jquery-cookie/jquery.cookie.js"></script>
<script src="js/stats.js"></script>

</head>
<body>
<?

if (count($getKeys) > 0) {
  ?>
  <div id=menu><a href="?" id=list>list of keys</a> <a href="javascript:void(0);" id=save>save</a></div>
  <h1><?=format_html($title)?></h1>
  <?

  // Build this array just once
  $zeroes = array_fill(0, 288, 0);


  $keys = array();

  foreach ($getKeys as $key) {
    if (substr($key, 0, 1) == '-') {
      $i = array_search(substr($key, 1), $keys);

      if ($i !== false) {
        unset($keys[$i]);
      }
    } else if (substr($key, 0, 1) == '+') {
      $keys[] = array(substr($key, 1), array_map(function($v) { return substr($v, 0, -2); } , $redis->keys(substr($key, 1) . ':s')));
    } else {
      $keys = array_merge($keys, array_map(function($v) { return substr($v, 0, -2); } , $redis->keys($key . ':s')));
    }
  }

  render('second', ':s',       5, 'H:i:s'   , 'last 24 minutes', '5 second'); // 288 * 5 seconds = 24 minutes
  render('minute', ':m',  5 * 60, 'H:i'     , 'last 24 hours'  , '5 minute'); // 288 * 5 minutes = 24 hours
  render('hour'  , ':h', 60 * 60, 'd - H:00', 'last 12 days'   , '1 hour');   // 288 * 1 hour    = 12 days
} else {
  $rkeys = array_map(function($v) { return substr($v, 0, -2); }, $redis->keys('*:s'));

  sort($rkeys);


  $keys  = array();

  // Build a tree of keys.
  foreach ($rkeys as $key) {
    $key  = explode('.', $key);
    $node = &$keys;

    foreach ($key as $part) {
      if (!isset($node[$part])) {
        $node[$part] = array();
      }

      $node = &$node[$part];
    }
  }
  unset($node); // Unset this reference variable so we can't mess it up below.


  // Return true when an array contains only empty arrays.
  function allEmpty($keys) {
    foreach ($keys as $key) {
      if (!empty($key)) {
        return false;
      }
    }

    return true;
  }


  function printTree($keys, $prefix, $isLast) {
    // Is this a folder?
    if (!empty($keys)) {
      // Collapse all last folders
      ?>
      <li class="folder<? if ($isLast) { ?> last<? } if (allEmpty($keys)) { ?> collapsed<? } ?>">
      <div class=icon><a href="?<?=format_html($prefix)?>.*"><?=format_html($prefix)?>.*</a>
      <a href="delete.php?tree=<?=format_html($prefix)?>" class="deltree"><img src="images/delete.png" width="10" height="10" title="Delete tree" alt="[X]"></a>
      </div>
      <ul>
      <?

      $l = count($keys);

      foreach ($keys as $name => $node) {
        printTree($node, $prefix . '.' . $name, (--$l == 0));
      }

      ?></ul></li><?
    } else {
      ?>
      <li<? if ($isLast) { ?> class=last<? } ?>>
      <a href="?<?=format_html($prefix)?>"><?=format_html($prefix)?></a>
      <a href="delete.php?key=<?=format_html($prefix)?>" class="delkey"><img src="images/delete.png" width="10" height="10" title="Delete key" alt="[X]"></a>
      </li>
      <?
    }

    ?></li><?
  }


  ?>
  <h1>stats</h1>

  <div id=keys>
  <ul>
  <li class=folder><div class=icon>keys</div><ul>
  <?

  $l = count($keys) + (isset($_COOKIE['storedstats']) ? 1 : 0);

  foreach ($keys as $name => $node) {
    printTree($node, $name, (--$l == 0));
  }

  if (isset($_COOKIE['storedstats'])) {
    $stored = explode(',', $_COOKIE['storedstats']);

    sort($stored);

    ?>
    <li class="folder last"><div class=icon>stored</div><ul>
    <?

    $l = count($stored);

    foreach ($stored as $key) {
      ?><li<? if (--$l == 0) { ?> class=last<? } ?>><a href="?<?=format_html($key)?>"><?=format_html($key)?></a></li><?
    }

    ?>
    </ul>
    </li>
    <?
  }

  ?>
  </ul></li>
  </ul>
  </div>
  <?
}
?>
</body>
</html>
