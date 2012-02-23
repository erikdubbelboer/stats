
$(function() {
  $('li.folder').click(function(e) {
    if (e.target.nodeName.toLowerCase() == 'a') {
      e.stopPropagation();
      return;
    }

    var t = $(this);

    if ((e.pageY >= t.offset().top) &&
        (e.pageY <= t.offset().top + t.children('div').height())) {
      e.stopPropagation();
      t.toggleClass('collapsed');
    }
  });

  $('#save').click(function() {
    var stored = $.cookie('storedstats');

    if (stored === null) {
      stored = [];
    } else {
      stored = stored.split(',');
    }

    stored.push(document.location.search.substr(1));

    $.cookie('storedstats', stored.join(','), { expires: 365 });

    alert('Saved');
  });
});

