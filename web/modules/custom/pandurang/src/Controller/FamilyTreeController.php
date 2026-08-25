<?php

declare(strict_types=1);

namespace Drupal\pandurang\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\pandurang\Service\FamilyTreeBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Serves the family tree page and its JSON feed.
 */
class FamilyTreeController extends ControllerBase {

  public function __construct(
    protected FamilyTreeBuilder $treeBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('pandurang.family_tree'));
  }

  /**
   * The page title, in the active interface language.
   */
  public function pageTitle(): string {
    return (string) $this->t('Family Tree');
  }

  /**
   * The full-page tree.
   */
  public function page(): array {
    $build = [
      '#theme' => 'pandurang_family_tree',
      '#tree' => $this->treeBuilder->getTree(),
      '#attached' => [
        'library' => ['pandurang/family-tree'],
        'drupalSettings' => [
          'pandurang' => [
            'familyTree' => $this->treeBuilder->getTreeData(),
          ],
        ],
      ],
      '#cache' => [
        'tags' => ['node_list:family_member'],
        'contexts' => ['languages:language_interface', 'user.permissions'],
      ],
    ];

    return $build;
  }

  /**
   * The same tree as JSON, for the client-side renderer.
   */
  public function data(): CacheableJsonResponse {
    $response = new CacheableJsonResponse($this->treeBuilder->getTreeData());

    $metadata = new CacheableMetadata();
    $metadata->addCacheTags(['node_list:family_member']);
    $metadata->addCacheContexts(['languages:language_interface', 'user.permissions']);
    $response->addCacheableDependency($metadata);

    return $response;
  }

}
