/* SITE1719: enhance only this page's existing photo links; no catalogue or search requests. */
(function () {
  'use strict';

  function initialize() {
    const page = document.querySelector('.jaz-page[data-anytour-hotel-id="99"]');
    if (!page || typeof window.HTMLDialogElement !== 'function'
      || typeof window.HTMLDialogElement.prototype.showModal !== 'function') return;

    const photos = Array.from(page.querySelectorAll('a[data-jaz-photo]')).map(link => {
      const href = link.getAttribute('href') || '';
      try {
        const url = new URL(href);
        if (url.protocol !== 'https:' || url.username || url.password || /[\s\u0000-\u001f\u007f]/.test(href)) return null;
      } catch (error) { return null; }
      return {
        link, href,
        caption: link.dataset.photoCaption || 'Фото отеля',
        note: link.dataset.photoNote || '',
        alt: link.querySelector('img')?.alt || link.dataset.photoCaption || 'Фото отеля'
      };
    }).filter(Boolean);
    if (!photos.length) return;

    let viewer = null;
    let session = null;

    function finish() {
      if (!session) return;
      const previous = session;
      session = null;
      document.documentElement.classList.remove('hotel-photo-viewer-open');
      const image = viewer.querySelector('img');
      image.onload = null;
      image.onerror = null;
      image.removeAttribute('src');
      image.hidden = true;
      if (previous.opener.isConnected) previous.opener.focus({ preventScroll: true });
      window.scrollTo({ left: previous.x, top: previous.y, behavior: 'instant' });
    }

    function close() {
      if (!viewer?.open) return;
      viewer.close();
      finish();
    }

    function show(index) {
      if (!session) return;
      const current = session;
      current.index = (index + photos.length) % photos.length;
      const photo = photos[current.index];
      const status = viewer.querySelector('.hotel-photo-viewer__status');
      const image = document.createElement('img');
      image.alt = photo.alt;
      image.hidden = true;
      // A fresh image keeps a late load/error from a previous photo out of this view.
      viewer.querySelector('img').replaceWith(image);
      viewer.dataset.photoState = 'loading';
      status.hidden = false;
      status.textContent = 'Загружаем фото…';
      image.onload = () => {
        if (session !== current || viewer.querySelector('img') !== image) return;
        viewer.dataset.photoState = 'loaded';
        status.hidden = true;
        image.hidden = false;
      };
      image.onerror = () => {
        if (session !== current || viewer.querySelector('img') !== image) return;
        viewer.dataset.photoState = 'error';
        status.textContent = 'Не удалось загрузить фото. Попробуйте другой снимок или откройте оригинал.';
        status.hidden = false;
        image.hidden = true;
      };
      viewer.querySelector('h2').textContent = photo.caption;
      viewer.querySelector('.jaz-viewer-note').textContent = photo.note;
      viewer.querySelector('.hotel-photo-viewer__counter').textContent = 'Фото ' + (current.index + 1) + ' из ' + photos.length;
      viewer.querySelector('.hotel-photo-viewer__original').href = photo.href;
      image.src = photo.href;
    }

    function createViewer() {
      const dialog = document.createElement('dialog');
      dialog.className = 'hotel-photo-viewer jaz-photo-viewer';
      dialog.setAttribute('aria-labelledby', 'jaz-photo-viewer-title');
      dialog.setAttribute('aria-describedby', 'jaz-photo-viewer-note');
      dialog.innerHTML = '<header class="hotel-photo-viewer__header">'
        + '<div class="jaz-viewer-heading"><h2 id="jaz-photo-viewer-title"></h2><p class="jaz-viewer-note" id="jaz-photo-viewer-note"></p></div>'
        + '<button type="button" class="hotel-photo-viewer__close" aria-label="Закрыть фотографии" autofocus>×</button></header>'
        + '<div class="hotel-photo-viewer__stage"><img alt="" hidden><p class="hotel-photo-viewer__status" role="status"></p></div>'
        + '<footer class="hotel-photo-viewer__footer"><div class="hotel-photo-viewer__navigation">'
        + '<button type="button" data-photo-step="-1" aria-label="Предыдущее фото">←</button>'
        + '<span class="hotel-photo-viewer__counter" role="status" aria-live="polite"></span>'
        + '<button type="button" data-photo-step="1" aria-label="Следующее фото">→</button></div>'
        + '<a class="hotel-photo-viewer__original" target="_blank" rel="noopener noreferrer">Открыть оригинал <span aria-hidden="true">↗</span></a></footer>';
      dialog.querySelector('.hotel-photo-viewer__close').addEventListener('click', close);
      dialog.querySelectorAll('[data-photo-step]').forEach(button => {
        button.hidden = photos.length < 2;
        button.addEventListener('click', () => { if (session) show(session.index + Number(button.dataset.photoStep)); });
      });
      dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
      dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) close();
      });
      // A queued close from a previous session must not clear a reopened dialog.
      dialog.addEventListener('close', () => { if (!dialog.open) finish(); });
      document.body.appendChild(dialog);
      return dialog;
    }

    page.addEventListener('click', event => {
      if (event.defaultPrevented || event.button !== 0 || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
      const link = event.target instanceof Element ? event.target.closest('a[data-jaz-photo]') : null;
      const index = photos.findIndex(photo => photo.link === link);
      if (index < 0 || session) return;
      if (!viewer) viewer = createViewer();
      session = { opener: link, index, x: window.scrollX, y: window.scrollY };
      try { viewer.showModal(); } catch (error) { session = null; return; }
      event.preventDefault();
      document.documentElement.classList.add('hotel-photo-viewer-open');
      show(index);
      viewer.querySelector('.hotel-photo-viewer__close').focus({ preventScroll: true });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
}());
