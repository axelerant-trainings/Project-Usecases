<?php

namespace Drupal\config_exporter\Form;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigManagerForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Tracks the valid config entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $definitions = [];

  /**
   * Constructs a new ConfigSingleImportForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StorageInterface $config_storage) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configStorage = $config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'config_exporter.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_exporter_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = $this->getListOfConfigOptions();
    $selected = $this->config('config_exporter.settings')->get('selected_configurations') ?: [];

    $form['configurations'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose configurations to expose via API'),
      '#options' => $options,
      '#default_value' => $selected,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns a list of general configurations.
   *
   * @return array
   *  A list of general conficgurations.
   */
  private function getListOfConfigOptions() {

    // Entity related configurations.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type => $definition) {
      if ($definition->entityClassImplements(ConfigEntityInterface::class)) {
        $this->definitions[$entity_type] = $definition;
      }
    }

    // Gather the config entity prefixes.
    $config_prefixes = array_map(function (EntityTypeInterface $definition) {
      return $definition->getConfigPrefix() . '.';
    }, $this->definitions);

    // Find all config, and then filter out entity configurations.
    $names = $this->configStorage->listAll();
    $names = array_combine($names, $names);
    foreach ($names as $config_name) {
      foreach ($config_prefixes as $config_prefix) {
        if (str_starts_with($config_name, $config_prefix)) {
          unset($names[$config_name]);
        }
      }
    }

    return $names;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $selected = array_filter($form_state->getValue('configurations'));

    // Remove keys and reset to sequential numeric keys
    // to avoid, 'key contains a dot which is not supported' error.
    $selected = array_values($selected);

    $this->config('config_exporter.settings')
      ->set('selected_configurations', $selected)
      ->save();

    parent::submitForm($form, $form_state);
  }
}
