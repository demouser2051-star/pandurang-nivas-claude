/**
 * @file
 * The hero slideshow: auto-advance, arrows, dots and swipe.
 */

(function (Drupal, once) {
  'use strict';

  const INTERVAL = 6000;

  Drupal.behaviors.pandurangSlideshow = {
    attach(context) {
      once('pn-slideshow', '.hero-slideshow', context).forEach((slideshow) => {
        const slides = slideshow.querySelectorAll('.hero-slide');
        const dots = slideshow.querySelectorAll('.slide-dot');
        const previous = slideshow.querySelector('.prev-slide');
        const next = slideshow.querySelector('.next-slide');

        if (slides.length < 2) {
          return;
        }

        let index = 0;
        let timer = null;

        const show = (target) => {
          index = (target + slides.length) % slides.length;
          slides.forEach((slide, i) => slide.classList.toggle('active', i === index));
          dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
        };

        const start = () => {
          window.clearInterval(timer);
          timer = window.setInterval(() => show(index + 1), INTERVAL);
        };

        const step = (delta) => {
          show(index + delta);
          start();
        };

        if (next) {
          next.addEventListener('click', () => step(1));
        }
        if (previous) {
          previous.addEventListener('click', () => step(-1));
        }

        dots.forEach((dot, i) => {
          dot.addEventListener('click', () => {
            show(i);
            start();
          });
        });

        // Pause while the pointer rests on the hero.
        slideshow.addEventListener('mouseenter', () => window.clearInterval(timer));
        slideshow.addEventListener('mouseleave', start);

        // Swipe on touch devices.
        let startX = null;
        slideshow.addEventListener('touchstart', (event) => {
          startX = event.changedTouches[0].clientX;
        }, { passive: true });

        slideshow.addEventListener('touchend', (event) => {
          if (startX === null) {
            return;
          }
          const delta = event.changedTouches[0].clientX - startX;
          if (Math.abs(delta) > 45) {
            step(delta < 0 ? 1 : -1);
          }
          startX = null;
        }, { passive: true });

        // Nothing should keep animating while the tab is hidden.
        document.addEventListener('visibilitychange', () => {
          if (document.hidden) {
            window.clearInterval(timer);
          }
          else {
            start();
          }
        });

        show(0);
        start();
      });
    },
  };
})(Drupal, once);
