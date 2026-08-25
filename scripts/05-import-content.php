<?php

/**
 * @file
 * Imports the site content: albums, gallery items, events, notifications and
 * the standing pages. Every node is created in Marathi with an English
 * translation alongside it.
 *
 * Idempotent - existing nodes are matched on their Marathi title and updated.
 *
 * Run: drush php:script scripts/05-import-content.php
 */

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

$source_images = __DIR__ . '/../data/images';
$storage = \Drupal::entityTypeManager()->getStorage('node');
$file_system = \Drupal::service('file_system');

/**
 * Copies one of the source photos into public:// and returns the file entity.
 */
$import_image = function (?string $filename) use ($source_images, $file_system): ?File {
  if (!$filename) {
    return NULL;
  }
  $source = $source_images . '/' . $filename;
  if (!is_file($source)) {
    print "    ! missing image: $filename\n";
    return NULL;
  }

  $directory = 'public://gallery';
  $file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $destination = $directory . '/' . $filename;

  // Reuse the file entity if this image is already in place.
  $existing = \Drupal::entityTypeManager()->getStorage('file')
    ->loadByProperties(['uri' => $destination]);
  if ($existing) {
    return reset($existing);
  }

  $uri = $file_system->copy($source, $destination, \Drupal\Core\File\FileExists::Replace);
  $file = File::create(['uri' => $uri, 'status' => 1, 'uid' => 1]);
  $file->save();

  return $file;
};

/**
 * Creates or updates a node, then adds its English translation.
 *
 * @param string $type
 *   The content type.
 * @param array $mr
 *   Marathi field values, including 'title'.
 * @param array $en
 *   English field values that differ from the Marathi ones.
 * @param array $shared
 *   Language-neutral field values.
 */
$upsert = function (string $type, array $mr, array $en, array $shared = []) use ($storage): Node {
  $matches = $storage->loadByProperties(['type' => $type, 'title' => $mr['title']]);
  $node = $matches ? reset($matches) : NULL;

  if (!$node) {
    $node = Node::create([
      'type' => $type,
      'langcode' => 'mr',
      'uid' => 1,
      'status' => 1,
    ]);
  }

  foreach ($mr + $shared as $field => $value) {
    $node->set($field, $value);
  }
  $node->save();

  // The English side carries the translated labels; shared fields ride along.
  if ($en) {
    $translation = $node->hasTranslation('en')
      ? $node->getTranslation('en')
      : $node->addTranslation('en');

    foreach ($en as $field => $value) {
      $translation->set($field, $value);
    }
    $translation->save();
  }

  return $node;
};

// ---------------------------------------------------------------------------
// Albums.
// ---------------------------------------------------------------------------
print "Albums\n";

$albums = [
  'family-gathering' => [
    'mr' => 'कुटुंब भेट २०२३',
    'en' => 'Family Gathering 2023',
    'image' => '2.jpg',
  ],
  'diwali' => [
    'mr' => 'दिवाळी साजरा',
    'en' => 'Diwali Celebration',
    'image' => '7.jpg',
  ],
  'wedding' => [
    'mr' => 'विवाह समारंभ',
    'en' => 'Wedding Ceremony',
    'image' => '9.JPG',
  ],
  'farm' => [
    'mr' => 'शेतातील काम',
    'en' => 'Farm Work',
    'image' => '8.jpg',
  ],
  'holi' => [
    'mr' => 'होळी उत्सव',
    'en' => 'Holi Festival',
    'image' => '10.jpg',
  ],
  'picnic' => [
    'mr' => 'कौटुंबिक सहल',
    'en' => 'Family Picnic',
    'image' => '11.JPG',
  ],
];

$album_nids = [];
foreach ($albums as $key => $album) {
  $cover = $import_image($album['image']);
  $node = $upsert('album',
    ['title' => $album['mr']],
    ['title' => $album['en']],
    ['field_album_cover' => $cover ? ['target_id' => $cover->id(), 'alt' => $album['en']] : NULL],
  );
  $album_nids[$key] = $node->id();
  print '  ' . $album['mr'] . "\n";
}

// ---------------------------------------------------------------------------
// Gallery items.
// ---------------------------------------------------------------------------
print "Gallery items\n";

