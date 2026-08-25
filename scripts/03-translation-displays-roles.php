<?php

/**
 * @file
 * Turns on content translation, wires the new fields into the form and view
 * displays, and creates the family roles.
 *
 * Run: drush php:script scripts/03-translation-displays-roles.php
 */

use Drupal\user\Entity\Role;

$bundles = ['family_member', 'event', 'album', 'gallery_item', 'notification', 'page', 'article'];

// ---------------------------------------------------------------------------
// Content translation.
// ---------------------------------------------------------------------------
print "Enabling content translation\n";
$content_translation = \Drupal::service('content_translation.manager');
$cf = \Drupal::configFactory();

foreach ($bundles as $bundle) {
  $content_translation->setEnabled('node', $bundle, TRUE);

  // Default new nodes to Marathi and expose the language selector.
  $cf->getEditable('language.content_settings.node.' . $bundle)
    ->set('target_entity_type_id', 'node')
    ->set('target_bundle', $bundle)
    ->set('default_langcode', 'mr')
    ->set('language_alterable', TRUE)
    ->save();
  print "  node.$bundle translatable\n";
}

// Users are translatable too, so profile labels follow the interface language.
$content_translation->setEnabled('user', 'user', TRUE);

// ---------------------------------------------------------------------------
// Form and view displays.
// ---------------------------------------------------------------------------
print "Configuring displays\n";

$widgets = [
  'field_fm_legacy_id'     => ['type' => 'string_textfield', 'weight' => 1],
  'field_fm_generation'    => ['type' => 'number', 'weight' => 2],
  'field_fm_sex'           => ['type' => 'options_select', 'weight' => 3],
  'field_fm_spouse'        => ['type' => 'string_textfield', 'weight' => 4],
  'field_fm_parent'        => ['type' => 'entity_reference_autocomplete', 'weight' => 5],
  'field_fm_photo'         => ['type' => 'image_image', 'weight' => 6],
  'field_fm_notes'         => ['type' => 'text_textarea', 'weight' => 7],
  'field_event_start'      => ['type' => 'datetime_default', 'weight' => 1],
  'field_event_end'        => ['type' => 'datetime_default', 'weight' => 2],
  'field_event_location'   => ['type' => 'string_textfield', 'weight' => 3],
  'field_event_time'       => ['type' => 'string_textfield', 'weight' => 4],
  'field_event_type'       => ['type' => 'options_select', 'weight' => 5],
  'field_event_image'      => ['type' => 'image_image', 'weight' => 6],
  'field_event_rsvp'       => ['type' => 'boolean_checkbox', 'weight' => 7],
  'field_album_cover'      => ['type' => 'image_image', 'weight' => 1],
  'field_gi_type'          => ['type' => 'options_select', 'weight' => 1],
  'field_gi_image'         => ['type' => 'image_image', 'weight' => 2],
  'field_gi_video'         => ['type' => 'file_generic', 'weight' => 3],
  'field_gi_album'         => ['type' => 'entity_reference_autocomplete', 'weight' => 4],
  'field_gi_date'          => ['type' => 'datetime_default', 'weight' => 5],
  'field_gi_caption'       => ['type' => 'string_textfield', 'weight' => 6],
];

$formatters = [
  'field_fm_generation'    => ['type' => 'number_integer', 'weight' => 2],
  'field_fm_sex'           => ['type' => 'list_default', 'weight' => 3],
  'field_fm_spouse'        => ['type' => 'string', 'weight' => 4],
  'field_fm_parent'        => ['type' => 'entity_reference_label', 'weight' => 5],
  'field_fm_photo'         => ['type' => 'image', 'weight' => 1, 'settings' => ['image_style' => 'medium']],
  'field_fm_notes'         => ['type' => 'text_default', 'weight' => 7],
  'field_event_start'      => ['type' => 'datetime_default', 'weight' => 1],
  'field_event_end'        => ['type' => 'datetime_default', 'weight' => 2],
  'field_event_location'   => ['type' => 'string', 'weight' => 3],
  'field_event_time'       => ['type' => 'string', 'weight' => 4],
  'field_event_type'       => ['type' => 'list_default', 'weight' => 5],
  'field_event_image'      => ['type' => 'image', 'weight' => 0, 'settings' => ['image_style' => 'large']],
  'field_album_cover'      => ['type' => 'image', 'weight' => 0, 'settings' => ['image_style' => 'large']],
  'field_gi_image'         => ['type' => 'image', 'weight' => 0, 'settings' => ['image_style' => 'large']],
  'field_gi_video'         => ['type' => 'file_default', 'weight' => 1],
  'field_gi_album'         => ['type' => 'entity_reference_label', 'weight' => 2],
  'field_gi_caption'       => ['type' => 'string', 'weight' => 3],
];

$display_repo = \Drupal::service('entity_display.repository');
$field_manager = \Drupal::service('entity_field.manager');

foreach (['family_member', 'event', 'album', 'gallery_item', 'notification'] as $bundle) {
  $definitions = $field_manager->getFieldDefinitions('node', $bundle);
  $form_display = $display_repo->getFormDisplay('node', $bundle, 'default');
  $view_display = $display_repo->getViewDisplay('node', $bundle, 'default');

  foreach ($definitions as $field_name => $definition) {
    if (!str_starts_with($field_name, 'field_')) {
      continue;
    }
    if (isset($widgets[$field_name])) {
      $form_display->setComponent($field_name, $widgets[$field_name]);
    }
    if (isset($formatters[$field_name])) {
      $view_display->setComponent($field_name, $formatters[$field_name] + ['label' => 'inline']);
    }
  }

  // The legacy tree ID is an import artefact, not editorial content.
  $view_display->removeComponent('field_fm_legacy_id');

  $form_display->save();
  $view_display->save();
  print "  displays: node.$bundle\n";
}

// ---------------------------------------------------------------------------
// Roles.
// ---------------------------------------------------------------------------
print "Creating roles\n";

/**
 * Creates or updates a role with the given permissions.
 */
function pn_role(string $id, string $label, int $weight, array $permissions): void {
  $role = Role::load($id);
  if (!$role) {
    $role = Role::create(['id' => $id, 'label' => $label, 'weight' => $weight]);
    print "  role: $id\n";
  }
  foreach ($permissions as $permission) {
    $role->grantPermission($permission);
  }
  $role->save();
}

// A verified relative: can see the private parts of the site and RSVP.
pn_role('family_member', 'Family Member', 3, [
  'access content',
  'view pn private content',
  'rsvp to events',
  'view media',
  'access user profiles',
  'search content',
]);

// Trusted relative who curates content.
pn_role('family_admin', 'Family Admin', 4, [
  'access content',
  'view pn private content',
  'rsvp to events',
  'view media',
  'access user profiles',
  'search content',
  'access content overview',
  'access administration pages',
  'access toolbar',
  'view own unpublished content',
  'view all revisions',
  'create media',
  'update any media',
  'translate any entity',
  'create content translations',
  'update content translations',
  'delete content translations',
  'view pn rsvp report',
]);

foreach (['family_member', 'event', 'album', 'gallery_item', 'notification'] as $bundle) {
  $admin = Role::load('family_admin');
  foreach (['create', 'delete any', 'edit any'] as $op) {
    $admin->grantPermission("$op $bundle content");
  }
  $admin->grantPermission("view own unpublished content");
  $admin->save();
}

// Anonymous and authenticated keep plain read access to public content only.
$anonymous = Role::load('anonymous');
$anonymous->grantPermission('access content');
$anonymous->save();

print "Done.\n";
