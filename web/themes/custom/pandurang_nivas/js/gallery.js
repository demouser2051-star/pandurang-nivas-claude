/**
 * @file
 * Gallery filtering and the lightbox.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Builds the lightbox once and reuses it for every image.
   */
  function getLightbox() {
    let lightbox = document.querySelector('.pn-lightbox');
    if (lightbox) {
      return lightbox;
    }

    lightbox = document.createElement('div');
    lightbox.className = 'pn-lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.hidden = true;
    lightbox.innerHTML = `
      <button class="pn-lightbox__close" type="button" aria-label="${Drupal.t('Close')}">&times;</button>
      <button class="pn-lightbox__nav pn-lightbox__nav--prev" type="button" aria-label="${Drupal.t('Previous')}">&#8249;</button>
      <figure class="pn-lightbox__figure">
        <img class="pn-lightbox__image" src="" alt="">
        <figcaption class="pn-lightbox__caption marathi-font"></figcaption>
      </figure>
      <button class="pn-lightbox__nav pn-lightbox__nav--next" type="button" aria-label="${Drupal.t('Next')}">&#8250;</button>
    `;
    document.body.appendChild(lightbox);
    return lightbox;
  }

  Drupal.behaviors.pandurangGallery = {
    attach(context) {
      // Filter tabs.
      once('pn-gallery-tabs', '.gallery-tabs', context).forEach((tabs) => {
        const grid = document.querySelector('.gallery-grid, .view-content');
        if (!grid) {
          return;
        }

        tabs.addEventListener('click', (event) => {
          const tab = event.target.closest('.gallery-tab');
          if (!tab) {
            return;
          }

          tabs.querySelectorAll('.gallery-tab').forEach((t) => t.classList.remove('active'));
          tab.classList.add('active');

          const wanted = tab.dataset.type;
          grid.querySelectorAll('[data-type]').forEach((item) => {
            item.hidden = wanted !== 'all' && item.dataset.type !== wanted;
          });
        });
      });

      // Lightbox.
      const items = once('pn-lightbox', '.gallery-item[data-type="image"], .photo-item', context);
      if (!items.length) {
        return;
      }

      const lightbox = getLightbox();
      const image = lightbox.querySelector('.pn-lightbox__image');
      const caption = lightbox.querySelector('.pn-lightbox__caption');
      let gallery = [];
      let current = 0;

      const render = () => {
        const item = gallery[current];
        if (!item) {
          return;
        }
        image.src = item.src;
        image.alt = item.alt;
        caption.textContent = item.caption;
      };

      const open = (index) => {
        gallery = Array.from(document.querySelectorAll('.gallery-item[data-type="image"], .photo-item'))
          .filter((element) => !element.hidden)
          .map((element) => {
            const img = element.querySelector('img');
            const title = element.querySelector('h4, .photo-info h4');
            return {
              src: img ? (img.dataset.full || img.src) : '',
              alt: img ? img.alt : '',
              caption: title ? title.textContent.trim() : '',
            };
          });

        current = index;
        render();
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
        lightbox.querySelector('.pn-lightbox__close').focus();
      };

      const close = () => {
        lightbox.hidden = true;
        image.src = '';
        document.body.style.overflow = '';
      };

      const move = (delta) => {
        current = (current + delta + gallery.length) % gallery.length;
        render();
      };

      items.forEach((item) => {
        item.addEventListener('click', () => {
          const visible = Array.from(document.querySelectorAll('.gallery-item[data-type="image"], .photo-item'))
            .filter((element) => !element.hidden);
          open(Math.max(0, visible.indexOf(item)));
        });
      });

      once('pn-lightbox-controls', lightbox).forEach(() => {
        lightbox.querySelector('.pn-lightbox__close').addEventListener('click', close);
        lightbox.querySelector('.pn-lightbox__nav--prev').addEventListener('click', () => move(-1));
        lightbox.querySelector('.pn-lightbox__nav--next').addEventListener('click', () => move(1));

        lightbox.addEventListener('click', (event) => {
          if (event.target === lightbox) {
            close();
          }
        });

        document.addEventListener('keydown', (event) => {
          if (lightbox.hidden) {
            return;
          }
          if (event.key === 'Escape') {
            close();
          }
          if (event.key === 'ArrowLeft') {
            move(-1);
          }
          if (event.key === 'ArrowRight') {
            move(1);
          }
        });
      });
    },
  };
})(Drupal, once);
