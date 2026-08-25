<?php

declare(strict_types=1);

namespace Drupal\pandurang\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\node\NodeInterface;
use Drupal\pandurang\Service\RsvpManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Handles event responses and the admin-facing response report.
 */
class RsvpController extends ControllerBase {

  public function __construct(
    protected RsvpManager $rsvp,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('pandurang.rsvp'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Stores the current user's answer and returns the updated head count.
   */
  public function submit(NodeInterface $node, Request $request): JsonResponse {
    if ($node->bundle() !== 'event') {
      throw new BadRequestHttpException('Only events accept responses.');
    }
    if ($node->get('field_event_rsvp')->isEmpty() || !$node->get('field_event_rsvp')->value) {
      throw new BadRequestHttpException('This event is not collecting responses.');
    }

    $payload = json_decode($request->getContent(), TRUE) ?: [];
    $status = $payload['status'] ?? '';
    $uid = (int) $this->currentUser()->id();

    if ($status === 'clear') {
      $this->rsvp->clearResponse((int) $node->id(), $uid);
      return new JsonResponse([
        'status' => NULL,
        'counts' => $this->rsvp->getCounts((int) $node->id()),
      ]);
    }

    if (!in_array($status, RsvpManager::STATUSES, TRUE)) {
      throw new BadRequestHttpException('Unknown response.');
    }

    $stored = $this->rsvp->setResponse(
      (int) $node->id(),
      $uid,
      $status,
      (int) ($payload['guests'] ?? 0),
      isset($payload['note']) ? (string) $payload['note'] : NULL,
    );

    if (!$stored) {
      throw new BadRequestHttpException('The response could not be saved.');
    }

    return new JsonResponse([
      'status' => $status,
      'counts' => $this->rsvp->getCounts((int) $node->id()),
    ]);
  }

  /**
   * Table of everyone who answered a given event.
   */
  public function report(NodeInterface $node): array {
    if ($node->bundle() !== 'event') {
      throw new AccessDeniedHttpException();
    }

    $labels = [
      'going' => $this->t('Going'),
      'maybe' => $this->t('Maybe'),
      'not_going' => $this->t('Not going'),
    ];

    $rows = [];
    foreach ($this->rsvp->getResponders((int) $node->id()) as $record) {
      $rows[] = [
        $record['name'],
        $labels[$record['status']] ?? $record['status'],
        $record['guests'],
        $record['note'] ?? '',
        $this->dateFormatter->format((int) $record['changed'], 'short'),
      ];
    }

    $counts = $this->rsvp->getCounts((int) $node->id());
    $summary = [];
    foreach ($labels as $key => $label) {
      $summary[] = $this->t('@label: @people (+@guests guests)', [
        '@label' => $label,
        '@people' => $counts[$key]['people'],
        '@guests' => $counts[$key]['guests'],
      ]);
    }

    return [
      'summary' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Responses for %event', ['%event' => $node->label()]),
        '#items' => $summary,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Member'),
          $this->t('Response'),
          $this->t('Guests'),
          $this->t('Note'),
          $this->t('Updated'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nobody has responded yet.'),
      ],
      '#cache' => [
        'tags' => ['pn_rsvp:' . $node->id()],
      ],
    ];
  }

}
