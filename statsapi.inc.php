<?

class Stats {
  private static $collector = false;
  private static $socket    = null;

  // Expects array('host' => '...', 'port' => '...')
  public static function bind($collector, $resolve = true) {
    if (is_null(self::$socket)) {
      self::$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    self::$collector = $collector;

    if ($resolve) {
      // resolve the hostname so we don't have to do this on each message we send
      $address = gethostbyname(self::$collector['host']);

      if (ip2long($address) !== false) {
        self::$collector['host'] = $address;
      }
    }
  }

  public static function send($message) {
    if (self::$collector === false) {
      return;
    }

    settype($message, 'string');

    if (strlen($message) == 0) {
      return;
    }

    socket_sendto(self::$socket, $message, strlen($message), 0, self::$collector['host'], self::$collector['port']);
  }
}

