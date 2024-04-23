<?php

namespace Drupal\weather\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure weather settings.
 */
class WeatherSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'weather.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'weather_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];

    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL'),
      '#default_value' => $config->get('base_url'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Save the configuration.
    $this->config(static::SETTINGS)
      ->set('api_key', $values['api_key'])
      ->set('base_url', $values['base_url'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
