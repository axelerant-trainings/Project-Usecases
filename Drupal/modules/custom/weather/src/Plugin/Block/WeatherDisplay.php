<?php

namespace Drupal\weather\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\weather\Form\WeatherSettingsForm;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Hello' Block.
 */
#[Block(
  id: "weather_display",
  admin_label: new TranslatableMarkup("Weather Display"),
  category: new TranslatableMarkup("Custom")
)]
class WeatherDisplay extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Guzzle HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->config = $config_factory;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('http_client'),
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
    // Get weather settings.
    $weather_settings = $this->config->get(WeatherSettingsForm::SETTINGS);

    // Bail out if the config is empty.
    if (empty($weather_settings)) {
      return [];
    }

    // Get location details from session.
    $city = $this->configuration['city'];
    if (!$city) {
      $city_not_found_message = $this->t('No city entered. Please enter a city to see the weather details');
      return [
        '#markup' => '<div class="weather--error">' . $city_not_found_message . '</div>',
      ];
    }

    // Endpoint that should be hit.
    $request_url = $weather_settings->get('base_url') . '/current.json';
    // Make a HTTP GET request to the endpoint.
    try {
      $request = $this->httpClient->request('GET', $request_url, [
        'query' => [
          'key' => $weather_settings->get('api_key'),
          'q' => $city,
          'units' => 'metric',
        ],
      ]);

      // Parse the response.
      $response = Json::decode($request->getBody());
      $weather_data = [];
      $weather_data['location'] = $response['location']['name'] . ', ' . $response['location']['region'] . ', ' . $response['location']['country'];
      $weather_data['temperature'] = $response['current']['temp_c'];
      $weather_data['feels_like'] = $response['current']['feelslike_c'];
      $weather_data['weather_condition_icon'] = $response['current']['condition']['icon'];
      $weather_data['weather_condition_text'] = $response['current']['condition']['text'];
      $weather_data['wind'] = $response['current']['wind_kph'];
      $weather_data['precipitation'] = $response['current']['precip_mm'];

      return [
        '#theme' => 'weather_display',
        '#weather_data' => $weather_data,
        '#cache' => [
          'max-age' => 3600,
        ],
      ];
    }
    catch (RequestException $e) {
      $error = $e->getResponse()->getBody()->getContents();
      $error_response = Json::decode($error);

      return [
        '#markup' => '<div class="weather--error">' . $error_response['error']['message'] . '</div>',
      ];
    }
  }

}
