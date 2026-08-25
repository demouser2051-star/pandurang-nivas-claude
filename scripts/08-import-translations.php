<?php
/**
 * Imports the Marathi interface translations shipped with the project.
 * Run: drush php:script scripts/08-import-translations.php
 */
$file = __DIR__ . '/../data/pandurang.mr.po';
if (!is_file($file)) {
  print "Missing $file\n";
  return;
}

\Drupal::moduleHandler()->loadInclude('locale', 'translation.inc');
\Drupal::moduleHandler()->loadInclude('locale', 'bulk.inc');

$source = new \Drupal\locale\Gettext();
$options = [
  'langcode' => 'mr',
  'overwrite_options' => ['not_customized' => TRUE, 'customized' => TRUE],
  'customized' => LOCALE_CUSTOMIZED,
];

$po = new \stdClass();
$po->uri = $file;
$po->langcode = 'mr';

$report = \Drupal\locale\Gettext::fileToDatabase($po, $options);

print "Marathi strings imported: {$report['additions']} added, {$report['updates']} updated, {$report['skips']} skipped.\n";

// Refresh the JS translation files and clear the string cache.
_locale_refresh_translations(['mr']);
\Drupal::service('cache.default')->deleteAll();
print "Translation caches refreshed.\n";
