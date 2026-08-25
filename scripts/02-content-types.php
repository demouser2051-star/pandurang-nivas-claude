<?php

/**
 * @file
 * Content architecture for Pandurang Nivas.
 *
 * Creates the content types, fields and marks everything translatable.
 * Idempotent - safe to re-run.
 *
 * Run: drush php:script scripts/02-content-types.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

/**
 * Creates a content type if it is not there yet.
 */
function pn_node_type(string $id, string $label, string $description): void {
  if (NodeType::load($id)) {
    return;
  }
  NodeType::create([
    'type' => $id,
    'name' => $label,
    'description' => $description,
    'new_revision' => TRUE,
    'preview_mode' => DRUPAL_OPTIONAL,
    'display_submitted' => FALSE,
  ])->save();
  print "  content type: $id\n";
}

/**
 * Creates a field storage plus instance on a bundle.
 */
function pn_field(string $bundle, string $name, string $type, string $label, array $storage = [], array $instance = []): void {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $storage['cardinality'] ?? 1,
      'settings' => $storage['settings'] ?? [],
      'translatable' => TRUE,
    ])->save();
  }
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $instance['required'] ?? FALSE,
      'description' => $instance['description'] ?? '',
      'settings' => $instance['settings'] ?? [],
      'translatable' => $instance['translatable'] ?? TRUE,
    ])->save();
    print "  field: $bundle.$name ($type)\n";
  }
}

$image_settings = [
  'file_directory' => '[date:custom:Y]-[date:custom:m]',
  'file_extensions' => 'png jpg jpeg webp gif',
  'max_filesize' => '',
  'alt_field' => TRUE,
  'alt_field_required' => FALSE,
  'title_field' => FALSE,
];

// ---------------------------------------------------------------------------
// Content types.
// ---------------------------------------------------------------------------
print "Content types\n";
pn_node_type('family_member', 'Family Member', 'A person in the Pandurang Nivas family tree.');
pn_node_type('event', 'Event', 'A festival, gathering or family event.');
pn_node_type('album', 'Album', 'A named collection of gallery items.');
pn_node_type('gallery_item', 'Gallery Item', 'A photo or video in the family gallery.');
pn_node_type('notification', 'Notification', 'A short announcement shown in the notification dropdown.');

// ---------------------------------------------------------------------------
// Family member.
// ---------------------------------------------------------------------------
print "Family member fields\n";
pn_field('family_member', 'field_fm_legacy_id', 'string', 'Legacy tree ID', [], [
  'description' => 'Identifier carried over from familyTreeData.json, e.g. gen-3-5-6.',
  'translatable' => FALSE,
]);
pn_field('family_member', 'field_fm_generation', 'integer', 'Generation', [], [
  'description' => 'Generation number, 1 = Pandurang.',
  'translatable' => FALSE,
]);
pn_field('family_member', 'field_fm_sex', 'list_string', 'Sex', [
  'settings' => ['allowed_values' => ['male' => 'Male', 'female' => 'Female']],
], ['translatable' => FALSE]);
pn_field('family_member', 'field_fm_spouse', 'string', 'Spouse', [], [
  'description' => 'Spouse name as recorded in the family register.',
]);
pn_field('family_member', 'field_fm_parent', 'entity_reference', 'Parent', [
  'settings' => ['target_type' => 'node'],
], [
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => ['family_member' => 'family_member'],
      'sort' => ['field' => 'title', 'direction' => 'ASC'],
    ],
  ],
  'translatable' => FALSE,
]);
pn_field('family_member', 'field_fm_photo', 'image', 'Photo', ['settings' => ['uri_scheme' => 'public']], [
  'settings' => $image_settings,
]);
pn_field('family_member', 'field_fm_notes', 'text_long', 'Notes', [], []);

// ---------------------------------------------------------------------------
// Event.
// ---------------------------------------------------------------------------
print "Event fields\n";
pn_field('event', 'field_event_start', 'datetime', 'Start date', [
  'settings' => ['datetime_type' => 'date'],
], ['required' => TRUE, 'translatable' => FALSE]);
pn_field('event', 'field_event_end', 'datetime', 'End date', [
  'settings' => ['datetime_type' => 'date'],
], ['translatable' => FALSE]);
pn_field('event', 'field_event_location', 'string', 'Location', [], []);
pn_field('event', 'field_event_time', 'string', 'Timing', [], [
  'description' => 'Human readable timing shown on the event card.',
]);
pn_field('event', 'field_event_type', 'list_string', 'Event type', [
  'settings' => [
    'allowed_values' => [
      'festival' => 'Festival',
      'gathering' => 'Gathering',
      'trip' => 'Trip',
      'ceremony' => 'Ceremony',
    ],
  ],
], ['translatable' => FALSE]);
pn_field('event', 'field_event_image', 'image', 'Image', ['settings' => ['uri_scheme' => 'public']], [
  'settings' => $image_settings,
]);
pn_field('event', 'field_event_rsvp', 'boolean', 'Collect RSVP', [], [
  'description' => 'Show Going / Maybe / Not going buttons to family members.',
  'translatable' => FALSE,
]);

// ---------------------------------------------------------------------------
// Album and gallery item.
// ---------------------------------------------------------------------------
print "Album fields\n";
pn_field('album', 'field_album_cover', 'image', 'Cover image', ['settings' => ['uri_scheme' => 'public']], [
  'settings' => $image_settings,
]);

print "Gallery item fields\n";
pn_field('gallery_item', 'field_gi_type', 'list_string', 'Media type', [
  'settings' => ['allowed_values' => ['image' => 'Photo', 'video' => 'Video']],
], ['required' => TRUE, 'translatable' => FALSE]);
pn_field('gallery_item', 'field_gi_image', 'image', 'Photo', ['settings' => ['uri_scheme' => 'public']], [
  'settings' => $image_settings,
  'description' => 'For videos this is used as the poster frame.',
]);
pn_field('gallery_item', 'field_gi_video', 'file', 'Video file', [
  'settings' => ['uri_scheme' => 'public', 'target_type' => 'file'],
], [
  'settings' => [
    'file_directory' => 'videos',
    'file_extensions' => 'mp4 webm ogg',
  ],
  'translatable' => FALSE,
]);
pn_field('gallery_item', 'field_gi_album', 'entity_reference', 'Album', [
  'settings' => ['target_type' => 'node'],
], [
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => ['album' => 'album'],
      'sort' => ['field' => 'title', 'direction' => 'ASC'],
    ],
  ],
  'translatable' => FALSE,
]);
pn_field('gallery_item', 'field_gi_date', 'datetime', 'Date taken', [
  'settings' => ['datetime_type' => 'date'],
], ['translatable' => FALSE]);
pn_field('gallery_item', 'field_gi_caption', 'string', 'Caption', [], [
  'description' => 'Short date label shown on the tile.',
]);

print "Done.\n";
