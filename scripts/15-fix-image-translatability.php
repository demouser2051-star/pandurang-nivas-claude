<?php

/**
 * @file
 * Makes the image fields non-translatable and propagates their values.
 *
 * These fields were created translatable, but the import only ever set them on
 * the Marathi node. English translations therefore had no value of their own
 * and rendered nothing - the gallery, event and album images all vanished on
 * /en pages.
 *
 * A photograph is the same photograph in either language, so the fields become
 * shared. Each affected node is then re-saved so the stored value is copied
 * across every translation row.
 *
 * Run: drush php:script scripts/15-fix-image-translatability.php
 */

use Drupal\field\Entity\FieldConfig;

$fields = [
  'gallery_item' => 'field_gi_image',
  'event' => 'field_event_image',
  'album' => 'field_album_cover',
  // No member photos are uploaded yet, but the field carries the same fault.
  'family_member' => 'field_fm_photo',
];

print "Making image fields non-translatable\n";

foreach ($fields as $bundle => $field_name) {
  $field = FieldConfig::loadByName('node', $bundle, $field_name);
  if (!$field) {
    print "  ! not found: node.$bundle.$field_name\n";
    continue;
  }

  if (!$field->isTranslatable()) {
    print "  already shared: $field_name\n";
    continue;
  }

  $field->setTranslatable(FALSE);
  $field->save();
  print "  shared: node.$bundle.$field_name\n";
}

// The definition change alone does not rewrite rows that are already stored,
// so touch every affected node to push the value into each translation.
\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
\Drupal::service('entity_type.bundle.info')->clearCachedBundles();

$storage = \Drupal::entityTypeManager()->getStorage('node');
$resaved = 0;

foreach (array_keys($fields) as $bundle) {
  $nids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', $bundle)
    ->execute();

  foreach ($storage->loadMultiple($nids) as $node) {
    // Saving without a new revision keeps the history tidy; this is a data
    // repair, not an editorial change.
    $node->setNewRevision(FALSE);
    $node->save();
    $resaved++;
  }
}

print "Re-saved $resaved nodes.\n";

// Report what the storage looks like now.
$database = \Drupal::database();
foreach ($fields as $bundle => $field_name) {
  $table = 'node__' . $field_name;
  if (!$database->schema()->tableExists($table)) {
    continue;
  }
  $rows = $database->query(
    'SELECT langcode, COUNT(*) AS c FROM {' . $table . '} GROUP BY langcode'
  )->fetchAllKeyed();

  $summary = [];
  foreach ($rows as $langcode => $count) {
    $summary[] = "$langcode=$count";
  }
  print '  ' . $field_name . ': ' . implode(' ', $summary) . "\n";
}

print "Done.\n";
