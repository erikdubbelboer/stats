
var dgram = require('dgram').createSocket('udp4');

var collector = false;


module.exports.bind = function(c) {
  collector = c;

  require('dns').lookup(collector.host, function(err, address) {
    if (err) {
      console.log('ERROR: could not resolve collector host ' + err);
      return;
    }

    collector.host = address;
  });
};


module.exports.send = function (message) {
  if (collector === false) {
    return;
  }

  if (!(message instanceof Buffer)) {
    message = new Buffer(message);
  }

  dgram.send(message, 0, message.length, collector.port, collector.host);
};

