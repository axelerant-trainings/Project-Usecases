<?php

namespace Drupal\weather\Services;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\weather\Form\WeatherSettingsForm;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service class to handle weather related operations.
 */
class Weather {

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
   * Constructs a new Weather object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The http client service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client,) {
    $this->config = $config_factory;
    $this->httpClient = $http_client;
  }

  /**
   * Get Today's weather information.
   *
   * Make an http request to the weather endpoint, parse
   * the response and display the details.
   *
   * @param string $city
   *   The city whose weather is to be displayed.
   *
   * @return mixed
   *   Response based on the API output.
   */
  public function getTodaysWeather($city): mixed {
    // Get weather settings.
    $weather_settings = $this->config->get(WeatherSettingsForm::SETTINGS);
    if (empty($weather_settings)) {
      return [];
    }

    // Endpoint that should be hit.
    $request_url = $weather_settings->get('base_url') . '/current.json';

    // Make a HTTP GET request to the endpoint.
    try {
      $request = $this->httpClient->request('GET', $request_url, [
        'query' => [
          'key' => $weather_settings->get('api_key'),
          'q' => $city,
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
