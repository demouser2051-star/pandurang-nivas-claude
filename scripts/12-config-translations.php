<?php

/**
 * @file
 * English overrides for the configuration that carries visible Marathi text:
 * the two view titles and the menu links those views create.
 *
 * Marathi is the site default, so the base config stays Marathi and English
 * lives in the language.en.* override collection.
 *
 * Run: drush php:script scripts/12-config-translations.php
 */

$language_manager = \Drupal::languageManager();

/**
 * Writes English values over one config object.
 */
function pn_config_en(string $name, array $values): void {
  $override = \Drupal::languageManager()
    ->getLanguageConfigOverride('en', $name);

  foreach ($values as $key => $value) {
    $override->set($key, $value);
  }
  $override->save();

  print "  en override: $name\n";
}

// View page titles and their menu entries.
pn_config_en('views.view.pn_events', [
  'display.default.display_options.title' => 'Events',
  'display.page_1.display_options.menu.title' => 'Events',
]);

pn_config_en('views.view.pn_gallery', [
  'display.default.display_options.title' => 'Gallery',
  'display.page_1.display_options.menu.title' => 'Gallery',
]);

// Site name and slogan.
pn_config_en('system.site', [
  'name' => 'Pandurang Nivas',
  'slogan' => 'A digital platform bringing the whole family together',
]);

// Content type labels, so the admin UI reads sensibly in English too.
$type_labels = [
  'family_member' => 'Family Member',
  'event' => 'Event',
  'album' => 'Album',
  'gallery_item' => 'Gallery Item',
  'notification' => 'Notification',
];

foreach ($type_labels as $type => $label) {
  pn_config_en('node.type.' . $type, ['name' => $label]);
}

// Marathi labels for the same content types, since the base config was
// created in English by the setup script.
foreach ([
  'family_member' => 'कुटुंब सदस्य',
  'event' => 'कार्यक्रम',
  'album' => 'अल्बम',
  'gallery_item' => 'चित्र',
  'notification' => 'सूचना',
] as $type => $label) {
  $override = $language_manager->getLanguageConfigOverride('mr', 'node.type.' . $type);
  $override->set('name', $label)->save();
}

print "Done.\n";
