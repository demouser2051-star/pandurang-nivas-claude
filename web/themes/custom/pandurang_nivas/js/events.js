/**
 * @file
 * The event calendar and the events-page tabs.
 *
 * Event dates arrive from Drupal in drupalSettings.pandurang.eventDates, one
 * row per day per event, so a festival that runs over several days marks all
 * of them. Two layouts are supported: the front page renders its weekday row
 * and its dates into the same block, while the events page keeps the dates in
 * their own .calendar-dates container.
 */

(function (Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Groups the flat event list by ISO date.
   */
  function indexByDate() {
    const rows = (drupalSettings.pandurang && drupalSettings.pandurang.eventDates) || [];
    const byDate = new Map();

    rows.forEach((row) => {
      if (!byDate.has(row.date)) {
        byDate.set(row.date, []);
      }
      byDate.get(row.date).push(row);
    });

    return byDate;
  }

  /**
   * Local ISO date, avoiding the UTC shift toISOString() would introduce.
   */
  function isoDate(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
  }

  /**
   * Which month to open on.
   *
   * The current month is the obvious choice, but it is no use when it holds
   * nothing: opening on an empty grid hides every marked date behind the
   * arrows. So the calendar starts on the month of the next event, or failing
   * that the most recent one, and only falls back to today when there are no
   * events at all.
   */
  function openingMonth(byDate) {
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), 1);

    if (!byDate.size) {
      return start;
    }

    const days = Array.from(byDate.keys()).sort();
    const todayIso = isoDate(today.getFullYear(), today.getMonth(), today.getDate());

    // Anything in the current month already? Then today's month is fine.
    const thisMonth = todayIso.slice(0, 7);
    if (days.some((d) => d.slice(0, 7) === thisMonth)) {
      return start;
    }

    const upcoming = days.find((d) => d >= todayIso);
    const chosen = upcoming || days[days.length - 1];
    const [year, month] = chosen.split('-').map(Number);

    return new Date(year, month - 1, 1);
  }

  /**
   * Draws one month into the dates container.
   */
  function renderMonth(calendar, cursor, byDate, locale) {
    const dates = calendar.querySelector('.calendar-dates')
      || calendar.querySelector('.calendar-grid');
    const label = calendar.querySelector('.calendar-header h3');

    if (!dates) {
      return;
    }

    const year = cursor.getFullYear();
    const month = cursor.getMonth();
    const first = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Monday-first, matching the सोम…रवि header.
    const offset = (first.getDay() + 6) % 7;
    const today = new Date();

    dates.innerHTML = '';

    for (let i = 0; i < offset; i += 1) {
      const blank = document.createElement('div');
      blank.className = 'calendar-date is-empty';
      dates.appendChild(blank);
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      const iso = isoDate(year, month, day);
      const entries = byDate.get(iso) || [];

      const cell = document.createElement(entries.length ? 'button' : 'div');
      cell.className = 'calendar-date';
      cell.textContent = day.toLocaleString(locale);

      if (entries.length) {
        cell.type = 'button';
        // A festival colours the dot red, anything else orange.
        cell.classList.add(entries.some((e) => e.type === 'festival') ? 'festival' : 'event');
        cell.dataset.date = iso;
        cell.title = entries.map((e) => e.title).join(', ');
        cell.setAttribute('aria-label', `${day}: ${cell.title}`);
      }

      if (year === today.getFullYear()
        && month === today.getMonth()
        && day === today.getDate()) {
        cell.classList.add('today', 'selected');
      }

      dates.appendChild(cell);
    }

    if (label) {
      label.textContent = first.toLocaleDateString(locale, {
        month: 'long',
        year: 'numeric',
      });
    }
  }

  Drupal.behaviors.pandurangCalendar = {
    attach() {
      // Scanned from the document rather than the passed context: BigPipe
      // swaps the calendar in after the first attach, and a context-scoped
      // search can miss the replacement. once() still keeps the wiring to a
      // single pass per element.
      const calendars = once('pn-calendar', '[data-pn-calendar], .calendar');

      calendars.forEach((calendar) => {
        const locale = document.documentElement.lang === 'mr' ? 'mr-IN' : 'en-IN';

        // Read the settings on every draw, not once at attach: with BigPipe
        // the event data can land after this behaviour first runs.
        let byDate = indexByDate();
        let cursor = openingMonth(byDate);
        let positioned = byDate.size > 0;

        const draw = () => {
          byDate = indexByDate();

          // If the data only turned up later, move to the month worth showing
          // before drawing - but never override a month the reader chose.
          if (!positioned && byDate.size > 0) {
            cursor = openingMonth(byDate);
            positioned = true;
          }

          renderMonth(calendar, cursor, byDate, locale);
        };

        const previous = calendar.querySelector('[data-pn-calendar-prev]')
          || calendar.querySelectorAll('.calendar-nav-btn')[0];
        const next = calendar.querySelector('[data-pn-calendar-next]')
          || calendar.querySelectorAll('.calendar-nav-btn')[1];

        if (previous) {
          previous.addEventListener('click', () => {
            positioned = true;
            cursor.setMonth(cursor.getMonth() - 1);
            draw();
          });
        }

        if (next) {
          next.addEventListener('click', () => {
            positioned = true;
            cursor.setMonth(cursor.getMonth() + 1);
            draw();
          });
        }

        // Clicking a marked day names what is on, and links to it.
        const readout = calendar.querySelector('[data-pn-calendar-selected]');

        calendar.addEventListener('click', (event) => {
          const cell = event.target.closest('.calendar-date[data-date]');
          if (!cell) {
            return;
          }

          calendar.querySelectorAll('.calendar-date.selected').forEach((c) => {
            c.classList.remove('selected');
          });
          cell.classList.add('selected');

          if (!readout) {
            return;
          }

          const entries = byDate.get(cell.dataset.date) || [];
          readout.innerHTML = '';

          entries.forEach((entry, index) => {
            if (index > 0) {
              readout.appendChild(document.createTextNode(' · '));
            }
            const link = document.createElement('a');
            link.href = entry.url;
            link.textContent = entry.title;
            readout.appendChild(link);
          });
        });

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