$gallery = [
  ['mr' => 'कुटुंब भेट', 'en' => 'Family Gathering', 'type' => 'image', 'image' => '6.JPG', 'album' => 'family-gathering', 'date' => '2022-12-15', 'caption_mr' => '२०२२ डिसेंबर', 'caption_en' => 'December 2022'],
  ['mr' => 'दिवाळी साजरा', 'en' => 'Diwali Celebration', 'type' => 'image', 'image' => '7.jpg', 'album' => 'diwali', 'date' => '2022-11-10', 'caption_mr' => '२०२२ नोव्हेंबर', 'caption_en' => 'November 2022'],
  ['mr' => 'शेतातील काम', 'en' => 'Farm Work', 'type' => 'image', 'image' => '8.jpg', 'album' => 'farm', 'date' => '2023-01-20', 'caption_mr' => '२०२३ जानेवारी', 'caption_en' => 'January 2023'],
  ['mr' => 'विवाह समारंभ', 'en' => 'Wedding Ceremony', 'type' => 'image', 'image' => '9.JPG', 'album' => 'wedding', 'date' => '2023-03-05', 'caption_mr' => '२०२३ मार्च', 'caption_en' => 'March 2023'],
  ['mr' => 'होळी उत्सव', 'en' => 'Holi Festival', 'type' => 'video', 'image' => '10.jpg', 'album' => 'holi', 'date' => '2023-03-08', 'caption_mr' => '२०२३ मार्च', 'caption_en' => 'March 2023'],
  ['mr' => 'कुटुंब', 'en' => 'Family Photo', 'type' => 'video', 'image' => '11.JPG', 'album' => 'picnic', 'date' => '2022-06-12', 'caption_mr' => '२०२२ जून', 'caption_en' => 'June 2022'],
  ['mr' => 'पांडुरंग निवास', 'en' => 'Pandurang Nivas', 'type' => 'image', 'image' => '1.JPG', 'album' => 'family-gathering', 'date' => '2023-05-01', 'caption_mr' => '२०२३ मे', 'caption_en' => 'May 2023'],
  ['mr' => 'कौटुंबिक क्षण', 'en' => 'Family Moments', 'type' => 'image', 'image' => '4.JPG', 'album' => 'family-gathering', 'date' => '2023-06-18', 'caption_mr' => '२०२३ जून', 'caption_en' => 'June 2023'],
];

foreach ($gallery as $item) {
  $image = $import_image($item['image']);
  $upsert('gallery_item',
    [
      'title' => $item['mr'],
      'field_gi_caption' => $item['caption_mr'],
    ],
    [
      'title' => $item['en'],
      'field_gi_caption' => $item['caption_en'],
    ],
    [
      'field_gi_type' => $item['type'],
      'field_gi_image' => $image ? ['target_id' => $image->id(), 'alt' => $item['en']] : NULL,
      'field_gi_album' => $album_nids[$item['album']] ?? NULL,
      'field_gi_date' => $item['date'],
    ],
  );
  print '  ' . $item['mr'] . ' (' . $item['type'] . ")\n";
}

// ---------------------------------------------------------------------------
// Events.
// ---------------------------------------------------------------------------
print "Events\n";

