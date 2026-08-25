<?php
/**
 * Marathi labels for the fields whose names show on the front end.
 * Run: drush php:script scripts/13-field-label-translations.php
 */
$labels = [
  'node.family_member.field_fm_generation' => 'पिढी',
  'node.family_member.field_fm_sex'        => 'लिंग',
  'node.family_member.field_fm_spouse'     => 'जोडीदार',
  'node.family_member.field_fm_parent'     => 'पालक',
  'node.family_member.field_fm_photo'      => 'छायाचित्र',
  'node.family_member.field_fm_notes'      => 'टीप',
  'node.event.field_event_start'           => 'सुरुवात दिनांक',
  'node.event.field_event_end'             => 'शेवट दिनांक',
  'node.event.field_event_location'        => 'ठिकाण',
  'node.event.field_event_time'            => 'वेळ',
  'node.event.field_event_type'            => 'प्रकार',
  'node.event.field_event_image'           => 'छायाचित्र',
  'node.gallery_item.field_gi_type'        => 'माध्यम प्रकार',
  'node.gallery_item.field_gi_image'       => 'छायाचित्र',
  'node.gallery_item.field_gi_album'       => 'अल्बम',
  'node.gallery_item.field_gi_date'        => 'दिनांक',
  'node.gallery_item.field_gi_caption'     => 'मथळा',
  'node.album.field_album_cover'           => 'मुखपृष्ठ चित्र',
];

$language_manager = \Drupal::languageManager();
foreach ($labels as $field => $label) {
  $override = $language_manager->getLanguageConfigOverride('mr', 'field.field.' . $field);
  $override->set('label', $label)->save();
}
print "Marathi labels applied to " . count($labels) . " fields.\n";
