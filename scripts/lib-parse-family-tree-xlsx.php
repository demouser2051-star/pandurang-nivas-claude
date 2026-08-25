<?php

/**
 * Parses the CHAVAN FAMILY TREE workbook into a flat person list.
 *
 * The sheet is a drawn tree: a cell's column is its generation and its parent
 * is the nearest name up and to the left. Each cell reads
 * "GIVEN FATHER SURNAME + SPOUSE", and a married daughter carries her married
 * surname in brackets: "रजनी (मोरे) व्यंकटेश चव्हाण".
 */

/**
 * Converts an A1-style column reference to a zero-based index.
 */
function col_index(string $ref): int {
  preg_match('/^([A-Z]+)/', $ref, $m);
  $n = 0;
  foreach (str_split($m[1] ?? 'A') as $ch) {
    $n = $n * 26 + (ord($ch) - 64);
  }
  return $n - 1;
}

$path = $argv[1];
$zip = new ZipArchive();
$zip->open($path);

$shared = [];
if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== FALSE) {
  foreach ((new SimpleXMLElement($xml))->si as $si) {
    $text = isset($si->t) ? (string) $si->t : '';
    foreach ($si->r as $r) {
      $text .= (string) $r->t;
    }
    $shared[] = $text;
  }
}

$sheet = new SimpleXMLElement($zip->getFromName('xl/worksheets/sheet1.xml'));

// Collect every non-empty cell as (row, col, text).
$cells = [];
foreach ($sheet->sheetData->row as $row) {
  $r = (int) $row['r'];
  foreach ($row->c as $c) {
    $type = (string) $c['t'];
    $v = isset($c->v) ? (string) $c->v : '';
    if ($type === 's') {
      $v = $shared[(int) $v] ?? '';
    }
    elseif ($type === 'inlineStr') {
      $v = (string) $c->is->t;
    }
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    if ($v !== '') {
      $cells[] = ['row' => $r, 'col' => col_index((string) $c['r']), 'text' => $v];
    }
  }
}

// Drop the title row and the branch-number column: a name always contains
// Devanagari, a branch marker is a bare integer.
$people = array_values(array_filter($cells, function ($c) {
  if (!preg_match('/\p{Devanagari}/u', $c['text'])) {
    return FALSE;
  }
  // The banner row.
  return stripos($c['text'], 'FAMILY TREE') === FALSE;
}));

usort($people, fn($a, $b) => [$a['row'], $a['col']] <=> [$b['row'], $b['col']]);

echo "Name cells found: " . count($people) . "\n";

$cols = [];
foreach ($people as $p) {
  $cols[$p['col']] = ($cols[$p['col']] ?? 0) + 1;
}
ksort($cols);
echo "Cells per column: ";
foreach ($cols as $c => $n) {
  echo "$c=$n ";
}
echo "\n\n";

// Walk in reading order, keeping the last name seen at each column. A person's
// parent is the most recent name in any shallower column.
$lastAtCol = [];
$records = [];

foreach ($people as $i => $p) {
  $parent = NULL;
  for ($c = $p['col'] - 1; $c >= 0; $c--) {
    if (isset($lastAtCol[$c])) {
      $parent = $lastAtCol[$c];
      break;
    }
  }

  // Split "NAME + SPOUSE"; a few carry more than one wife, separated by +.
  $parts = array_map('trim', explode('+', $p['text']));
  $full = array_shift($parts);
  $spouse = $parts ? implode(' + ', $parts) : NULL;

  // A bracketed surname is the married name of a daughter.
  $married = NULL;
  if (preg_match('/\(([^)]*)\)/u', $full, $m)) {
    $married = trim($m[1]);
    $full = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $full));
  }

  $words = preg_split('/\s+/u', $full);
  $given = $words[0] ?? $full;

  // What the family actually wrote, minus only the spouse. Kept verbatim so
  // a married daughter's bracketed surname is not lost.
  $verbatim = trim(explode('+', $p['text'])[0]);

  // Sex is only recorded implicitly: a person listed with a wife is male, a
  // bracketed married surname marks a daughter. Anything else stays unset
  // rather than guessed.
  $sex = NULL;
  if ($married !== NULL) {
    $sex = 'female';
  }
  elseif ($spouse !== NULL) {
    $sex = 'male';
  }

  $records[$i] = [
    'idx' => $i,
    'row' => $p['row'],
    'col' => $p['col'],
    'title' => $verbatim,
    'sex' => $sex,
    'full' => $full,
    'given' => $given,
    'married_surname' => $married,
    'spouse' => $spouse,
    'parent_idx' => $parent,
    'raw' => $p['text'],
  ];

  $lastAtCol[$p['col']] = $i;
  // A deeper column cannot still point at a stale sibling of this person.
  foreach (array_keys($lastAtCol) as $c) {
    if ($c > $p['col']) {
      unset($lastAtCol[$c]);
    }
  }
}

// Depth per person, from the parent chain.
foreach ($records as $i => &$rec) {
  $depth = 1;
  $cur = $rec['parent_idx'];
  $guard = 0;
  while ($cur !== NULL && $guard++ < 30) {
    $depth++;
    $cur = $records[$cur]['parent_idx'];
  }
  $rec['generation'] = $depth;
}
unset($rec);

$roots = array_filter($records, fn($r) => $r['parent_idx'] === NULL);
echo "Roots: " . count($roots) . "\n";
foreach ($roots as $r) {
  echo "  row {$r['row']} col {$r['col']}: {$r['raw']}\n";
}

$byGen = [];
foreach ($records as $r) {
  $byGen[$r['generation']] = ($byGen[$r['generation']] ?? 0) + 1;
}
ksort($byGen);
echo "\nPeople per generation:\n";
foreach ($byGen as $g => $n) {
  echo "  gen $g: $n\n";
}

echo "\nWith spouse: " . count(array_filter($records, fn($r) => $r['spouse'] !== NULL)) . "\n";
echo "With married surname: " . count(array_filter($records, fn($r) => $r['married_surname'] !== NULL)) . "\n";

echo "\nFirst 30 in tree order:\n";
foreach (array_slice($records, 0, 30) as $r) {
  echo str_repeat('  ', $r['generation'] - 1)
    . $r['full']
    . ($r['married_surname'] ? " [{$r['married_surname']}]" : '')
    . ($r['spouse'] ? " + {$r['spouse']}" : '')
    . "   (g{$r['generation']} r{$r['row']} c{$r['col']})\n";
}

file_put_contents(
  __DIR__ . '/parsed-tree.json',
  json_encode(array_values($records), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);
echo "\nWritten: parsed-tree.json (" . count($records) . " people)\n";
