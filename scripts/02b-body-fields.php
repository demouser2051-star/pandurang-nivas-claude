<?php
/**
 * Adds the standard body field to the content types that need prose.
 * Run: drush php:script scripts/02b-body-fields.php
 */
use Drupal\node\Entity\NodeType;

$display_repo = \Drupal::service('entity_display.repository');

foreach (['event' => 'Description', 'album' => 'Description', 'notification' => 'Message'] as $type => $label) {
  $node_type = NodeType::load($type);
  if (!$node_type) {
    continue;
  }
  $field = node_add_body_field($node_type, $label);
  $field->setTranslatable(TRUE)->save();

  $display_repo->getFormDisplay('node', $type, 'default')
    ->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 0])
    ->save();
  $display_repo->getViewDisplay('node', $type, 'default')
    ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 10])
    ->save();

  print "  body field added to node.$type\n";
}
print "Done.\n";
