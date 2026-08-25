<?php
/**
 * Removes the leftover default Home link and tidies the site title.
 * Run: drush php:script scripts/09-cleanup.php
 */

// The standard profile ships a "Home" link that duplicates ours.
$links = \Drupal::entityTypeManager()->getStorage('menu_link_content')
  ->loadByProperties(['menu_name' => 'main']);

foreach ($links as $link) {
  print '  main menu: ' . $link->getTitle() . ' -> ' . $link->getUrlObject()->toString() . "\n";
}

// Core's Home link lives in the module-defined menu tree, not menu_link_content.
$config = \Drupal::configFactory()->getEditable('system.menu.main');
print "\nMenu link overrides:\n";
$overrides = \Drupal::state()->get('menu_link_overrides') ?: [];
foreach ($overrides as $id => $override) {
  print "  $id: " . json_encode($override) . "\n";
}

// Hide the core-provided front page link so ours is the only one.
$menu_link_manager = \Drupal::service('plugin.manager.menu.link');
foreach ($menu_link_manager->getDefinitions() as $id => $definition) {
  if (($definition['menu_name'] ?? '') === 'main' && ($definition['route_name'] ?? '') === '<front>') {
    $menu_link_manager->updateDefinition($id, ['enabled' => FALSE]);
    print "  disabled core link: $id\n";
  }
}

print "Done.\n";
