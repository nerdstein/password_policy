<?php
/**
 * Created by PhpStorm.
 * User: kris
 * Date: 4/24/15
 * Time: 3:57 PM
 */

namespace Drupal\password_policy\Form;


use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PasswordPolicyConstraintForm extends FormBase {

  /**
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $manager;

  protected $machine_name;

  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.password_policy.password_constraint'));
  }

  function __construct(PluginManagerInterface $manager) {
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'password_policy_constraint_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $machine_name = NULL) {
    $cached_values = $form_state->get('wizard');
    $this->machine_name = $machine_name;
    //drupal_set_message($this->t('Tempstore ID: !test', ['!test' => print_r($cached_values, TRUE)]));
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $constraints = [];
    foreach ($this->manager->getDefinitions() as $plugin_id => $definition) {
      $constraints[$plugin_id] = (string) $definition['title'];
    }
    $form['items'] = array(
      '#type' => 'markup',
      '#prefix' => '<div id="configured-constraints">',
      '#suffix' => '</div>',
      '#theme' => 'table',
      '#header' => array($this->t('Plugin Id'), $this->t('Summary'), $this->t('Operations')),
      '#rows' => $this->renderRows($cached_values),
      '#empty' => t('No constraints have been configured.')
    );
    $form['constraint'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose a constraint'),
      '#options' => $constraints,
    ];
    $form['add'] = [
      '#type' => 'submit',
      '#name' => 'add',
      '#value' => t('Configure Condition'),
      '#ajax' => [
        'callback' => [$this, 'add'],
        'event' => 'click',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  public function add(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->get('wizard');
    $constraint = $form_state->getValue('constraint');
    $content = \Drupal::formBuilder()->getForm('\Drupal\password_policy\Form\ConstraintEdit', $constraint, $cached_values['id']);
    $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand($this->t('Configure Required Context'), $content, array('width' => '700')));
    return $response;
  }

  /**
   * @param $cached_values
   *
   * @return array
   */
  public function renderRows($cached_values) {
    $configured_conditions = array();
    foreach ($cached_values['policy_constraints'] as $row => $constraint) {
      /** @var $instance \Drupal\password_policy\PasswordConstraintInterface */
      $instance = $this->manager->createInstance($constraint['id'], $constraint);
      $build = array(
        '#type' => 'operations',
        '#links' => $this->getOperations('entity.password_policy.constraint', ['machine_name' => $cached_values['id'], 'constraint_id' => $row]),
      );
      $configured_conditions[] = array(
        $instance->getPluginId(),
        '',
        'operations' => [
          'data' => $build,
        ],
      );
    }
    return $configured_conditions;
  }

  protected function getOperations($route_name_base, array $route_parameters = array()) {
    $operations['edit'] = array(
      'title' => t('Edit'),
      'url' => new Url($route_name_base . '.edit', $route_parameters),
      'weight' => 10,
      'attributes' => array(
        'class' => array('use-ajax'),
        'data-accepts' => 'application/vnd.drupal-modal',
        'data-dialog-options' => json_encode(array(
          'width' => 700,
        )),
      ),
    );
    $route_parameters['id'] = $route_parameters['constraint_id'];
    unset($route_parameters['constraint_id']);
    $operations['delete'] = array(
      'title' => t('Delete'),
      'url' => new Url($route_name_base . '.delete', $route_parameters),
      'weight' => 100,
      'attributes' => array(
        'class' => array('use-ajax'),
        'data-accepts' => 'application/vnd.drupal-modal',
        'data-dialog-options' => json_encode(array(
          'width' => 700,
        )),
      ),
    );
    return $operations;
  }

}
