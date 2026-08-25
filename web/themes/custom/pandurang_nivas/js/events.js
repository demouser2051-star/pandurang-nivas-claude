/**
 * @file
 * The events calendar and the upcoming / past tabs.
 *
 * Event dates arrive from Drupal in drupalSettings.pandurang.eventDates as
 * ISO strings, so the calendar highlights real events rather than fixed days.
 */

(function (Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Renders one month into the calendar grid.
   */
  function renderMonth(grid, year, month, eventDays, labelElement, locale) {
    const first = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Monday-first, matching the सोम…रवि header of the original.
    const offset = (first.getDay() + 6) % 7;

    grid.innerHTML = '';

    for (let i = 0; i < offset; i++) {
      const blank = document.createElement('div');
      blank.className = 'calendar-date';
      grid.appendChild(blank);
    }

    const today = new Date();

    for (let day = 1; day <= daysInMonth; day++) {
      const cell = document.createElement('div');
      cell.className = 'calendar-date';
      cell.textContent = day.toLocaleString(locale);

      const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      if (eventDays.has(iso)) {
        cell.classList.add('event');
        cell.title = eventDays.get(iso).join(', ');
      }

      if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
        cell.classList.add('selected');
      }

      grid.appendChild(cell);
    }

    if (labelElement) {
      labelElement.textContent = first.toLocaleDateString(locale, {
        month: 'long',
        year: 'numeric',
      });
    }
  }

  Drupal.behaviors.pandurangCalendar = {
    attach(context) {
      once('pn-calendar', '.calendar', context).forEach((calendar) => {
        const grid = calendar.querySelector('.calendar-grid, .calendar-dates');
        const label = calendar.querySelector('.calendar-header h3, #calendar-month');
        const [previous, next] = calendar.querySelectorAll('.calendar-nav-btn');

        if (!grid) {
          return;
        }

        const settings = (drupalSettings.pandurang && drupalSettings.pandurang.eventDates) || [];
        const eventDays = new Map();
        settings.forEach((event) => {
          if (!eventDays.has(event.date)) {
            eventDays.set(event.date, []);
          }
          eventDays.get(event.date).push(event.title);
        });

        const locale = document.documentElement.lang === 'mr' ? 'mr-IN' : 'en-IN';
        const cursor = new Date();

        const draw = () => renderMonth(
          grid,
          cursor.getFullYear(),
          cursor.getMonth(),
          eventDays,
          label,
          locale
        );

        if (previous) {
          previous.addEventListener('click', () => {
            cursor.setMonth(cursor.getMonth() - 1);
            draw();
          });
        }
        if (next) {
          next.addEventListener('click', () => {
            cursor.setMonth(cursor.getMonth() + 1);
            draw();
          });
        }

        draw();
      });
    },
  };

  /**
   * Upcoming / festivals / past tabs on the events page.
   */
  Drupal.behaviors.pandurangEventTabs = {
    attach(context) {
      once('pn-event-tabs', '.events-tabs', context).forEach((tabs) => {
        tabs.addEventListener('click', (event) => {
          const tab = event.target.closest('[data-target]');
          if (!tab) {
            return;
          }
          event.preventDefault();

          tabs.querySelectorAll('[data-target]').forEach((t) => t.classList.remove('active'));
          tab.classList.add('active');

          const target = document.getElementById(tab.dataset.target);
          if (target) {
            const header = document.getElementById('header');
            const offset = header ? header.offsetHeight : 80;
            window.scrollTo({ top: target.offsetTop - offset, behavior: 'smooth' });
          }
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
