<?php

declare(strict_types=1);

namespace Drupal\pandurang\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\pandurang\Service\EventCalendar;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A month calendar with a dot on every day something happens.
 *
 * @Block(
 *   id = "pandurang_event_calendar",
 *   admin_label = @Translation("Event calendar"),
 *   category = @Translation("Pandurang Nivas")
 * )
 */
class EventCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EventCalendar $calendar,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('pandurang.event_calendar'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#theme' => 'pandurang_event_calendar',
      '#attached' => $this->calendar->attachments(),
      '#cache' => [
        'tags' => ['node_list:event'],
        'contexts' => ['languages:language_interface', 'user.permissions'],
      ],
    ];
  }

}
