<?php

declare(strict_types=1);

namespace Drupal\pandurang\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Turns the family_member nodes into the nested structure the tree UI wants.
 */
class FamilyTreeBuilder {

  /**
   * Cache ID prefix; the interface language is appended.
   */
  protected const CACHE_PREFIX = 'pandurang:tree:';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * Builds the whole family tree, rooted at the members with no parent.
   *
   * @return array
   *   A list of root nodes, each with a 'children' key.
   */
  public function getTree(): array {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $cid = self::CACHE_PREFIX . $langcode;

    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $members = $this->loadMembers($langcode);

    // Attach every member to its parent; the parentless ones become roots.
    $roots = [];
    foreach ($members as $id => &$member) {
      $parent = $member['parent'];
      if ($parent && isset($members[$parent])) {
        $members[$parent]['children'][] = &$member;
      }
      else {
        $roots[] = &$member;
      }
    }
    unset($member);

    $this->cache->set($cid, $roots, CacheBackendInterface::CACHE_PERMANENT, [
      'node_list:family_member',
    ]);

    return $roots;
  }

  /**
   * Returns the tree as a plain array suitable for drupalSettings or JSON.
   */
  public function getTreeData(): array {
    return $this->stripReferences($this->getTree());
  }

  /**
   * Loads every family member, flattened and keyed by node ID.
   */
  protected function loadMembers(string $langcode): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'family_member')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_fm_generation', 'ASC')
      ->sort('nid', 'ASC')
      ->execute();

    if (!$nids) {
      return [];
    }

    $members = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      if ($node->hasTranslation($langcode)) {
        $node = $node->getTranslation($langcode);
      }

      $photo = NULL;
      if (!$node->get('field_fm_photo')->isEmpty()) {
        $file = $node->get('field_fm_photo')->entity;
        if ($file) {
          $photo = \Drupal::service('file_url_generator')->generateString($file->getFileUri());
        }
      }

      $members[(int) $node->id()] = [
        'nid' => (int) $node->id(),
        'id' => $node->get('field_fm_legacy_id')->value ?? ('nid-' . $node->id()),
        'name' => $node->label(),
        'spouse' => $node->get('field_fm_spouse')->value,
        'sex' => $node->get('field_fm_sex')->value ?? 'male',
        'generation' => (int) ($node->get('field_fm_generation')->value ?? 0),
        'photo' => $photo,
        'url' => $node->toUrl()->toString(),
        'parent' => $node->get('field_fm_parent')->isEmpty()
          ? NULL
          : (int) $node->get('field_fm_parent')->target_id,
        'children' => [],
      ];
    }

    return $members;
  }

  /**
   * Rebuilds the nested array without the by-reference links, so it can be
   * JSON-encoded safely.
   */
  protected function stripReferences(array $branch): array {
    $out = [];
    foreach ($branch as $item) {
      $children = $item['children'] ?? [];
      $item['children'] = $children ? $this->stripReferences($children) : [];
      $out[] = $item;
    }
    return $out;
  }

  /**
   * Clears the cached tree in every language.
   */
  public function invalidate(): void {
    foreach (array_keys($this->languageManager->getLanguages()) as $langcode) {
      $this->cache->delete(self::CACHE_PREFIX . $langcode);
    }
  }

}
