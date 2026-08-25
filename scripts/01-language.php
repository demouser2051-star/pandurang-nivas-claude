<?php
/**
 * Configure Marathi as default language with /mr and /en URL prefixes.
 * Run: drush php:script scripts/01-language.php
 */

$cf = \Drupal::configFactory();

// Marathi becomes the site default language.
$cf->getEditable('system.site')->set('default_langcode', 'mr')->save();

// Give both languages an explicit URL prefix so neither is "bare".
$cf->getEditable('language.negotiation')
  ->set('url.source', 'path_prefix')
  ->set('url.prefixes', ['en' => 'en', 'mr' => 'mr'])
  ->save();

// Order Marathi first in the language switcher.
$cf->getEditable('language.entity.mr')->set('weight', 0)->save();
$cf->getEditable('language.entity.en')->set('weight', 1)->save();

// URL negotiation wins for interface language, then user preference.
$cf->getEditable('language.types')
  ->set('negotiation.language_interface.enabled', [
    'language-url' => 0,
    'language-user' => 1,
    'language-selected' => 2,
  ])
  ->set('configurable', ['language_interface', 'language_content'])
  ->save();

\Drupal::service('kernel')->invalidateContainer();
print "Default language set to Marathi (mr); prefixes /mr and /en active.\n";
