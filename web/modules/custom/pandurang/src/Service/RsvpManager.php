<?php

declare(strict_types=1);

namespace Drupal\pandurang\Service;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Reads and writes event responses in the {pn_event_rsvp} table.
 */
class RsvpManager {

  /**
   * The three answers an event card offers.
   */
  public const STATUSES = ['going', 'maybe', 'not_going'];

  public function __construct(
    protected Connection $database,
    protected AccountInterface $currentUser,
    protected TimeInterface $time,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Records a response, replacing any earlier one from the same member.
   *
   * @param int $nid
   *   The event node ID.
   * @param int $uid
   *   The responding user ID.
   * @param string $status
   *   One of going, maybe or not_going.
   * @param int $guests
   *   How many extra people are coming along.
   * @param string|null $note
   *   An optional short message.
   *
   * @return bool
   *   TRUE when the response was stored.
   */
  public function setResponse(int $nid, int $uid, string $status, int $guests = 0, ?string $note = NULL): bool {
    if (!in_array($status, self::STATUSES, TRUE)) {
      return FALSE;
    }

    $now = $this->time->getRequestTime();
    $this->database->merge('pn_event_rsvp')
      ->keys(['nid' => $nid, 'uid' => $uid])
      ->fields([
        'status' => $status,
        'guests' => max(0, $guests),
        'note' => $note !== NULL ? mb_substr($note, 0, 255) : NULL,
        'changed' => $now,
      ])
      ->insertFields([
        'nid' => $nid,
        'uid' => $uid,
        'status' => $status,
        'guests' => max(0, $guests),
        'note' => $note !== NULL ? mb_substr($note, 0, 255) : NULL,
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();

    $this->cacheTagsInvalidator->invalidateTags(['pn_rsvp:' . $nid]);
    return TRUE;
  }

  /**
   * Removes a member's response to an event.
   */
  public function clearResponse(int $nid, int $uid): void {
    $this->database->delete('pn_event_rsvp')
      ->condition('nid', $nid)
      ->condition('uid', $uid)
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags(['pn_rsvp:' . $nid]);
  }

  /**
   * Returns one member's answer to an event, or NULL if they have not replied.
   */
  public function getResponse(int $nid, int $uid): ?array {
    $row = $this->database->select('pn_event_rsvp', 'r')
      ->fields('r', ['status', 'guests', 'note', 'changed'])
      ->condition('r.nid', $nid)
      ->condition('r.uid', $uid)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /**
   * Returns the head count per status for an event.
   *
   * @return array
   *   Keyed by status, each holding 'people' (members) and 'guests'.
   */
  public function getCounts(int $nid): array {
    $counts = array_fill_keys(self::STATUSES, ['people' => 0, 'guests' => 0]);

    $query = $this->database->select('pn_event_rsvp', 'r');
    $query->addField('r', 'status');
    $query->addExpression('COUNT(r.id)', 'people');
    $query->addExpression('SUM(r.guests)', 'guests');
    $query->condition('r.nid', $nid);
    $query->groupBy('r.status');

    foreach ($query->execute() as $record) {
      $counts[$record->status] = [
        'people' => (int) $record->people,
        'guests' => (int) $record->guests,
      ];
    }

    return $counts;
  }

  /**
   * Lists everyone who answered an event, newest response first.
   *
   * @return array
   *   Rows of uid, name, status, guests, note and changed.
   */
  public function getResponders(int $nid): array {
    $query = $this->database->select('pn_event_rsvp', 'r');
    $query->join('users_field_data', 'u', 'u.uid = r.uid AND u.default_langcode = 1');
    $query->fields('r', ['uid', 'status', 'guests', 'note', 'changed']);
    $query->addField('u', 'name');
    $query->condition('r.nid', $nid);
    $query->orderBy('r.changed', 'DESC');

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Whether the current user may answer this event.
   */
  public function currentUserMayRespond(): bool {
    return $this->currentUser->isAuthenticated()
      && $this->currentUser->hasPermission('rsvp to events');
  }

}
