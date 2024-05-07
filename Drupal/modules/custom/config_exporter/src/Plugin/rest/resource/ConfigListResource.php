<?php

namespace Drupal\config_exporter\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource for fetching allowed config list.
 *
 * @RestResource(
 *   id = "config_list",
 *   label = @Translation("Allowed Config List"),
 *   uri_paths = {
 *     "canonical" = "/api/config-list"
 *   }
 * )
 */

class ConfigListResource extends ResourceBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a ConfigExportResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The currently authenticated user.
   * @param \Drupal\contact\MailHandlerInterface $mail_handler
   *   The contact mail handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer_formats, LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('config.factory')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns list of allowed configurations.
   *
   * @param string $config_name
   *   The name of the configuration.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the list of allowed configurations.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
   *   When admin did not allowed any configuration to view.
   */
  public function get() {

    $config = $this->configFactory
      ->get('config_exporter.settings')
      ->get('selected_configurations') ?? [];

    // When config does not exists and only has an empty new config object.
    if (empty($config)) {
      throw new BadRequestHttpException('Currently, site admin did not allow any configuration to view.');
    }
    else {
      $response = new ResourceResponse($config);
      $response->getCacheableMetadata()->addCacheTags(['config:config_exporter.settings']);
      return $response;
    }
  }
}
