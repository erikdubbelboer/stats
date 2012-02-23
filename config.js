
module.exports = {
  collector: {
    host: 'novagroup-dev',
    port: 9876
  }
};



// Try to load a local config file (config.hostname.js)
try {
  var config = require('./config.' + require('os').hostname() + '.js');

  for (var key in config) {
    module.exports[key] = config[key];
  }
} catch (err) {
}

