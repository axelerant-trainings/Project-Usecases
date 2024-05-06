<?php

namespace Drupal\weather\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\weather\Services\Weather;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Today's Weather" Block.
 */
#[Block(
  id: "weather_display",
  admin_label: new TranslatableMarkup("Weather Display"),
  category: new TranslatableMarkup("Custom")
)]
class WeatherDisplay extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The weather service.
   *
   * @var \Drupal\weather\Services\Weather
   */
  protected $weather;

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\weather\Services\Weather $weather
   *   The weather service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Weather $weather) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->weather = $weather;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('weather')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'city' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#description' => $this->t('Please enter the city to show weather details. Eg: Mumbai, India'),
      '#default_value' => $this->configuration['city'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['city'] = $form_state->getValue('city');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get city name from block configuration.
    $city = $this->configuration['city'];
    if (!$city) {
      $city_not_found_message = $this->t('No city entered. Please enter a city to see the weather details');
      return [
        '#markup' => '<div class="weather--error">' . $city_not_found_message . '</div>',
      ];
    }

    // Show today's weather.
    return $this->weather->getTodaysWeather($city);
  }

}
