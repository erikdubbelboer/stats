'use strict';

var config = require('./config.js');
var redis  = require('redis-node');
var dgram  = require('dgram');


var redisClient = redis.createClient(config.redis.port, config.redis.ip);


var stats = {
  'seconds': {},
  'minutes': {},
  'hours'  : {}
};


// Load the current keys from the database
redisClient.keys('*', function(err, keys) {
  for (var i = 0; i < keys.length; ++i) {
    var type = keys[i].substr(keys[i].length - 1, 1);

    if ((type == 's') && (!stats.seconds[keys[i]])) {
      stats.seconds[keys[i]] = [];
    }
    if ((type == 'm') && (!stats.minutes[keys[i]])) {
      stats.minutes[keys[i]] = [];
    }
    if ((type == 'h') && (!stats.hours[keys[i]])) {
      stats.hours[keys[i]] = [];
    }
  }
});



dgram.createSocket('udp4', function(data) {
  if (config.verbose) {
    console.log('VERBOSE: new message "' + data + '"');
  }

  data = data.toString('utf8').split(',');
  
  for (var i = 0; i < data.length; ++i) {
    var d = data[i].split(':');

    if (d.length != 2) {
      console.log('ERROR: invalid data "' + data[i] + '"');
      continue;
    }

    var key = d[0] + ':s';
    var dtp = d[1].split('|'); // data|type pair
    var val = parseFloat(dtp[0]);

    if (dtp.length == 1) { // data point
      if (dtp[0] == 'd') { // delete
        if (stats.seconds[key]) {
          delete stats.seconds[key];
        }
        if (stats.minutes[key]) {
          delete stats.minutes[key];
        }
        if (stats.hours[key]) {
          delete stats.hours[key];
        }
      } else { // add data point
        if (!stats.seconds[key]) {
          console.log('INFO: new key ' + key.substr(0, key.length - 2));

          stats.seconds[key] = [];
        }
        
        stats.seconds[key].push(val);
      }
    } else if (dtp[1] == 'c') { // counter
      if (!stats.seconds[key]) {
        console.log('INFO: new key ' + key.substr(0, key.length - 2));

        stats.seconds[key] = [0, 0, 0, 0, 0]; // 1 value and 4 zeroes so we get a 1 second average.
      } else if (stats.seconds[key].length != 5) {
        stats.seconds[key] = [0, 0, 0, 0, 0];
      }

      stats.seconds[key][0] += val;
    } else {
      console.log('ERROR: Invalid data type ' + dtp[1]);
    }
  }
}).bind(config.port || 9876, config.ip);
  


function process(what, into) {
  for (var key in stats[what]) {
    var value = 0;

    if (stats[what][key].length > 0) {
      for (var i = 0; i < stats[what][key].length; ++i) {
        value += stats[what][key][i];
      }

      value = Math.round((value / stats[what][key].length) * 100) / 100;
    }

    stats[what][key] = [];

    redisClient.rpush(key, value);
    redisClient.ltrim(key, -288, -1);

    if (into !== false) {
      // transform key:s into key:m
      key = key.substr(0, key.length - 1) + into.substr(0, 1);

      if (!stats[into][key]) {
        stats[into][key] = [];
      }

      stats[into][key].push(value);
    }
  }
}


setInterval(process.bind(this, 'seconds', 'minutes'),       5 * 1000); // store our second average each 5 seconds
setInterval(process.bind(this, 'minutes', 'hours'  ),  5 * 60 * 1000); // store our minute average each 5 minute
setInterval(process.bind(this, 'hours'  , false    ), 60 * 60 * 1000); // store our hour   average each 1 hour