$events = [
  [
    'mr' => [
      'title' => 'गणेशोत्सव २०२५',
      'body' => 'सर्व कुटुंबाच्या सदस्यांना आमंत्रित! वार्षिक गणेशोत्सव साजरा करण्यासाठी पांडुरंग निवास येथे सहभागी व्हा.',
      'field_event_location' => 'पांडुरंग निवास, हातदे',
      'field_event_time' => 'सकाळी ९ ते संध्याकाळी ८',
    ],
    'en' => [
      'title' => 'Ganeshotsav 2025',
      'body' => 'All family members are invited! Participate in the annual Ganeshotsav at Pandurang Nivas.',
      'field_event_location' => 'Pandurang Nivas, Hatade',
      'field_event_time' => '9 AM to 8 PM',
    ],
    'shared' => [
      'field_event_start' => '2025-08-25',
      'field_event_end' => '2025-09-02',
      'field_event_type' => 'festival',
      'field_event_rsvp' => TRUE,
      'image' => '2.jpg',
    ],
  ],
  [
    'mr' => [
      'title' => 'कौटुंबिक सहल',
      'body' => 'वार्षिक कौटुंबिक सहल. सकाळी ८ वाजता बस निघणार आहे. सर्वांनी आपापले जेवण डब्यातून आणावे.',
      'field_event_location' => 'सज्जनगड, पुणे',
      'field_event_time' => 'बस सकाळी ८ वाजता',
    ],
    'en' => [
      'title' => 'Family Picnic',
      'body' => 'Annual family picnic. The bus leaves at 8 AM. Everyone should bring their own food.',
      'field_event_location' => 'Sajjangad, Pune',
      'field_event_time' => 'Bus at 8 AM',
    ],
    'shared' => [
      'field_event_start' => '2025-09-10',
      'field_event_type' => 'trip',
      'field_event_rsvp' => TRUE,
      'image' => '11.JPG',
    ],
  ],
  [
    'mr' => [
      'title' => 'होळी उत्सव २०२४',
      'body' => 'रंगांच्या सणात सर्व कुटुंबाने आनंद घेतला.',
      'field_event_location' => 'पांडुरंग निवास, हातदे',
      'field_event_time' => 'दुपारी ४ नंतर',
    ],
    'en' => [
      'title' => 'Holi Festival 2024',
      'body' => 'The whole family enjoyed the festival of colours.',
      'field_event_location' => 'Pandurang Nivas, Hatade',
      'field_event_time' => 'After 4 PM',
    ],
    'shared' => [
      'field_event_start' => '2024-03-25',
      'field_event_type' => 'festival',
      'field_event_rsvp' => FALSE,
      'image' => '10.jpg',
    ],
  ],
  [
    'mr' => [
      'title' => 'मकरसंक्रांत २०२४',
      'body' => 'तिळगुळ घ्या, गोड गोड बोला.',
      'field_event_location' => 'पांडुरंग निवास, हातदे',
      'field_event_time' => 'सकाळपासून',
    ],
    'en' => [
      'title' => 'Makar Sankranti 2024',
      'body' => 'Take tilgul, and speak sweetly.',
      'field_event_location' => 'Pandurang Nivas, Hatade',
      'field_event_time' => 'From morning',
    ],
    'shared' => [
      'field_event_start' => '2024-01-14',
      'field_event_type' => 'festival',
      'field_event_rsvp' => FALSE,
      'image' => '8.jpg',
    ],
  ],
  [
    'mr' => [
      'title' => 'नाताळ साजरा २०२३',
      'body' => 'ख्रिसमसच्या सणाचा आनंद सर्वांनी घेतला.',
      'field_event_location' => 'पांडुरंग निवास, हातदे',
      'field_event_time' => 'संध्याकाळी',
    ],
    'en' => [
      'title' => 'Christmas 2023',
      'body' => 'Everyone enjoyed the Christmas celebration.',
      'field_event_location' => 'Pandurang Nivas, Hatade',
      'field_event_time' => 'Evening',
    ],
    'shared' => [
      'field_event_start' => '2023-12-25',
      'field_event_type' => 'gathering',
      'field_event_rsvp' => FALSE,
      'image' => '7.jpg',
    ],
  ],
];

foreach ($events as $event) {
  $shared = $event['shared'];
  $image = $import_image($shared['image'] ?? NULL);
  unset($shared['image']);

  $mr = $event['mr'];
  $mr['body'] = ['value' => $mr['body'], 'format' => 'basic_html'];
  $en = $event['en'];
  $en['body'] = ['value' => $en['body'], 'format' => 'basic_html'];

  $shared['field_event_image'] = $image
    ? ['target_id' => $image->id(), 'alt' => $en['title']]
    : NULL;

  $upsert('event', $mr, $en, $shared);
  print '  ' . $mr['title'] . "\n";
}

// ---------------------------------------------------------------------------
// Notifications.
// ---------------------------------------------------------------------------
print "Notifications\n";

$notifications = [
  ['mr_title' => 'नवीन सदस्य', 'mr_body' => 'राजेश चव्हाण कुटुंबात सामील झाले आहेत', 'en_title' => 'New Member', 'en_body' => 'Rajesh Chavan has joined the family'],
  ['mr_title' => 'सूचना', 'mr_body' => 'गणेशोत्सवासाठी योगदानाची विनंती', 'en_title' => 'Notice', 'en_body' => 'Contribution request for Ganeshotsav'],
  ['mr_title' => 'स्मरण', 'mr_body' => 'उन्हाळी सुट्टीतील कौटुंबिक सहल', 'en_title' => 'Reminder', 'en_body' => 'Family picnic during summer vacation'],
];

