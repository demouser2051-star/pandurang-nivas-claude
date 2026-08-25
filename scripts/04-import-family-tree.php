<?php

/**
 * @file
 * Imports familyTreeData.json into family_member nodes.
 *
 * Runs generation by generation so a parent always exists before the children
 * that point at it. Re-running updates the existing nodes rather than
 * duplicating them, matched on field_fm_legacy_id.
 *
 * Run: drush php:script scripts/04-import-family-tree.php -- <path-to-json>
 */

use Drupal\node\Entity\Node;

$path = $extra[0] ?? (__DIR__ . '/../data/familyTreeData.json');

if (!is_file($path)) {
  print "Source not found: $path\n";
  return;
}

$data = json_decode(file_get_contents($path), TRUE);
if (!$data) {
  print "Could not parse $path\n";
  return;
}

$storage = \Drupal::entityTypeManager()->getStorage('node');

/**
 * Finds an existing member node by its legacy tree ID.
 */
$find = function (string $legacy_id) use ($storage): ?Node {
  $matches = $storage->loadByProperties([
    'type' => 'family_member',
    'field_fm_legacy_id' => $legacy_id,
  ]);
  return $matches ? reset($matches) : NULL;
};

// Flatten the file into one record per person, tagging each with its
// generation and the legacy ID of its parent.
$people = [];

// Generation 1 is the root of the whole register.
foreach ($data['gen1'] as $member) {
  $people[$member['id']] = $member + ['generation' => 1, 'parent_legacy' => NULL];
}
$root_id = $data['gen1'][0]['id'] ?? NULL;

// Generation 2 is a flat list; every one of them is a child of the root.
foreach ($data['gen2'] as $member) {
  $people[$member['id']] = $member + ['generation' => 2, 'parent_legacy' => $root_id];
}

// Generations 3 and deeper are grouped under "<parent-id>-children" keys.
foreach (['gen3' => 3, 'gen4' => 4, 'gen5' => 5, 'gen6' => 6] as $key => $generation) {
  foreach ($data[$key] ?? [] as $group_key => $group) {
    $parent_legacy = preg_replace('/-children$/', '', $group_key);
    foreach ($group as $member) {
      $people[$member['id']] = $member + [
        'generation' => $generation,
        'parent_legacy' => $parent_legacy,
      ];
    }
  }
}

print 'Flattened ' . count($people) . " people from the register.\n";

// Any parent that was never recorded leaves its children stranded; note it on
// the node so whoever curates the tree can fill the gap in.
$orphans = [];
foreach ($people as $id => $person) {
  if ($person['parent_legacy'] !== NULL && !isset($people[$person['parent_legacy']])) {
    $orphans[$id] = $person['parent_legacy'];
  }
}

// ---------------------------------------------------------------------------
// Create or update, shallowest generation first.
// ---------------------------------------------------------------------------
uasort($people, fn($a, $b) => $a['generation'] <=> $b['generation']);

$nid_by_legacy = [];
$unnamed_people = [];
$created = 0;
$updated = 0;

foreach ($people as $legacy_id => $person) {
  $parent_nid = NULL;
  if ($person['parent_legacy'] !== NULL && isset($nid_by_legacy[$person['parent_legacy']])) {
    $parent_nid = $nid_by_legacy[$person['parent_legacy']];
  }

  // A few people hold a place in the register without a recorded name. Keep
  // their position in the tree rather than dropping them.
  $name = trim((string) ($person['name'] ?? ''));
  $unnamed = $name === '';
  if ($unnamed) {
    $name = '(नाव नोंदवलेले नाही)';
    $unnamed_people[$legacy_id] = $person['generation'];
  }

  $values = [
    'title' => mb_substr($name, 0, 255),
    'field_fm_legacy_id' => $legacy_id,
    'field_fm_generation' => $person['generation'],
    'field_fm_sex' => $person['sex'] ?? 'male',
    'field_fm_spouse' => $person['spouse'] ?? NULL,
    'field_fm_parent' => $parent_nid,
  ];

  $notes = [];
  if (isset($orphans[$legacy_id])) {
    $notes[] = 'Imported without a parent: the register refers to "'
      . $orphans[$legacy_id] . '", who has no entry of their own. '
      . 'Add that person and set them as the parent here to reconnect this branch.';
  }
  if ($unnamed) {
    $notes[] = 'This person appears in the register with no name recorded. '
      . 'Replace the placeholder title once the name is known.';
  }
  if ($notes) {
    $values['field_fm_notes'] = [
      'value' => implode(' ', $notes),
      'format' => 'basic_html',
    ];
  }

  $node = $find($legacy_id);

  if ($node) {
    foreach ($values as $field => $value) {
      $node->set($field, $value);
    }
    $node->setNewRevision(FALSE);
    $node->save();
    $updated++;
  }
  else {
    $node = Node::create($values + [
      'type' => 'family_member',
      'langcode' => 'mr',
      'uid' => 1,
      'status' => 1,
    ]);
    $node->save();
    $created++;
  }

  $nid_by_legacy[$legacy_id] = (int) $node->id();
}

print "Created $created, updated $updated family member nodes.\n";

if ($orphans) {
  print "\nBranches with a missing parent in the source register:\n";
  foreach ($orphans as $child => $missing_parent) {
    $label = trim((string) ($people[$child]['name'] ?? '')) ?: '(unnamed)';
    print '  ' . $label . " (id $child) refers to missing parent $missing_parent\n";
  }
}

if ($unnamed_people) {
  print "\nPeople held in the register with no name recorded:\n";
  foreach ($unnamed_people as $id => $generation) {
    print "  id $id (generation $generation) - imported with a placeholder title\n";
  }
}

// Members whose "children" pointer leads nowhere - harmless, but worth naming.
$dangling = [];
foreach ($people as $legacy_id => $person) {
  if (!empty($person['children'])) {
    $expected = $person['children'];
    $has_children = FALSE;
    foreach ($people as $candidate) {
      if ($candidate['parent_legacy'] . '-children' === $expected) {
        $has_children = TRUE;
        break;
      }
    }
    if (!$has_children) {
      $dangling[$legacy_id] = $person['name'];
    }
  }
}

if ($dangling) {
  print "\nMembers recorded as having children, but with no children listed:\n";
  foreach ($dangling as $id => $name) {
    print "  $name (id $id)\n";
  }
}

\Drupal::service('pandurang.family_tree')->invalidate();
print "\nFamily tree cache cleared.\n";
