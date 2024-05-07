<?php

namespace Drupal\config_exporter\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource for exporting configurations.
 *
 * @RestResource(
 *   id = "config_export_resource",
 *   label = @Translation("Config Export Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/config-export/{config_name}"
 *   }
 * )
 */
class ConfigExportResource extends ResourceBase {
  /**
   * The currently authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
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
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns details for the specified configuration.
   *
   * @param string $config_name
   *   The name of the configuration.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the configuration detail.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
   *   When configuration does not exists or not allowed to view.
   */
  public function get($config_name = NULL) {

    // Get the list of allowed configurations.
    $config = $this->configFactory
      ->get('config_exporter.settings')
      ->get('selected_configurations') ?? [];

    // When configuration is not allowed to view.
    if (!in_array($config_name, $config)) {
      throw new BadRequestHttpException(sprintf('Configuration (%s) yet not exposed to view.', $config_name));
    }

    $config = $this->configFactory->get($config_name);

    // When config does not exists and only has an empty new config object.
    if ($config->isNew()) {
      throw new BadRequestHttpException(sprintf('Configuration (%s) does not exists.', $config_name));
    }
    else {
      $data[$config_name] = $config->getRawData();

      $response = new ResourceResponse($data);
      $response->getCacheableMetadata()->addCacheTags(['config:config_exporter.settings']);
      return $response;
    }
  }
}
