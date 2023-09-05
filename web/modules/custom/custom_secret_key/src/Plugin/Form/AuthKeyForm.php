<?php

namespace Drupal\custom_secret_key\Plugin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class auth_keyForm
 *  Provides a config form to set default auth_key for the user while registering.
 */
class AuthKeyForm extends ConfigFormBase {

  /**
   * Config settins.
   *
   * @var string
   */
  const SETTING = 'custom_secret_key.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_secret_key.auth_key_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      static::SETTING,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTING);

    $form['auth_key_form']['auth_key'] = [
      '#type' => 'textfield',
      '#title' => 'Set Auth Key',
      '#default_value' => $config->get('auth_key') ?? "",
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTING);

    $config->set('auth_key', $form_state->getValue('auth_key'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