foreach ($notifications as $notification) {
  $upsert('notification',
    [
      'title' => $notification['mr_title'],
      'body' => ['value' => $notification['mr_body'], 'format' => 'basic_html'],
    ],
    [
      'title' => $notification['en_title'],
      'body' => ['value' => $notification['en_body'], 'format' => 'basic_html'],
    ],
  );
  print '  ' . $notification['mr_title'] . "\n";
}

// ---------------------------------------------------------------------------
// Standing pages.
// ---------------------------------------------------------------------------
print "Pages\n";

$about_mr = <<<HTML
<h2>आमचा कौटुंबिक वारसा</h2>
<p>रत्नागिरी जिल्ह्यातील हातदे गावात वसलेले पांडुरंग निवास ही आमची कौटुंबिक वास्तु, आमच्या कुटुंबाचे हृदय बनून आहे. वाघोटन नदीकाठी व जंगलाने वेढलेले हे स्थान आमचे आध्यात्मिक केंद्र आहे.</p>
<p>आमच्या पणजोबा, पांडुरंग चव्हाण यांनी ही निर्मिती केली. शेती, शिक्षण आणि समाजसेवेतील योगदानासह आम्ही आमची समृद्ध परंपरा जपत आहोत.</p>
<p>जगभर पसरले असले तरी, आम्ही गणपती, शिमगा, महाशिवरात्री सारख्या सणांसाठी येथे एकत्र येतो. सहा पिढ्यांमध्ये आमचे नातेसंबंध दृढ करणारे हे घर आमच्या मराठी वारशाचे प्रतीक आहे. पांडुरंग निवास ही केवळ एक इमारत नव्हे, तर आमची ओळख आहे.</p>
HTML;

$about_en = <<<HTML
<h2>Our Family Heritage</h2>
<p>Pandurang Nivas, our ancestral home in Hatade village of Ratnagiri district, has been the heart of our family for generations. Set beside the Waghotan river and ringed by forest, it is our spiritual centre.</p>
<p>Our great-grandfather, Pandurang Chavan, built this house. We continue to keep our rich traditions alive, with contributions in farming, education and social service.</p>
<p>Though the family is now spread across the world, we gather here for Ganpati, Shimga and Mahashivratri. Across six generations this house has held our bonds together, a symbol of our Marathi heritage. Pandurang Nivas is not merely a building - it is who we are.</p>
HTML;

$about = $upsert('page',
  ['title' => 'आमच्याबद्दल', 'body' => ['value' => $about_mr, 'format' => 'full_html']],
  ['title' => 'About Us', 'body' => ['value' => $about_en, 'format' => 'full_html']],
);

$privacy_mr = <<<HTML
<p>पांडुरंग निवास हे कुटुंबातील सदस्यांसाठीचे खाजगी व्यासपीठ आहे. या संकेतस्थळावरील कुटुंबवृक्ष, छायाचित्रे आणि कार्यक्रमांची माहिती केवळ नोंदणीकृत कुटुंबीयांनाच दिसते.</p>
<p>आम्ही तुमचा ईमेल आणि नाव केवळ तुमचे खाते चालवण्यासाठी वापरतो. ही माहिती कोणत्याही त्रयस्थ पक्षाला दिली जात नाही.</p>
<p>तुमचे खाते बंद करायचे असल्यास कृपया संपर्क करा: contactus@pandurangnivas.in</p>
HTML;

$privacy_en = <<<HTML
<p>Pandurang Nivas is a private platform for members of the family. The family tree, photographs and event details on this site are visible only to registered relatives.</p>
<p>We use your name and email address solely to run your account. This information is never shared with any third party.</p>
<p>To close your account, please write to contactus@pandurangnivas.in</p>
HTML;

$privacy = $upsert('page',
  ['title' => 'गोपनीयता धोरण', 'body' => ['value' => $privacy_mr, 'format' => 'full_html']],
  ['title' => 'Privacy Policy', 'body' => ['value' => $privacy_en, 'format' => 'full_html']],
);

print "  आमच्याबद्दल / About Us (node " . $about->id() . ")\n";
print "  गोपनीयता धोरण / Privacy Policy (node " . $privacy->id() . ")\n";

print "\nContent import complete.\n";
