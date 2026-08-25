<?php

/**
 * @file
 * Puts the event calendar on the events page, above the card grid.
 *
 * Run: drush php:script scripts/17-events-calendar-block.php
 */

use Drupal\block\Entity\Block;

$theme = 'pandurang_nivas';
$id = 'pn_event_calendar';

if ($block = Block::load($id)) {
  $block->delete();
}

Block::create([
  'id' => $id,
  'theme' => $theme,
  'region' => 'highlighted',
  'plugin' => 'pandurang_event_calendar',
  'weight' => 10,
  'settings' => [
    'id' => 'pandurang_event_calendar',
    'label' => 'Event calendar',
    'label_display' => '0',
    'provider' => 'pandurang',
  ],
  // Only on the events listing; the front page renders its own calendar.
  'visibility' => [
    'request_path' => [
      'id' => 'request_path',
      'negate' => FALSE,
      'pages' => "/events\n/events/*",
    ],
  ],
])->save();

print "  block: $id -> highlighted (events page only)\n";
print "Done.\n";
