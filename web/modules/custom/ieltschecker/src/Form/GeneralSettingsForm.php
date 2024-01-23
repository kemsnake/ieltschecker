<?php

namespace Drupal\ieltschecker\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GeneralSettingsForm. The config form for the ieltschecker module.
 *
 * @package Drupal\ieltschecker\Form
 */
class GeneralSettingsForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ieltschecker.settings',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'ieltschecker_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ieltschecker.settings');
    $form['prompt_text'] = [
      '#type' => 'textarea',
      '#rows' => 20,
      '#title' => $this->t('Prompt text'),
      '#description' => $this->t('You can use snippets {task1_description}, {task1_result} and {task2_description}, {task2_result}.'),
      '#default_value' => $config->get('prompt_text'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ieltschecker.settings')
      ->set('prompt_text', $form_state->getValue('prompt_text'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
