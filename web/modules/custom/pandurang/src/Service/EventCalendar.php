<?php

declare(strict_types=1);

namespace Drupal\pandurang\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Supplies the dated event list the calendars mark their dots from.
 *
 * Shared by the front page and the events page so both mark the same days.
 */
class EventCalendar {

  /**
   * Guards against a runaway date range in badly entered content.
   */
  protected const MAX_EVENT_DAYS = 60;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Every day on which something happens, in the active language.
   *
   * A festival that runs from the 25th to the 2nd marks all nine days, so the
   * calendar shows the whole span rather than only its first day.
   *
   * @return array
   *   Rows of date (Y-m-d), title, url and type.
   */
  public function getDatedEvents(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'event')
      ->condition('status', NodeInterface::PUBLISHED)
      ->exists('field_event_start')
      ->sort('field_event_start', 'ASC')
      ->execute();

    if (!$nids) {
      return [];
    }

    $dates = [];

    foreach ($storage->loadMultiple($nids) as $node) {
      if ($node->hasTranslation($langcode)) {
        $node = $node->getTranslation($langcode);
      }

      $start = $node->get('field_event_start')->value;
      if (!$start) {
        continue;
      }

      $end = $node->get('field_event_end')->isEmpty()
        ? $start
        : $node->get('field_event_end')->value;

      // The dot colour follows the event type: festivals are marked in red,
      // everything else in orange, matching the legend.
      $type = $node->get('field_event_type')->value ?? 'gathering';

      try {
        $cursor = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);
      }
      catch (\Exception $e) {
        continue;
      }

      if ($last < $cursor) {
        $last = $cursor;
      }

      $days = 0;
      while ($cursor <= $last && $days < self::MAX_EVENT_DAYS) {
        $dates[] = [
          'date' => $cursor->format('Y-m-d'),
          'title' => $node->label(),
          'url' => $node->toUrl()->toString(),
          'type' => $type === 'festival' ? 'festival' : 'event',
          'nid' => (int) $node->id(),
        ];
        $cursor = $cursor->modify('+1 day');
        $days++;
      }
    }

    return $dates;
  }

  /**
   * Events happening within the next few weeks.
   *
   * An event qualifies if it starts inside the window, or if it started
   * earlier and is still running - a nine-day festival should keep showing
   * once it has begun, not vanish on its opening day.
   *
   * @param int $days
   *   How far ahead to look.
   * @param string|null $today
   *   The day to count from, as Y-m-d. Defaults to the current date; passing
   *   it explicitly makes the window testable against fixed content.
   *
   * @return array
   *   Rows of nid, title, url, start, end, location, timing, type and
   *   days_away (0 for today, negative never returned).
   */
  public function getUpcoming(int $days = 30, ?string $today = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    try {
      $from = new \DateTimeImmutable($today ?? 'today');
    }
    catch (\Exception $e) {
      $from = new \DateTimeImmutable('today');
    }
    $horizon = $from->modify("+$days days");

    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'event')
      ->condition('status', NodeInterface::PUBLISHED)
      ->exists('field_event_start')
      // Anything starting after the horizon is not our concern; the trailing
      // edge is checked below, where the empty end date is easy to handle.
      ->condition('field_event_start', $horizon->format('Y-m-d'), '<=')
      ->sort('field_event_start', 'ASC')
      ->execute();

    if (!$nids) {
      return [];
    }

    $upcoming = [];

    foreach ($storage->loadMultiple($nids) as $node) {
      if ($node->hasTranslation($langcode)) {
        $node = $node->getTranslation($langcode);
      }

      $start_value = $node->get('field_event_start')->value;
      if (!$start_value) {
        continue;
      }

      $end_value = $node->get('field_event_end')->isEmpty()
        ? $start_value
        : $node->get('field_event_end')->value;

      try {
        $start = new \DateTimeImmutable($start_value);
        $end = new \DateTimeImmutable($end_value);
      }
      catch (\Exception $e) {
        continue;
      }

      // Finished before today: not upcoming.
      if ($end < $from) {
        continue;
      }

      $upcoming[] = [
        'nid' => (int) $node->id(),
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'location' => $node->get('field_event_location')->value,
        'timing' => $node->get('field_event_time')->value,
        'type' => $node->get('field_event_type')->value ?? 'gathering',
        // Negative while an event is already running; clamped to zero so the
        // label reads "today" rather than "in -3 days".
        'days_away' => max(0, (int) $from->diff($start)->format('%r%a')),
        'running' => $start <= $from,
      ];
    }

    return $upcoming;
  }

  /**
   * The render-array additions a calendar needs: library plus event data.
   */
  public function attachments(): array {
    return [
      'library' => ['pandurang_nivas/events'],
      'drupalSettings' => [
        'pandurang' => ['eventDates' => $this->getDatedEvents()],
      ],
    ];
  }

}
