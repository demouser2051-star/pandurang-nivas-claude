<?php

/**
 * @file
 * Splits the events view into upcoming and recent displays, and gives the
 * events page an upcoming / past split.
 *
 * Run: drush php:script scripts/10-events-displays.php
 */

use Drupal\views\Entity\View;

$view = View::load('pn_events');
if (!$view) {
  print "pn_events view not found.\n";
  return;
}

$display = $view->get('display');

/**
 * A filter keeping only events on or after today.
 */
function pn_upcoming_filter(): array {
  return [
    'field_event_start_value' => [
      'id' => 'field_event_start_value',
      'table' => 'node__field_event_start',
      'field' => 'field_event_start_value',
      'plugin_id' => 'datetime',
      'operator' => '>=',
      'value' => [
        'type' => 'offset',
        'value' => 'now',
        'min' => '',
        'max' => '',
      ],
      'group' => 1,
    ],
  ];
}

/**
 * A filter keeping only events before today.
 */
function pn_past_filter(): array {
  $filter = pn_upcoming_filter();
  $filter['field_event_start_value']['operator'] = '<';
  return $filter;
}

// The front-page block: the next two events.
$display['block_upcoming']['display_options']['filters'] = pn_upcoming_filter();
$display['block_upcoming']['display_options']['defaults']['filters'] = FALSE;
$display['block_upcoming']['display_options']['defaults']['filter_groups'] = FALSE;
$display['block_upcoming']['display_options']['filter_groups'] = [
  'operator' => 'AND',
  'groups' => [1 => 'AND'],
];

// A sibling block used only when nothing is coming up.
$display['block_recent'] = [
  'display_plugin' => 'block',
  'id' => 'block_recent',
  'display_title' => 'Recent events block',
  'position' => 3,
  'display_options' => [
    'display_description' => 'The two most recent events, shown when nothing is upcoming.',
    'pager' => [
      'type' => 'some',
      'options' => ['items_per_page' => 2, 'offset' => 0],
    ],
    'sorts' => [
      'field_event_start_value' => [
        'id' => 'field_event_start_value',
        'table' => 'node__field_event_start',
        'field' => 'field_event_start_value',
        'plugin_id' => 'datetime',
        'order' => 'DESC',
      ],
    ],
    'defaults' => ['pager' => FALSE, 'sorts' => FALSE],
  ],
];

// The events page gets an upcoming-first ordering.
$display['page_1']['display_options']['sorts'] = [
  'field_event_start_value' => [
    'id' => 'field_event_start_value',
    'table' => 'node__field_event_start',
    'field' => 'field_event_start_value',
    'plugin_id' => 'datetime',
    'order' => 'DESC',
  ],
];
$display['page_1']['display_options']['defaults']['sorts'] = FALSE;

$view->set('display', $display);
$view->save();

print "pn_events: block_upcoming filtered to future dates, block_recent added.\n";

// The gallery block should lead with the newest items.
$gallery = View::load('pn_gallery');
if ($gallery) {
  $gallery_display = $gallery->get('display');
  $gallery_display['block_1']['display_options']['sorts'] = [
    'field_gi_date_value' => [
      'id' => 'field_gi_date_value',
      'table' => 'node__field_gi_date',
      'field' => 'field_gi_date_value',
      'plugin_id' => 'datetime',
      'order' => 'DESC',
    ],
  ];
  $gallery_display['block_1']['display_options']['defaults']['sorts'] = FALSE;
  $gallery->set('display', $gallery_display);
  $gallery->save();
  print "pn_gallery: block sorted newest first.\n";
}

print "Done.\n";
