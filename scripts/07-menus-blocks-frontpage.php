<?php

/**
 * @file
 * Menu links, block placement and the front page setting.
 *
 * Run: drush php:script scripts/07-menus-blocks-frontpage.php
 */

use Drupal\block\Entity\Block;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\system\Entity\Menu;

$theme = 'pandurang_nivas';

/**
 * Creates a menu link once, keyed on its title and menu.
 */
function pn_menu_link(string $menu, string $title_mr, string $title_en, string $uri, int $weight): void {
  $existing = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content')
    ->loadByProperties(['menu_name' => $menu, 'title' => $title_mr]);

  if ($existing) {
    return;
  }

  $link = MenuLinkContent::create([
    'title' => $title_mr,
    'link' => ['uri' => $uri],
    'menu_name' => $menu,
    'weight' => $weight,
    'expanded' => FALSE,
    'langcode' => 'mr',
  ]);
  $link->save();

  $translation = $link->addTranslation('en', ['title' => $title_en] + $link->toArray());
  $translation->save();

  print "  link: $title_mr ($uri)\n";
}

// ---------------------------------------------------------------------------
// Footer menu.
// ---------------------------------------------------------------------------
if (!Menu::load('footer')) {
  Menu::create([
    'id' => 'footer',
    'label' => 'Footer',
    'description' => 'Quick links in the site footer.',
  ])->save();
}

// ---------------------------------------------------------------------------
// Find the About and Privacy pages by their Marathi titles.
// ---------------------------------------------------------------------------
$storage = \Drupal::entityTypeManager()->getStorage('node');

/**
 * Returns an internal URI for a node matched on its Marathi title.
 */
$node_uri = function (string $title) use ($storage): ?string {
  $matches = $storage->loadByProperties(['type' => 'page', 'title' => $title]);
  if (!$matches) {
    return NULL;
  }
  /** @var \Drupal\node\NodeInterface $node */
  $node = reset($matches);
  return 'entity:node/' . $node->id();
};

$about_uri = $node_uri('आमच्याबद्दल') ?? 'internal:/';
$privacy_uri = $node_uri('गोपनीयता धोरण') ?? 'internal:/';

// ---------------------------------------------------------------------------
// Main menu. Gallery and Events were added by their views already.
// ---------------------------------------------------------------------------
print "Main menu\n";
pn_menu_link('main', 'मुखपृष्ठ', 'Home', 'internal:/home', 0);
pn_menu_link('main', 'आमच्याबद्दल', 'About Us', $about_uri, 1);
pn_menu_link('main', 'कुटुंबवृक्ष', 'Family Tree', 'internal:/family-tree', 2);
pn_menu_link('main', 'संपर्क', 'Contact Us', 'internal:/contact', 5);

print "Footer menu\n";
pn_menu_link('footer', 'मुखपृष्ठ', 'Home', 'internal:/home', 0);
pn_menu_link('footer', 'आमच्याबद्दल', 'About Us', $about_uri, 1);
pn_menu_link('footer', 'कुटुंबवृक्ष', 'Family Tree', 'internal:/family-tree', 2);
pn_menu_link('footer', 'चित्रदालन', 'Gallery', 'internal:/gallery', 3);
pn_menu_link('footer', 'कार्यक्रम', 'Events', 'internal:/events', 4);
pn_menu_link('footer', 'गोपनीयता धोरण', 'Privacy Policy', $privacy_uri, 5);

// ---------------------------------------------------------------------------
// Blocks.
// ---------------------------------------------------------------------------
print "Blocks\n";

/**
 * Places a block, replacing any earlier placement with the same ID.
 */
function pn_block(string $id, string $plugin, string $region, int $weight, string $theme, array $settings = [], array $visibility = []): void {
  if ($block = Block::load($id)) {
    $block->delete();
  }

  Block::create([
    'id' => $id,
    'theme' => $theme,
    'region' => $region,
    'plugin' => $plugin,
    'weight' => $weight,
    'settings' => $settings + [
      'id' => $plugin,
      'label' => '',
      'label_display' => '0',
      'provider' => explode(':', $plugin)[0],
    ],
    'visibility' => $visibility,
  ])->save();

  print "  block: $id -> $region\n";
}

pn_block('pn_main_menu', 'system_menu_block:main', 'primary_menu', 0, $theme, [
  'level' => 1,
  'depth' => 2,
]);

pn_block('pn_language_switcher', 'language_block:language_interface', 'header_actions', 0, $theme);

pn_block('pn_footer_menu', 'system_menu_block:footer', 'footer_links', 0, $theme, [
  'level' => 1,
  'depth' => 1,
]);

pn_block('pn_page_title', 'page_title_block', 'highlighted', -10, $theme, [], [
  'request_path' => [
    'id' => 'request_path',
    'negate' => TRUE,
    'pages' => "/home\n<front>",
  ],
]);

pn_block('pn_messages', 'system_messages_block', 'highlighted', -5, $theme);
pn_block('pn_local_tasks', 'local_tasks_block', 'highlighted', -8, $theme);
pn_block('pn_content', 'system_main_block', 'content', 0, $theme);

// The admin theme keeps its own blocks; only the front end is rearranged here.
foreach (Block::loadMultiple() as $block) {
  if ($block->getTheme() === $theme && !str_starts_with($block->id(), 'pn_')) {
    $block->delete();
  }
}

// ---------------------------------------------------------------------------
// Front page.
// ---------------------------------------------------------------------------
\Drupal::configFactory()->getEditable('system.site')
  ->set('page.front', '/home')
  ->save();

print "Front page set to /home\n";

// Friendly aliases for the two standing pages, in both languages. An alias is
// stored per language, so a Marathi-only one leaves /en/about a 404.
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

$aliases = [
  'आमच्याबद्दल' => ['mr' => '/about', 'en' => '/about'],
  'गोपनीयता धोरण' => ['mr' => '/privacy', 'en' => '/privacy'],
];

foreach ($aliases as $title => $per_language) {
  $matches = $storage->loadByProperties(['type' => 'page', 'title' => $title]);
  if (!$matches) {
    continue;
  }
  $node = reset($matches);
  $path = '/node/' . $node->id();

  foreach ($per_language as $langcode => $alias) {
    $existing = $alias_storage->loadByProperties([
      'path' => $path,
      'langcode' => $langcode,
    ]);
    if ($existing) {
      continue;
    }

    $alias_storage->create([
      'path' => $path,
      'alias' => $alias,
      'langcode' => $langcode,
    ])->save();

    print "  alias [$langcode]: $path -> $alias\n";
  }
}

print "Done.\n";
