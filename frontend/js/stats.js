
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
});

