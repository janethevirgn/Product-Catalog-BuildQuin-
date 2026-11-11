/* Click any <img data-lightbox> to see it larger. Images with the same data-lightbox-group can be browsed with the arrows. */
(function () {
  'use strict';
  var backdrop,
    imgEl,
    captionEl,
    prevBtn,
    nextBtn,
    items = [],
    index = 0;

  function build() {
    if (backdrop) return;
    backdrop = document.createElement('div');
    backdrop.className = 'lb-backdrop';
    backdrop.setAttribute('role', 'dialog');
    backdrop.setAttribute('aria-modal', 'true');
    backdrop.setAttribute('aria-label', 'Picture viewer');
    backdrop.innerHTML =
      '<button type="button" class="lb-btn lb-close" aria-label="Close"><span class="mi">close</span></button>' +
      '<button type="button" class="lb-btn lb-prev" aria-label="Previous picture"><span class="mi">chevron_left</span></button>' +
      '<figure class="lb-figure"><img class="lb-img" alt=""><figcaption class="lb-caption"></figcaption></figure>' +
      '<button type="button" class="lb-btn lb-next" aria-label="Next picture"><span class="mi">chevron_right</span></button>';
    document.body.appendChild(backdrop);
    imgEl = backdrop.querySelector('.lb-img');
    captionEl = backdrop.querySelector('.lb-caption');
    prevBtn = backdrop.querySelector('.lb-prev');
    nextBtn = backdrop.querySelector('.lb-next');
    backdrop.querySelector('.lb-close').addEventListener('click', close);
    prevBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      step(-1);
    });
    nextBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      step(1);
    });
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop || e.target.classList.contains('lb-figure')) close();
    });
  }

  function show() {
    var item = items[index];
    imgEl.src = item.src;
    captionEl.textContent = item.caption
      ? item.caption + (items.length > 1 ? '  (' + (index + 1) + ' / ' + items.length + ')' : '')
      : items.length > 1
        ? index + 1 + ' / ' + items.length
        : '';
    prevBtn.hidden = nextBtn.hidden = items.length < 2;
  }

  function step(by) {
    index = (index + by + items.length) % items.length;
    show();
  }
  function close() {
    if (backdrop) {
      backdrop.classList.remove('open');
      imgEl.removeAttribute('src');
    }
  }

  document.addEventListener(
    'click',
    function (event) {
      var target = event.target.closest('[data-lightbox]');
      if (!target || !target.getAttribute('src')) return;
      event.preventDefault();
      event.stopPropagation();
      build();
      var group = target.getAttribute('data-lightbox-group');
      var nodes = group
        ? Array.prototype.slice.call(
            document.querySelectorAll('[data-lightbox][data-lightbox-group="' + group + '"]'),
          )
        : [target];
      items = nodes
        .filter(function (n) {
          return n.getAttribute('src');
        })
        .map(function (n) {
          return {
            src: n.currentSrc || n.src,
            caption: n.getAttribute('data-lightbox-caption') || n.getAttribute('alt') || '',
          };
        });
      index = Math.max(
        0,
        nodes
          .filter(function (n) {
            return n.getAttribute('src');
          })
          .indexOf(target),
      );
      show();
      backdrop.classList.add('open');
    },
    true,
  );

  document.addEventListener('keydown', function (event) {
    if (!backdrop || !backdrop.classList.contains('open')) return;
    if (event.key === 'Escape') close();
    else if (event.key === 'ArrowLeft' && items.length > 1) step(-1);
    else if (event.key === 'ArrowRight' && items.length > 1) step(1);
  });
})();
