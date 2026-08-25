<?php

declare(strict_types=1);

namespace Drupal\pandurang\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\pandurang\Service\FamilyTreeBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assembles the front page from the same sections the original site had.
 */
class HomeController extends ControllerBase {

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
   * The front page title, which is simply the site name in the active
   * language. The theme drops it from the head title, where it would otherwise
   * repeat the site name.
   */
  public function pageTitle(): string {
    return (string) $this->config('system.site')->get('name');
  }

  /**
   * The front page.
   */
  public function page(): array {
    $is_member = $this->currentUser()->hasPermission('view pn private content');

    $build = [
      '#theme' => 'pandurang_home',
      '#hero' => $this->heroSlides(),
      '#about' => $this->aboutSection(),
      '#tree_preview' => $is_member ? $this->treePreview() : NULL,
      '#gallery' => $is_member ? $this->viewBlock('pn_gallery', 'block_1') : NULL,
      // Show what is coming up; if the calendar is empty, show what just
      // happened rather than leaving the section blank.
      '#events' => $this->viewBlock('pn_events', 'block_upcoming')
        ?? $this->viewBlock('pn_events', 'block_recent'),
      '#is_member' => $is_member,
      '#attached' => [
        'library' => [
          'pandurang_nivas/hero',
          'pandurang_nivas/gallery',
          'pandurang_nivas/events',
          'pandurang/family-tree',
        ],
        'drupalSettings' => [
          'pandurang' => [
            'eventDates' => \Drupal::service('pandurang.event_calendar')->getDatedEvents(),
          ],
        ],
      ],
      '#cache' => [
        'tags' => ['node_list:event', 'node_list:gallery_item', 'node_list:family_member', 'node_list:page'],
        'contexts' => ['user.permissions', 'languages:language_interface'],
      ],
    ];

    return $build;
  }

  /**
   * The four hero slides, translated through the interface language.
   */
  protected function heroSlides(): array {
    return [
      [
        'title' => $this->t('Pandurang Nivas Family'),
        'subtitle' => $this->t('Preserving our traditional Marathi heritage for generations'),
        'cta' => $this->t('Join the Family'),
        'url' => $this->currentUser()->isAuthenticated()
          ? '/family-tree'
          : '/user/register',
      ],
      [
        'title' => $this->t('Family Connecting Platform'),
        'subtitle' => $this->t('Digital platform connecting all family members'),
        'cta' => $this->t('Learn More'),
        'url' => '#about',
      ],
      [
        'title' => $this->t('Traditional Marathi Culture'),
        'subtitle' => $this->t('Preserving our traditions, culture and values'),
        'cta' => $this->t('View Our Traditions'),
        'url' => '#about',
      ],
      [
        'title' => $this->t('Festivals and Celebrations'),
        'subtitle' => $this->t('Participate in all festivals and events'),
        'cta' => $this->t('View Events'),
        'url' => '/events',
      ],
    ];
  }

  /**
   * The About node, rendered in its teaser form.
   */
  protected function aboutSection(): ?array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    // The About page is matched on its path alias so editors can rename it.
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'page')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('nid', 'ASC')
      ->range(0, 1)
      ->execute();

    if (!$nids) {
      return NULL;
    }

    $node = $storage->load(reset($nids));
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }

    return [
      'title' => $node->label(),
      'body' => $node->get('body')->isEmpty() ? '' : $node->get('body')->processed,
      'url' => $node->toUrl()->toString(),
    ];
  }

  /**
   * The top of the tree, as a taste of the whole thing.
   *
   * Three generations: the register is rooted at धोंडोजीं, whose only child is
   * पांडुरंग, so stopping at two would show just two names.
   */
  protected function treePreview(): array {
    return $this->trimTree($this->treeBuilder->getTree(), 3);
  }

  /**
   * Returns a copy of a nested tree cut off below the given depth.
   */
  protected function trimTree(array $branch, int $depth): array {
    if ($depth <= 1) {
      return array_map(
        fn(array $member) => ['children' => []] + $member,
        $branch
      );
    }

    return array_map(function (array $member) use ($depth) {
      $member['children'] = empty($member['children'])
        ? []
        : $this->trimTree($member['children'], $depth - 1);
      return $member;
    }, $branch);
  }

  /**
   * Renders one display of a view, or NULL when it has no results.
   */
  protected function viewBlock(string $view_id, string $display_id): ?array {
    $view = \Drupal::entityTypeManager()->getStorage('view')->load($view_id);
    if (!$view) {
      return NULL;
    }

    $executable = $view->getExecutable();
    $executable->setDisplay($display_id);
    $executable->preExecute();
    $executable->execute();

    if (!$executable->result) {
      return NULL;
    }

    return $executable->buildRenderable($display_id, []);
  }


}
