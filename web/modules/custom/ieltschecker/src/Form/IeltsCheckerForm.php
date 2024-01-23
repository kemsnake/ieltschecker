<?php

namespace Drupal\ieltschecker\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Parsedown;

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

    if ($node) {
      $default_task1_description = strip_tags((string) $node->field_task1_description->processed);
      $default_task1_result = strip_tags((string) $node->field_task1_result->processed);
      $default_task2_description = strip_tags((string) $node->field_task2_description->processed);
      $default_task2_result = strip_tags((string) $node->field_task2_result->processed);
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
      '#type' => 'textarea',
      '#title' => $this->t('First task description'),
      '#placeholder' => $this->t('Insert task part one'),
      '#default_value' => $default_task1_description,
    ];
    $form['task1']['result1'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your result'),
      '#default_value' => $default_task1_result,
      '#placeholder' => $this->t('Insert your task one text'),
    ];

    $form['task2'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Task two'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['task2']['description2'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Second task description'),
      '#placeholder' => $this->t('Insert task part two'),
      '#default_value' => $default_task2_description,
    ];
    $form['task2']['result2'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your result'),
      '#placeholder' => $this->t('Insert your task two text'),
      '#default_value' => $default_task2_result,
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
        'wrapper' => 'openai-chatgpt-response',
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
    if (empty($errors) && !empty($storage)) {
      $last_response = end($storage['messages']);
      $form['response']['#markup'] = trim($last_response['content']) ?? $this->t('No answer was provided.');
    }
    else {
      $form['response']['#value'] = 'errors';
    }
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $task1_description = $form_state->getValue('description1');
    $task2_description = $form_state->getValue('description2');
    $task1_result = $form_state->getValue('result1');
    $task2_result = $form_state->getValue('result2');

    $prompt_text = \Drupal::config('ieltschecker.settings')->get('prompt_text');
    $prompt_text = str_replace('{task1_description}', $task1_description, $prompt_text);
    $prompt_text = str_replace('{task2_description}', $task2_description, $prompt_text);
    $prompt_text = str_replace('{task1_result}', $task1_result, $prompt_text);
    $prompt_text = str_replace('{task2_result}', $task2_result, $prompt_text);

    \Drupal::logger('te')->notice($prompt_text);
    $system = 'You are a friendly helpful assistant inside of a Drupal website. Be encouraging and polite and ask follow up questions of the user after giving the answer.';
    $model = 'gpt-3.5-turbo';
    $temperature = '0.4';
    $max_tokens = '1000';

    $messages = [
      ['role' => 'system', 'content' => trim($system)],
      ['role' => 'user', 'content' => trim($prompt_text)]
    ];

    /*$response = $this->client->chat()->create(
      [
        'model' => $model,
        'messages' => $messages,
        'temperature' => (int) $temperature,
        'max_tokens' => (int) $max_tokens,
      ],
    );
    $result = $response->toArray();*/

    $result = $this->api->chat($model, $messages, $temperature, $max_tokens);
    $Parsedown = new Parsedown();

    $messages[] = [
      'role' => 'assistant',
      //'content' => trim($result["choices"][0]["message"]["content"]),
      'content' => $Parsedown->text($result),
    ];
    $form_state->setStorage(['messages' => $messages]);
    $form_state->setRebuild(TRUE);

    /*
    $this->messenger()->addStatus(trim($result["choices"][0]["message"]["content"]));
    $this->logger()->warning(trim($result["choices"][0]["message"]["content"]));*/
    //$form_state->setRebuild(TRUE);
//who is a best actor?
  }

}
