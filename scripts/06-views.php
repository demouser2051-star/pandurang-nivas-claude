<?php

/**
 * @file
 * Creates the gallery and events listing views.
 *
 * Both render nodes in the teaser view mode, so the theme's teaser templates
 * decide the markup and the family can still reorder or filter from the
 * Views UI.
 *
 * Run: drush php:script scripts/06-views.php
 */

use Drupal\views\Entity\View;

/**
 * Shared skeleton for a node listing view.
 */
function pn_view_defaults(string $bundle, string $title, int $per_page, array $sorts): array {
  return [
    'title' => $title,
    'fields' => [],
    'pager' => [
      'type' => 'full',
      'options' => [
        'items_per_page' => $per_page,
        'offset' => 0,
        'quantity' => 7,
      ],
    ],
    'style' => [
      'type' => 'default',
      'options' => ['row_class' => '', 'default_row_class' => TRUE],
    ],
    'row' => [
      'type' => 'entity:node',
      'options' => ['view_mode' => 'teaser'],
    ],
    'filters' => [
      'status' => [
        'id' => 'status',
        'table' => 'node_field_data',
        'field' => 'status',
        'entity_type' => 'node',
        'entity_field' => 'status',
        'plugin_id' => 'boolean',
        'value' => '1',
        'group' => 1,
      ],
      'type' => [
        'id' => 'type',
        'table' => 'node_field_data',
        'field' => 'type',
        'entity_type' => 'node',
        'entity_field' => 'type',
        'plugin_id' => 'bundle',
        'value' => [$bundle => $bundle],
        'group' => 1,
      ],
      'langcode' => [
        'id' => 'langcode',
        'table' => 'node_field_data',
        'field' => 'langcode',
        'entity_type' => 'node',
        'entity_field' => 'langcode',
        'plugin_id' => 'language',
        'value' => ['***LANGUAGE_language_content***' => '***LANGUAGE_language_content***'],
        'group' => 1,
      ],
    ],
    'sorts' => $sorts,
    'arguments' => [],
    'display_extenders' => [],
    'rendering_language' => '***LANGUAGE_language_content***',
    'use_ajax' => FALSE,
    'empty' => [
      'area_text_custom' => [
        'id' => 'area_text_custom',
        'table' => 'views',
        'field' => 'area_text_custom',
        'plugin_id' => 'text_custom',
        'empty' => TRUE,
        'content' => 'Nothing here yet.',
      ],
    ],
  ];
}

// ---------------------------------------------------------------------------
// Gallery.
// ---------------------------------------------------------------------------
if (!View::load('pn_gallery')) {
  $gallery = View::create([
    'id' => 'pn_gallery',
    'label' => 'Family gallery',
    'module' => 'node',
    'description' => 'Photos and videos, newest first.',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'display_plugin' => 'default',
        'id' => 'default',
        'display_title' => 'Default',
        'position' => 0,
        'display_options' => pn_view_defaults('gallery_item', 'चित्रदालन', 12, [
          'field_gi_date_value' => [
            'id' => 'field_gi_date_value',
            'table' => 'node__field_gi_date',
            'field' => 'field_gi_date_value',
            'plugin_id' => 'datetime',
            'order' => 'DESC',
          ],
        ]) + [
          'access' => [
            'type' => 'perm',
            'options' => ['perm' => 'view pn private content'],
          ],
          'css_class' => 'gallery-grid',
        ],
      ],
      'page_1' => [
        'display_plugin' => 'page',
        'id' => 'page_1',
        'display_title' => 'Gallery page',
        'position' => 1,
        'display_options' => [
          'path' => 'gallery',
          'menu' => [
            'type' => 'normal',
            'title' => 'चित्रदालन',
            'menu_name' => 'main',
            'weight' => 3,
          ],
        ],
      ],
      'block_1' => [
        'display_plugin' => 'block',
        'id' => 'block_1',
        'display_title' => 'Gallery teaser block',
        'position' => 2,
        'display_options' => [
          'display_description' => 'Six recent items for the front page.',
          'pager' => [
            'type' => 'some',
            'options' => ['items_per_page' => 6, 'offset' => 0],
          ],
          'defaults' => ['pager' => FALSE],
        ],
      ],
    ],
  ]);
  $gallery->save();
  print "  view: pn_gallery (/gallery)\n";
}

// ---------------------------------------------------------------------------
// Events.
// ---------------------------------------------------------------------------
if (!View::load('pn_events')) {
  $events = View::create([
    'id' => 'pn_events',
    'label' => 'Family events',
    'module' => 'node',
    'description' => 'Festivals and gatherings, soonest first.',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'display_plugin' => 'default',
        'id' => 'default',
        'display_title' => 'Default',
        'position' => 0,
        'display_options' => pn_view_defaults('event', 'कार्यक्रम', 10, [
          'field_event_start_value' => [
            'id' => 'field_event_start_value',
            'table' => 'node__field_event_start',
            'field' => 'field_event_start_value',
            'plugin_id' => 'datetime',
            'order' => 'DESC',
          ],
        ]) + [
          'access' => [
            'type' => 'perm',
            'options' => ['perm' => 'access content'],
          ],
          'css_class' => 'events-grid',
        ],
      ],
      'page_1' => [
        'display_plugin' => 'page',
        'id' => 'page_1',
        'display_title' => 'Events page',
        'position' => 1,
        'display_options' => [
          'path' => 'events',
          'menu' => [
            'type' => 'normal',
            'title' => 'कार्यक्रम',
            'menu_name' => 'main',
            'weight' => 4,
          ],
        ],
      ],
      'block_upcoming' => [
        'display_plugin' => 'block',
        'id' => 'block_upcoming',
        'display_title' => 'Upcoming events block',
        'position' => 2,
        'display_options' => [
          'display_description' => 'The next two events, for the front page.',
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
              'order' => 'ASC',
            ],
          ],
          'defaults' => ['pager' => FALSE, 'sorts' => FALSE],
        ],
      ],
    ],
  ]);
  $events->save();
  print "  view: pn_events (/events)\n";
}

// ---------------------------------------------------------------------------
// A teaser view mode for each type, so the theme templates have one to target.
// ---------------------------------------------------------------------------
$display_repo = \Drupal::service('entity_display.repository');

$teaser_fields = [
  'gallery_item' => ['field_gi_image', 'field_gi_caption'],
  'event' => ['field_event_image', 'field_event_start', 'field_event_location', 'field_event_time', 'body'],
  'album' => ['field_album_cover', 'body'],
];

foreach ($teaser_fields as $bundle => $fields) {
  $display = $display_repo->getViewDisplay('node', $bundle, 'teaser');
  if ($display->isNew()) {
    $display->setStatus(TRUE);
  }
  $weight = 0;
  foreach ($fields as $field) {
    $display->setComponent($field, ['label' => 'hidden', 'weight' => $weight++]);
  }
  $display->save();
  print "  teaser display: node.$bundle\n";
}

print "Done.\n";
