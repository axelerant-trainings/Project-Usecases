<?php

namespace Drupal\rate_limit\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
* Event subscriber for enforcing rate limits.
*/
class RateLimitSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;


  public function __construct(ConfigFactoryInterface $config_factory, Connection $database, TimeInterface $time) {
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->time = $time;
  }

  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 20];
    return $events;
  }

  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Do nothing in case API not accessed.
    if (!$request->query->get('_format')) {
      return;
    }

    // Respond with too many request when limit exceeeds.
    if ($wait = $this->checkIpRequestLimit($request->getClientIp())) {
      $status = 429;
      $message = $this->t('Rate limit exceeded. Please wait for another @wait seconds before making more requests.', [
        '@wait' => $wait,
      ]);

      $event->setResponse(new JsonResponse(['message' => $message], $status));
    }
  }

  /**
   * Verifies if IP request limit exceeds.
   */
  private function checkIpRequestLimit(string $ip) {
    $time = $this->time->getCurrentTime();
    $data = $this->getIpRateLimitDetail($ip);

    // When details already recorded for an IP.
    if ($data['timestamp']) {
      $settings = $this->configFactory->get('rate_limit.settings');

      // Keep monitoring API request counts within the given time limit.
      $timediff = $time - $data['timestamp'];
      if ($timediff < $settings->get('time_limit')) {

        // When request count exceeds the limit.
        if ($data['count'] >= $settings->get('count_limit')) {
          return $settings->get('time_limit') - $timediff;
        }
        else {
          // Increment the count keeping the timestamp same.
          $this->setIpRateLimitDetail($ip, $data['timestamp'], ++$data['count']);
        }
      }
      else {
        // Reset the counter after the given time limit is passed.
        $this->setIpRateLimitDetail($ip, $time, 1);
      }
    }
    else {
      // Add a new IP entry with counter value 1.
      $this->setIpRateLimitDetail($ip, $time, 1);
    }

  }

  /**
   * Get the access details for a given IP address.
   */
  private function getIpRateLimitDetail(string $ip) {
    $query = $this->database->select('rate_limit', 'rl')
      ->fields('rl')
      ->condition('ip', $ip);

    return $query->execute()->fetchAssoc();
  }

  /**
   * Update the access details per IP request.
   */
  private function setIpRateLimitDetail(string $ip, int $timestamp, int $count) {
    // Old database entries can removed periodically to avoid long look up.
    $this->database->merge('rate_limit')
      ->key(['ip' => $ip])
      ->fields([
        'timestamp' => $timestamp,
        'count' => $count,
      ])
      ->execute();
  }
}
