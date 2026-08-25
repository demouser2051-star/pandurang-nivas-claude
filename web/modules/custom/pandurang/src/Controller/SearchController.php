<?php

declare(strict_types=1);

namespace Drupal\pandurang\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Backs the header search box.
 *
 * The entity type manager and language manager both come from ControllerBase.
 */
class SearchController extends ControllerBase {

  /**
   * How many rows the dropdown shows.
   */
  protected const LIMIT = 8;

  /**
   * Returns nodes whose title matches the term, as JSON.
   */
  public function search(Request $request): CacheableJsonResponse {
    $term = trim((string) $request->query->get('q', ''));
    $results = [];

    if (mb_strlen($term) >= 2) {
      $storage = $this->entityTypeManager()->getStorage('node');
      $langcode = $this->languageManager()->getCurrentLanguage()->getId();

      // Family members and gallery items are family-only, so leave them out
      // unless the person searching is allowed to see them.
      $bundles = ['event', 'page', 'article'];
      if ($this->currentUser()->hasPermission('view pn private content')) {
        $bundles = array_merge($bundles, ['family_member', 'gallery_item', 'album']);
      }

      // escapeLike() lives on the database connection, not on entity queries.
      $pattern = '%' . \Drupal::database()->escapeLike($term) . '%';

      $nids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundles, 'IN')
        ->condition('status', NodeInterface::PUBLISHED)
        ->condition('title', $pattern, 'LIKE')
        ->range(0, self::LIMIT)
        ->execute();

      $labels = [
        'event' => $this->t('Events'),
        'family_member' => $this->t('Family Tree'),
        'gallery_item' => $this->t('Gallery'),
        'album' => $this->t('Albums'),
        'page' => $this->t('Pages'),
        'article' => $this->t('News'),
      ];

      foreach ($storage->loadMultiple($nids) as $node) {
        if ($node->hasTranslation($langcode)) {
          $node = $node->getTranslation($langcode);
        }
        $results[] = [
          'title' => $node->label(),
          'type' => (string) ($labels[$node->bundle()] ?? $node->bundle()),
          'url' => $node->toUrl()->toString(),
        ];
      }
    }

    $response = new CacheableJsonResponse($results);

    $metadata = new CacheableMetadata();
    $metadata->addCacheContexts([
      'url.query_args:q',
      'languages:language_interface',
      'user.permissions',
    ]);
    $metadata->addCacheTags(['node_list']);
    $response->addCacheableDependency($metadata);

    return $response;
  }

}
