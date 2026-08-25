<?php

/**
 * @file
 * A date-only format for event dates, and the field formatters that use it.
 *
 * The default medium format appends " - 12:00", which is noise on a field
 * that only stores a date.
 *
 * Run: drush php:script scripts/11-date-formats.php
 */

use Drupal\Core\Datetime\Entity\DateFormat;

if (!DateFormat::load('pn_event_date')) {
  DateFormat::create([
    'id' => 'pn_event_date',
    'label' => 'Event date',
    'pattern' => 'j F Y',
  ])->save();
  print "  date format: pn_event_date (j F Y)\n";
}

$display_repo = \Drupal::service('entity_display.repository');

$targets = [
  ['event', 'default', 'field_event_start'],
  ['event', 'default', 'field_event_end'],
  ['event', 'teaser', 'field_event_start'],
  ['event', 'teaser', 'field_event_end'],
  ['gallery_item', 'default', 'field_gi_date'],
  ['gallery_item', 'teaser', 'field_gi_date'],
];

foreach ($targets as [$bundle, $mode, $field]) {
  $display = $display_repo->getViewDisplay('node', $bundle, $mode);
  $component = $display->getComponent($field);
  if (!$component) {
    continue;
  }

  $component['type'] = 'datetime_default';
  $component['settings'] = [
    'timezone_override' => '',
    'format_type' => 'pn_event_date',
  ];
  $display->setComponent($field, $component);
  $display->save();
  print "  formatter: node.$bundle.$mode.$field -> pn_event_date\n";
}

print "Done.\n";
