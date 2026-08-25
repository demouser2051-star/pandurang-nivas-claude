<?php

/**
 * @file
 * Rebuilds the footer "Quick Links" menu to match the original site: six
 * links, read down the first column then the second.
 *
 *   Home         Photo Gallery
 *   About Us     Events
 *   Family Tree  Contact Us
 *
 * Privacy Policy moves to the copyright line, where the static site had it.
 *
 * Run: drush php:script scripts/14-footer-menu.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$node_storage = \Drupal::entityTypeManager()->getStorage('node');

/**
 * Returns an internal URI for a page node matched on its Marathi title.
 */
$node_uri = function (string $title) use ($node_storage): ?string {
  $matches = $node_storage->loadByProperties(['type' => 'page', 'title' => $title]);
  if (!$matches) {
    return NULL;
  }
  return 'entity:node/' . reset($matches)->id();
};

// Start from a clean footer menu so re-running cannot leave strays behind.
foreach ($storage->loadByProperties(['menu_name' => 'footer']) as $link) {
  $link->delete();
}

// Core's contact module puts its own link in this menu; ours replaces it.
$menu_link_manager = \Drupal::service('plugin.manager.menu.link');
foreach ($menu_link_manager->getDefinitions() as $id => $definition) {
  if (($definition['menu_name'] ?? '') === 'footer') {
    $menu_link_manager->updateDefinition($id, ['enabled' => FALSE]);
    print "  disabled core footer link: $id\n";
  }
}

// Column-major order: the CSS fills the first column before the second, so
// weights 0-2 land on the left and 3-5 on the right.
$links = [
  ['mr' => 'मुखपृष्ठ', 'en' => 'Home', 'uri' => 'internal:/home'],
  ['mr' => 'आमच्याबद्दल', 'en' => 'About Us', 'uri' => $node_uri('आमच्याबद्दल') ?? 'internal:/about'],
  ['mr' => 'कुटुंबवृक्ष', 'en' => 'Family Tree', 'uri' => 'internal:/family-tree'],
  ['mr' => 'चित्रदालन', 'en' => 'Photo Gallery', 'uri' => 'internal:/gallery'],
  ['mr' => 'कार्यक्रम', 'en' => 'Events', 'uri' => 'internal:/events'],
  ['mr' => 'संपर्क', 'en' => 'Contact Us', 'uri' => 'internal:/contact'],
];

foreach ($links as $weight => $link) {
  $entity = MenuLinkContent::create([
    'title' => $link['mr'],
    'link' => ['uri' => $link['uri']],
    'menu_name' => 'footer',
    'weight' => $weight,
    'expanded' => FALSE,
    'langcode' => 'mr',
  ]);
  $entity->save();

  $entity->addTranslation('en', ['title' => $link['en']] + $entity->toArray())->save();

  print '  ' . $link['mr'] . ' / ' . $link['en'] . "\n";
}

\Drupal::service('plugin.manager.menu.link')->rebuild();

print "Footer menu rebuilt with " . count($links) . " links.\n";
