(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (target.matches('[data-dzen-copy]')) {
      var source = document.querySelector(target.getAttribute('data-dzen-copy'));
      if (!source) {
        return;
      }

      var value = source.textContent || '';
      if (navigator.clipboard && value) {
        navigator.clipboard.writeText(value).catch(function () {});
      }
    }
  });
})();

