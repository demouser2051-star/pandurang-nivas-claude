<?php

/**
 * @file
 * Replaces the family register with the 2022 spreadsheet.
 *
 * The earlier import came from familyTreeData.json and was rooted at
 * पांडुरंग. The spreadsheet (FAMILY TREE [MM] 21.10.2022.xlsx, parsed into
 * data/familyTree-2022.json) goes a generation further back, to his father
 * धोंडोजीं, and records full names rather than given names.
 *
 * Every existing family_member node is exported to data/backups/ first, then
 * deleted, then the spreadsheet is imported in generation order so a parent
 * always exists before its children.
 *
 * Dry run:  drush php:script scripts/18-replace-family-tree.php -- --dry
 * Apply:    drush php:script scripts/18-replace-family-tree.php
 */

use Drupal\node\Entity\Node;

$dry_run = in_array('--dry', $extra ?? [], TRUE);

$source = __DIR__ . '/../data/familyTree-2022.json';
if (!is_file($source)) {
  print "Missing $source\n";
  return;
}

$people = json_decode(file_get_contents($source), TRUE);
if (!$people) {
  print "Could not parse $source\n";
  return;
}

$storage = \Drupal::entityTypeManager()->getStorage('node');

// ---------------------------------------------------------------------------
// Back up what is there now.
// ---------------------------------------------------------------------------
$existing_nids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'family_member')
  ->sort('nid', 'ASC')
  ->execute();

print 'Existing family members: ' . count($existing_nids) . "\n";
print 'Spreadsheet holds: ' . count($people) . "\n\n";

if ($existing_nids) {
  $backup = [];
  foreach ($storage->loadMultiple($existing_nids) as $node) {
    $row = [
      'nid' => (int) $node->id(),
      'title' => $node->label(),
      'legacy_id' => $node->get('field_fm_legacy_id')->value,
      'generation' => $node->get('field_fm_generation')->value,
      'sex' => $node->get('field_fm_sex')->value,
      'spouse' => $node->get('field_fm_spouse')->value,
      'parent_nid' => $node->get('field_fm_parent')->isEmpty()
        ? NULL
        : (int) $node->get('field_fm_parent')->target_id,
      'translations' => [],
    ];
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      $t = $node->getTranslation($langcode);
      $row['translations'][$langcode] = [
        'title' => $t->label(),
        'spouse' => $t->get('field_fm_spouse')->value,
      ];
    }
    $backup[] = $row;
  }

  $backup_dir = __DIR__ . '/../data/backups';
  if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, TRUE);
  }
  $backup_file = $backup_dir . '/family-members-' . date('Ymd-His') . '.json';

  if ($dry_run) {
    print "Would back up " . count($backup) . " members to " . basename($backup_file) . "\n";
  }
  else {
    file_put_contents($backup_file, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    print 'Backed up ' . count($backup) . ' members to ' . basename($backup_file) . "\n";
  }
}

if ($dry_run) {
  print "\nWould delete " . count($existing_nids) . " nodes and create " . count($people) . ".\n";
  print "\nFirst 12 to be created:\n";
  foreach (array_slice($people, 0, 12) as $p) {
    print '  ' . str_repeat('  ', $p['generation'] - 1) . $p['title']
      . ($p['spouse'] ? ' + ' . $p['spouse'] : '')
      . '  (g' . $p['generation'] . ')' . "\n";
  }
  print "\nNothing written. Re-run without --dry to apply.\n";
  return;
}

// ---------------------------------------------------------------------------
// Remove the old register.
// ---------------------------------------------------------------------------
if ($existing_nids) {
  // Clear the parent references first so deletion cannot trip over them.
  foreach ($storage->loadMultiple($existing_nids) as $node) {
    if (!$node->get('field_fm_parent')->isEmpty()) {
      $node->set('field_fm_parent', NULL);
      $node->setNewRevision(FALSE);
      $node->save();
    }
  }

  foreach (array_chunk($existing_nids, 50) as $chunk) {
    $storage->delete($storage->loadMultiple($chunk));
  }
  print 'Deleted ' . count($existing_nids) . " old family member nodes.\n";
}

// ---------------------------------------------------------------------------
// Import the spreadsheet, shallowest generation first.
// ---------------------------------------------------------------------------
usort($people, fn($a, $b) => [$a['generation'], $a['idx']] <=> [$b['generation'], $b['idx']]);

$nid_by_idx = [];
$created = 0;

foreach ($people as $person) {
  $parent_nid = NULL;
  if ($person['parent_idx'] !== NULL && isset($nid_by_idx[$person['parent_idx']])) {
    $parent_nid = $nid_by_idx[$person['parent_idx']];
  }

  $node = Node::create([
    'type' => 'family_member',
    'langcode' => 'mr',
    'uid' => 1,
    'status' => 1,
    'title' => mb_substr($person['title'], 0, 255),
    // Traceable back to the cell it came from.
    'field_fm_legacy_id' => 'xl-r' . $person['row'] . '-c' . $person['col'],
    'field_fm_generation' => $person['generation'],
    'field_fm_sex' => $person['sex'],
    'field_fm_spouse' => $person['spouse'],
    'field_fm_parent' => $parent_nid,
  ]);
  $node->save();

  $nid_by_idx[$person['idx']] = (int) $node->id();
  $created++;
}

print "Created $created family member nodes.\n";

// ---------------------------------------------------------------------------
// Summary.
// ---------------------------------------------------------------------------
$by_generation = [];
$orphans = 0;
foreach ($people as $person) {
  $by_generation[$person['generation']] = ($by_generation[$person['generation']] ?? 0) + 1;
  if ($person['parent_idx'] === NULL) {
    $orphans++;
  }
}
ksort($by_generation);

print "\nPeople per generation:\n";
foreach ($by_generation as $generation => $count) {
  print "  generation $generation: $count\n";
}
print "Roots (no parent): $orphans\n";

\Drupal::service('pandurang.family_tree')->invalidate();
print "\nFamily tree cache cleared.\n";
print "Next: run scripts/16-transliterate-member-names.php to give the new names English translations.\n";
