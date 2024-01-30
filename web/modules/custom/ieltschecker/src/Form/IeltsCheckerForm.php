<?php

namespace Drupal\ieltschecker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Parsedown;
use function Psy\info;

/**
 * Form for send request to ChatGPT.
 */
class IeltsCheckerForm extends FormBase {

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected $client;

  /**
   * The OpenAI client.
   *
   * @var \OpenAI\Client
   */
  protected $api;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ieltschecker_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->client = $container->get('openai.client');
    $instance->api = $container->get('openai.api');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $parsedown = new Parsedown();
    if ($node) {
      $default_task1_description = $node->field_task1_description->processed;
      $default_task1_result = $node->field_task1_result->processed;
      $default_task2_description = $node->field_task2_description->processed;
      $default_task2_result = $node->field_task2_result->processed;
    }
    else {
      $default_task1_description = $default_task1_result = $default_task2_description = $default_task2_result = '';
    }
    $form['task1'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Task one'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['task1']['description1'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('First task description'),
      '#placeholder' => $this->t('Insert task part one'),
      '#default_value' => $default_task1_description,
    ];
    $form['task1']['result1'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('Your result'),
      '#default_value' => $default_task1_result,
      '#placeholder' => $this->t('Insert your task one text'),
    ];
    $form['task1']['response1'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="openai-chatgpt-response1">',
      '#suffix' => '</div>',
    ];

    $form['task2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Task two'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['task2']['description2'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('Second task description'),
      '#placeholder' => $this->t('Insert task part two'),
      '#default_value' => $default_task2_description,
    ];
    $form['task2']['result2'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#title' => $this->t('Your result'),
      '#placeholder' => $this->t('Insert your task two text'),
      '#default_value' => $default_task2_result,
    ];
    $form['task2']['response2'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="openai-chatgpt-response2">',
      '#suffix' => '</div>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['response'] = [
      '#type' => 'markup',
      '#title' => $this->t('Response'),
      '#prefix' => '<div id="openai-chatgpt-response">',
      '#suffix' => '</div>',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Check'),
      '#submit' => [
        [$this, 'submitForm'],
      ],
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'openai-chatgpt-response1',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Analyzing data...'),
        ],
      ],
    ];
    return $form;
  }

  /**
   * Render the last response out to the user.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The modified form element.
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $errors = $form_state->getErrors();
    $storage = $form_state->getStorage();
    if (empty($errors) && !empty($storage['results'])) {
      $form['task1']['response1']['#markup'] = trim($storage['results']['task1']) ?? $this->t('No answer was provided.');
      $form['task2']['response2']['#markup'] = trim($storage['results']['task2']) ?? $this->t('No answer was provided.');
    }
    else {
      $form['task1']['response1']['#markup'] = $form['task2']['response2']['#markup'] = 'Run-time error';
    }
    return $form['task1']['response1'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $task1_description = $form_state->getValue('description1');
    $task2_description = $form_state->getValue('description2');
    $task1_result = $form_state->getValue('result1');
    $task2_result = $form_state->getValue('result2');

    $prompt_text_task1 = \Drupal::config('ieltschecker.settings')->get('prompt_text_task1');
    $prompt_text_task1 = str_replace('{task_description}', $task1_description['value'], $prompt_text_task1);
    $prompt_text_task1 = str_replace('{task_result}', $task1_result['value'], $prompt_text_task1);

    $prompt_text_task2 = \Drupal::config('ieltschecker.settings')->get('prompt_text_task1');
    $prompt_text_task2 = str_replace('{task_description}', $task2_description['value'], $prompt_text_task2);
    $prompt_text_task2 = str_replace('{task_result}', $task2_result['value'], $prompt_text_task2);


    \Drupal::logger('te')->notice($prompt_text_task1);
    $system = 'You are a friendly helpful assistant inside of a Drupal website. Be encouraging and polite and ask follow up questions of the user after giving the answer.';
    $model = 'gpt-3.5-turbo';
    $temperature = '0.4';
    $max_tokens = '2000';
    $Parsedown = new Parsedown();

    $request1 = [
      ['role' => 'system', 'content' => trim($system)],
      ['role' => 'user', 'content' => trim($prompt_text_task1)],
    ];
    $result = $this->api->chat($model, $request1, $temperature, $max_tokens);
    $results['task1'] = $Parsedown->text($result);

    $request2 = [
      ['role' => 'system', 'content' => trim($system)],
      ['role' => 'user', 'content' => trim($prompt_text_task2)],
    ];
    $result = $this->api->chat($model, $request2, $temperature, $max_tokens);
    $results['task2'] = $Parsedown->text($result);

    $form_state->setStorage(['results' => $results]);
    $form_state->setRebuild(TRUE);

  }

}
