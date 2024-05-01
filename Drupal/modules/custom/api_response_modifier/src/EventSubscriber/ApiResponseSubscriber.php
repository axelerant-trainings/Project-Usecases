<?php

namespace Drupal\api_response_modifier\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class ApiResponseSubscriber.
 */
class ApiResponseSubscriber implements EventSubscriberInterface {

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new ApiResponseSubscriber.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('api_response_modifier');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onResponse', 0];
    return $events;
  }

  /**
   * Responds to kernel response events.
   */
  public function onResponse(ResponseEvent $event) {
    $request = $event->getRequest();
    if ($request->attributes->get('_route') === 'jsonapi.node--article.individual.delete') {
      if ($request->getMethod() === 'DELETE') {
        $response = $event->getResponse();
        // Check if the response status code indicates a successful deletion.
        if ($response->getStatusCode() === Response::HTTP_NO_CONTENT) {
          $node = $request->attributes->get('entity');
          $this->logger->info('Article \'@label\' (@nid) deleted via API.', [
            '@label' => $node->label(),
            '@nid' => $node->id(),
          ]);
        }
      }
    }
  }
}
