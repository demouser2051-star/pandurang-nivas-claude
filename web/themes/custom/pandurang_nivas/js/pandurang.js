/**
 * @file
 * Header, navigation, dropdown and search behaviour.
 *
 * Ported from the original main.js. The language toggle, notification list and
 * search index are now served by Drupal, so those parts talk to the back end
 * rather than to a hard-coded array.
 */

(function (Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Shrinks the header once the page has scrolled past the hero.
   */
  Drupal.behaviors.pandurangStickyHeader = {
    attach(context) {
      once('pn-sticky-header', '#header', context).forEach((header) => {
        const dropdowns = [
          ['#search', 'search-scrolled'],
          ['#user-options', 'user-scrolled'],
          ['#notifications', 'notification-scrolled'],
        ];

        const onScroll = () => {
          const scrolled = window.scrollY > 100;
          header.classList.toggle('header-scrolled', scrolled);
          dropdowns.forEach(([selector, className]) => {
            const element = document.querySelector(selector);
            if (element) {
              element.classList.toggle(className, scrolled);
            }
          });
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
      });
    },
  };

  /**
   * The hamburger menu on narrow screens.
   */
  Drupal.behaviors.pandurangMobileMenu = {
    attach(context) {
      once('pn-mobile-menu', '#menu-toggle', context).forEach((toggle) => {
        const nav = document.getElementById('main-nav');
        if (!nav) {
          return;
        }

        toggle.addEventListener('click', () => {
          const open = nav.classList.toggle('active');
          toggle.innerHTML = open
            ? '<i class="fas fa-times" aria-hidden="true"></i>'
            : '<i class="fas fa-bars" aria-hidden="true"></i>';
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // Following a link should put the menu away again.
        nav.addEventListener('click', (event) => {
          if (event.target.closest('a') && nav.classList.contains('active')) {
            nav.classList.remove('active');
            toggle.innerHTML = '<i class="fas fa-bars" aria-hidden="true"></i>';
          }
        });
      });
    },
  };

  /**
   * Search, account and notification dropdowns; only one open at a time.
   */
  Drupal.behaviors.pandurangDropdowns = {
    attach(context) {
      once('pn-dropdowns', 'body', context).forEach((body) => {
        const panels = [
          { trigger: '.search-toggle-btn', panel: '.search-dropdown' },
          { trigger: '.user-avatar', panel: '#user-options' },
          { trigger: '.notification-button', panel: '#notifications' },
        ];

        const closeAll = (except) => {
          panels.forEach(({ panel }) => {
            const element = document.querySelector(panel);
            if (element && element !== except) {
              element.classList.remove('active');
            }
          });
        };

        panels.forEach(({ trigger, panel }) => {
          const button = document.querySelector(trigger);
          const element = document.querySelector(panel);
          if (!button || !element) {
            return;
          }

          button.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = !element.classList.contains('active');
            closeAll(element);
            element.classList.toggle('active', willOpen);

            if (willOpen) {
              const input = element.querySelector('.search-input');
              if (input) {
                window.setTimeout(() => input.focus(), 100);
              }
            }
          });

          element.addEventListener('click', (event) => event.stopPropagation());
        });

        body.addEventListener('click', () => closeAll(null));

        body.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            closeAll(null);
          }
        });
      });
    },
  };

  /**
   * Live search against Drupal's search endpoint.
   */
  Drupal.behaviors.pandurangSearch = {
    attach(context) {
      once('pn-search', '.search-input', context).forEach((input) => {
        const results = document.getElementById('search-results');
        if (!results) {
          return;
        }

        let timer = null;
        let controller = null;

        const render = (items) => {
          results.innerHTML = '';

          if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'search-result-item';
            empty.textContent = Drupal.t('Nothing found');
            results.appendChild(empty);
          }
          else {
            items.forEach((item) => {
              const row = document.createElement('a');
              row.className = 'search-result-item';
              row.href = item.url;

              const title = document.createElement('div');
              title.innerHTML = `<strong>${item.title}</strong>`;

              const category = document.createElement('div');
              category.style.fontSize = '0.8rem';
              category.style.color = '#888';
              category.textContent = item.type;

              row.append(title, category);
              results.appendChild(row);
            });
          }

          results.classList.add('active');
        };

        input.addEventListener('input', () => {
          const term = input.value.trim();
          window.clearTimeout(timer);

          if (term.length < 2) {
            results.classList.remove('active');
            return;
          }

          timer = window.setTimeout(async () => {
            if (controller) {
              controller.abort();
            }
            controller = new AbortController();

            try {
              const response = await fetch(
                `${drupalSettings.path.baseUrl}pandurang/search?q=${encodeURIComponent(term)}`,
                { credentials: 'same-origin', signal: controller.signal }
              );
              if (!response.ok) {
                throw new Error(response.status);
              }
              render(await response.json());
            }
            catch (error) {
              if (error.name !== 'AbortError') {
                render([]);
              }
            }
          }, 250);
        });
      });
    },
  };

  /**
   * Plays a fade-and-rise on sections as they scroll into view.
   *
   * Purely decorative: the stylesheet leaves everything visible, and this only
   * adds the class that replays the animation. Nothing here can hide content.
   */
  Drupal.behaviors.pandurangAnimations = {
    attach(context) {
      // The last three carry the original stylesheet's slide-in; they are
      // included so that animation still plays, not to make them visible.
      const targets = once(
        'pn-animate',
        '.section, .event-card, .gallery-item, .calendar, .event-highlights, .about-content',
        context
      );
      if (!targets.length || !('IntersectionObserver' in window)) {
        return;
      }

      // A section taller than the viewport can never reach a percentage
      // threshold, so trigger on any overlap at all.
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0, rootMargin: '0px 0px -10% 0px' });

      targets.forEach((element) => observer.observe(element));
    },
  };
})(Drupal, once, drupalSettings);
