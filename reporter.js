'use strict';

var http  = require('http');
var os    = require('os');
var fs    = require('fs');
var exec  = require('child_process').exec;
var dns   = require('dns');
var dgram = require('dgram').createSocket('udp4');


var hostname = os.hostname();


var config      = require('./config.js');

// Load the local config into the global config
!function() {
  var localConfig = require('./config.' + hostname + '.js');

  for (var key in localConfig) {
    config[key] = localConfig[key];
  }
}();




var collector = config.collector.host;

// Try to lookup the connector hostname to an IP.
// This will remove the overhead of having to do this for every UDP packed we send.
dns.lookup(collector, function(err, address) {
  if (err) {
    console.log('ERROR: could not resolve collector host ' + err);
    return;
  }

  collector = address;
});

dgram.bind(config.port, '127.0.0.1');




// Store previous values so we can send the difference.
var previousValues = {};

function sendDataPoints(data) {
  var message = [];

  for (var key in data) {
    // When it's an array we don't send the difference, we just send the value.
    if (data[key] instanceof Array) {
      message.push(key + '.' + hostname + ':' + data[key][0]);
    } else {
      // Send the difference between data points.
      // The first call will send nothing and only store the points.
      var value = parseFloat(data[key]);

      if (previousValues[key]) {
        // Always assume it increments
        message.push(key + '.' + hostname + ':' + (value - previousValues[key]));
      }

      previousValues[key] = value;
    }
  }

  if (message.length == 0) {
    return;
  }

  message = new Buffer(message.join(','));

  dgram.send(message, 0, message.length, 9876, collector);
}






// Collect lighttpd fcgi stats
if (config.php) { !function() {
  var options = {
    host  : config.php.host || '127.0.0.1',
    port  : config.php.port || 80,
    path  : config.php.path || '/server-statistics',
    method: 'GET'
  };

  setInterval(function() {
    http.request(options, function(res) {
      var data = '';

      res.on('data', function(chunk) {
        data += chunk;
      });

      res.on('end', function() {
        data = data.split('\n');

        if (!data[7]) {
          data = null; // Free memory
          return;
        }

        var overloaded = data[5].split(': ')[1];
        var served     = data[7].split(': ')[1];

        sendDataPoints({
          'php.served'   : served,
          'php.overloade': overloaded
        });
      });
    }).end();
  }, 1000);
}(); }




// Collecte memory stats
setInterval(function() {
  fs.readFile('/proc/meminfo', function(err, content) {
    content = content.toString('utf8').split('\n');

    var info = {};

    for (var i = 0; i < content.length; ++i) {
      var line = content[i].split(/\s+/);

      info[line[0].replace(':', '')] = parseInt(line[1]);
    }

    sendDataPoints({
      'os.load': [os.loadavg()[0]],
      'os.free': [Math.round((info.MemFree + info.Cached) / 1024)]
    });
  });
}, 1000);




// Collect disk io stats
setInterval(function() {
  fs.readFile('/proc/diskstats', function(err, content) {
    content = content.toString('utf8').split('\n');

    var line = content[content.length - 2].split(/\s+/);

    sendDataPoints({
      'os.disk.read'   : line[4],
      'os.disk.written': line[8]
    });
  });
}, 1000);




// Collect network stats
setInterval(function() {
  fs.readFile('/proc/net/dev', function(err, content) {
    content  = content.toString('utf8').split('\n');

    var data = {};

    for (var i = 3; i < content.length - 1; ++i) {
      var line = content[i].split(/\s+/);
      var eth  = line[1].replace(':', '');

      data['os.net.read.'    + eth] = line[2];
      data['os.net.written.' + eth] = line[10];
    }

    sendDataPoints(data);
  });
}, 1000);

