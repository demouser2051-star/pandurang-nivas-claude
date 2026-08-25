<?php

/**
 * @file
 * Gives every family member an English translation of their name.
 *
 * The register holds only Devanagari, so English pages fell back to Marathi.
 * This writes a Latin-script translation of the name and the spouse name onto
 * the English translation of each family_member node.
 *
 * The result is a starting point, not an authority: the family can correct any
 * spelling from the admin UI and re-running will not overwrite a name that has
 * been edited by hand (see $preserve_edited).
 *
 * Dry run:  drush php:script scripts/16-transliterate-member-names.php -- --dry
 * Apply:    drush php:script scripts/16-transliterate-member-names.php
 * Redo:     drush php:script scripts/16-transliterate-member-names.php -- --force
 */

$dry_run = in_array('--dry', $extra ?? [], TRUE);

// Names that no longer match what the rules produce are assumed to be
// hand-corrected and left alone. Pass --force after changing the rules
// themselves, when the stored names are stale machine output rather than
// somebody's edit.
$force = in_array('--force', $extra ?? [], TRUE);

/**
 * Spellings the family has confirmed, which win over the automatic rules.
 *
 * Matched one word at a time, so an entry here applies wherever that word
 * appears in a name. Transliteration can only guess at conventional English
 * spellings - चव्हाण comes out as "Chavhan" by rule, but the family writes it
 * Chavan - so corrections belong here, where they survive a re-run.
 */
const PN_KNOWN_SPELLINGS = [
  // The family surname.
  'चव्हाण' => 'Chavan',

  // Names where the rules leave an internal vowel a Marathi speaker drops.
  'ज्ञानदेव' => 'Dnyandev',
  'दिनकर' => 'Dinkar',
];

/**
 * Turns one Devanagari name into its conventional Latin spelling.
 *
 * ICU's Any-Latin produces IAST (ś, ṣ, ṇ, ā). Two further steps make it read
 * like a Marathi name written in English: the diacritics become the digraphs
 * people actually use, and the inherent final 'a' is dropped.
 */
function pn_transliterate_name(string $devanagari): string {
  static $iast = NULL;
  if ($iast === NULL) {
    $iast = Transliterator::create('Any-Latin');
  }

  $devanagari = trim($devanagari);
  if ($devanagari === '') {
    return '';
  }

  // Anything already in Latin script is left alone.
  if (!preg_match('/\p{Devanagari}/u', $devanagari)) {
    return $devanagari;
  }

  // A handful of entries annotate a name rather than spell one - the register
  // records two wives as "अनुसया (थोरली + धाकटी)". Those words are translated,
  // not transliterated, or they come out as nonsense like "thorali".
  $descriptors = [
    'थोरली' => 'elder', 'थोरला' => 'elder',
    'धाकटी' => 'younger', 'धाकटा' => 'younger',
    'बहीण' => 'sister', 'भाऊ' => 'brother',
  ];
  $devanagari = strtr($devanagari, $descriptors);

  $latin = $iast->transliterate($devanagari);

  // ICU emits its diacritics as combining marks rather than precomposed
  // characters: vocalic ऋ comes back as "r" + U+0325 (ring below), and ङ/ं as
  // "n" + U+0304 (macron). These have to be caught before the single-letter
  // rules below, which only ever see precomposed forms.
  $combining = [
    'r' . "\u{0325}" => 'ru', 'R' . "\u{0325}" => 'Ru',
    'l' . "\u{0325}" => 'l',  'L' . "\u{0325}" => 'L',
    'n' . "\u{0304}" => 'n',  'N' . "\u{0304}" => 'N',
    'm' . "\u{0304}" => 'n',  'M' . "\u{0304}" => 'N',
    's' . "\u{0301}" => 'sh', 'S' . "\u{0301}" => 'Sh',
    'n' . "\u{0323}" => 'n',  'N' . "\u{0323}" => 'N',
    's' . "\u{0323}" => 'sh', 'S' . "\u{0323}" => 'Sh',
    't' . "\u{0323}" => 't',  'T' . "\u{0323}" => 'T',
    'd' . "\u{0323}" => 'd',  'D' . "\u{0323}" => 'D',
    'h' . "\u{0323}" => 'h',  'H' . "\u{0323}" => 'H',
    'r' . "\u{0323}" => 'ru', 'R' . "\u{0323}" => 'Ru',
    'a' . "\u{0304}" => 'a',  'A' . "\u{0304}" => 'A',
    'i' . "\u{0304}" => 'i',  'I' . "\u{0304}" => 'I',
    'u' . "\u{0304}" => 'u',  'U' . "\u{0304}" => 'U',
    'e' . "\u{0304}" => 'e',  'E' . "\u{0304}" => 'E',
    'o' . "\u{0304}" => 'o',  'O' . "\u{0304}" => 'O',
  ];
  $latin = strtr($latin, $combining);

  // IAST to the digraphs Marathi names are normally written with. Order
  // matters: the compound rules have to run before the single letters.
  $map = [
    'jñ' => 'dny', 'Jñ' => 'Dny',
    'ṅg' => 'ng', 'ṅk' => 'nk', 'ñc' => 'nch', 'ñj' => 'nj',
    'ś' => 'sh', 'Ś' => 'Sh',
    'ṣ' => 'sh', 'Ṣ' => 'Sh',
    'ch' => 'chh',
    'c'  => 'ch', 'C' => 'Ch',
    'ṭ' => 't', 'Ṭ' => 'T',
    'ḍ' => 'd', 'Ḍ' => 'D',
    'ṇ' => 'n', 'Ṇ' => 'N',
    'ṃ' => 'n', 'Ṃ' => 'N',
    'ṁ' => 'n', 'Ṁ' => 'N',
    'ṅ' => 'n', 'Ṅ' => 'N',
    'ñ' => 'n', 'Ñ' => 'N',
    'ḥ' => 'h', 'Ḥ' => 'H',
    'ṛ' => 'ru', 'Ṛ' => 'Ru',
    'ṝ' => 'ru', 'ḷ' => 'l', 'ḹ' => 'l',
    'ā' => 'a', 'Ā' => 'A',
    'ī' => 'i', 'Ī' => 'I',
    'ū' => 'u', 'Ū' => 'U',
    'ē' => 'e', 'Ē' => 'E',
    'ō' => 'o', 'Ō' => 'O',
    "'" => '',
  ];
  $latin = strtr($latin, $map);

  // Safety net: drop any combining mark the tables above did not name, so a
  // stray diacritic can never reach a person's name.
  $latin = preg_replace('/\p{Mn}/u', '', $latin);

  // Drop the inherent final vowel: महादेव is Mahadev, not Mahadeva. This only
  // applies where the Devanagari word ends in a bare consonant; a word ending
  // in ा carries a real long vowel, so अर्चना stays Archana.
  $source_words = preg_split('/\s+/u', $devanagari);
  $latin_words = preg_split('/\s+/u', trim($latin));

  foreach ($latin_words as $i => $word) {
    $source = $source_words[$i] ?? '';
    if ($source === '') {
      continue;
    }

    // Devanagari consonants occupy U+0915..U+0939. Anything else in final
    // position is a vowel sign, a halant or an independent vowel - all kept.
    $code = mb_ord(mb_substr($source, -1), 'UTF-8');
    $bare_consonant = $code >= 0x0915 && $code <= 0x0939;

    // A final consonant that closes a conjunct keeps its vowel: the द्र of
    // रामचंद्र is "dra", so the name is Ramachandra, not Ramachandr.
    $preceded_by_halant = mb_ord(mb_substr($source, -2, 1), 'UTF-8') === 0x094D;

    if ($bare_consonant && !$preceded_by_halant
      && mb_strlen($word) > 3 && mb_substr($word, -1) === 'a') {
      $latin_words[$i] = mb_substr($word, 0, -1);
    }
  }

  // A confirmed spelling replaces whatever the rules produced for that word.
  foreach ($source_words as $i => $source) {
    if (isset(PN_KNOWN_SPELLINGS[$source])) {
      $latin_words[$i] = PN_KNOWN_SPELLINGS[$source];
    }
  }

  // Title case the first *letter* of each word, not the first character, so a
  // bracketed nickname such as "(दाजी)" comes out as "(Daji)".
  foreach ($latin_words as $i => $word) {
    $latin_words[$i] = preg_replace_callback(
      '/\p{L}/u',
      fn($m) => mb_strtoupper($m[0]),
      $word,
      1
    );
  }

  $result = implode(' ', array_filter($latin_words, fn($w) => $w !== ''));

  // Title casing capitalises the descriptors injected above; they read better
  // in lower case, as the annotations they are.
  return strtr($result, [
    'Elder' => 'elder',
    'Younger' => 'younger',
  ]);
}

