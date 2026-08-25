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
