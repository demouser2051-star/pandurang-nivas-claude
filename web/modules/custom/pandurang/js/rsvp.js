/**
 * @file
 * Sends event responses to the server and reflects the new head count.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Posts one answer and hands back the server's updated counts.
   */
  async function sendResponse(nid, status) {
    const tokenResponse = await fetch(
      `${drupalSettings.path.baseUrl}session/token`,
      { credentials: 'same-origin' }
    );
    const token = await tokenResponse.text();

    const response = await fetch(
      `${drupalSettings.path.baseUrl}pandurang/rsvp/${nid}`,
      {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
        },
        body: JSON.stringify({ status }),
      }
    );

    if (!response.ok) {
      throw new Error(`RSVP failed with status ${response.status}`);
    }
    return response.json();
  }

  /**
   * Writes the counts returned by the server back into the widget.
   */
  function paintCounts(widget, counts) {
    Object.keys(counts).forEach((status) => {
      const target = widget.querySelector(`[data-rsvp-count="${status}"]`);
      if (target) {
        target.textContent = counts[status].people;
      }
    });
  }

  Drupal.behaviors.pandurangRsvp = {
    attach(context) {
      once('pn-rsvp', '[data-rsvp-widget]', context).forEach((widget) => {
        const nid = widget.dataset.rsvpWidget;
        const buttons = widget.querySelectorAll('[data-rsvp-status]');

        buttons.forEach((button) => {
          button.addEventListener('click', async (event) => {
            event.preventDefault();
            const chosen = button.dataset.rsvpStatus;

            // Clicking the active answer withdraws it.
            const next = button.classList.contains('is-active') ? 'clear' : chosen;

            buttons.forEach((b) => { b.disabled = true; });
            widget.classList.add('is-saving');

            try {
              const result = await sendResponse(nid, next);
              buttons.forEach((b) => {
                b.classList.toggle('is-active', b.dataset.rsvpStatus === result.status);
              });
              paintCounts(widget, result.counts);
              widget.classList.remove('has-error');
            }
            catch (error) {
              widget.classList.add('has-error');
              const message = widget.querySelector('[data-rsvp-message]');
              if (message) {
                message.textContent = Drupal.t('Your response could not be saved. Please try again.');
              }
            }
            finally {
              buttons.forEach((b) => { b.disabled = false; });
              widget.classList.remove('is-saving');
            }
          });
        });
      });
    },
  };
})(Drupal, once);