// Entries the register carries as a description rather than a name. These are
// translated outright; transliterating them would give nonsense like "bahina".
$placeholders = [
  '(नाव नोंदवलेले नाही)' => '(name not recorded)',
  '*(बहीण)' => '*(sister)',
  '*(भाऊ)' => '*(brother)',
  'बहीण' => 'Sister',
  'भाऊ' => 'Brother',
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'family_member')
  ->sort('nid', 'ASC')
  ->execute();

print 'Family members: ' . count($nids) . ($dry_run ? "  (DRY RUN)\n" : "\n");
printf("%-28s %-26s %s\n", 'MARATHI', 'ENGLISH', 'SPOUSE');
print str_repeat('-', 90) . "\n";

$created = 0;
$updated = 0;
$skipped = 0;
$shown = 0;

foreach ($storage->loadMultiple($nids) as $node) {
  $marathi_name = $node->label();
  $english_name = $placeholders[$marathi_name] ?? pn_transliterate_name($marathi_name);

  $marathi_spouse = $node->get('field_fm_spouse')->value;
  $english_spouse = NULL;
  if ($marathi_spouse) {
    $english_spouse = $placeholders[$marathi_spouse] ?? pn_transliterate_name($marathi_spouse);
  }

  if ($shown < 260) {
    printf("%-28s %-26s %s\n", $marathi_name, $english_name, $english_spouse ?? '');
    $shown++;
  }

  if ($dry_run) {
    continue;
  }

  $had_translation = $node->hasTranslation('en');
  $translation = $had_translation
    ? $node->getTranslation('en')
    : $node->addTranslation('en');

  // Never clobber a name someone has corrected by hand. A translation counts
  // as hand-edited once its title differs from what this script would produce.
  if ($had_translation && !$force) {
    $current = $translation->label();
    if ($current !== '' && $current !== $english_name && $current !== $marathi_name) {
      $skipped++;
      continue;
    }
  }

  $translation->setTitle($english_name);
  if ($english_spouse !== NULL) {
    $translation->set('field_fm_spouse', $english_spouse);
  }
  $translation->setNewRevision(FALSE);
  $translation->save();

  $had_translation ? $updated++ : $created++;
}

if ($shown >= 260) {
  print "  … (" . (count($nids) - 260) . " more)\n";
}

if ($dry_run) {
  print "\nNothing written. Re-run without --dry to apply.\n";
}
else {
  print "\nEnglish translations: $created created, $updated updated, $skipped left alone (hand-edited).\n";
  \Drupal::service('pandurang.family_tree')->invalidate();
  print "Family tree cache cleared.\n";
}
