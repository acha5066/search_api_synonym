<?php

namespace Drupal\search_api_synonym\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_synonym\Export\ExportPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SynonymSettingsForm.
 *
 * @package Drupal\search_api_synonym\Form
 *
 * @ingroup search_api_synonym
 */
class SynonymSettingsForm extends ConfigFormBase {

  /**
   * An array containing available export plugins.
   *
   * @var array
   */
  protected $availablePlugins = [];

  /**
   * Constructs a VacancySourceForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ExportPluginManager $manager) {
    parent::__construct($config_factory);

    foreach ($manager->getAvailableExportPlugins() as $id => $definition) {
      $this->availablePlugins[$id] = $manager->createInstance($id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.search_api_synonym.export')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_synonym_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['search_api_synonym.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->getEditableConfigNames()[0]);

    // Add cron settings
    $cron = $config->get('cron');
    $form['cron'] = [
      '#title' => t('Cron settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $options = [];
    foreach ($this->availablePlugins as $id => $source) {
      $definition = $source->getPluginDefinition();
      $options[$id] = $definition['label'];
    }
    $form['cron']['plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Synonym export plugin'),
      '#description' => t('Select the export plugin being used by cron.'),
      '#default_value' => $cron['plugin'] ? $cron['plugin'] : '',
      '#options' => $options,
    ];

    $options = [900, 1800, 3600, 10800, 21600, 43200, 86400, 604800];
    $form['cron']['interval'] = [
      '#type' => 'select',
      '#title' => t('Export synonyms every'),
      '#description' => t('How often should Drupal export synonyms?'),
      '#default_value' => $cron['interval'] ? $cron['interval'] : 86400,
      '#options' => array_map([\Drupal::service('date.formatter'), 'formatInterval'], array_combine($options, $options)),
    ];

    $form['cron']['type'] = [
      '#type' => 'radios',
      '#title' => t('Type'),
      '#description' => t('Which synonym type should be exported by cron?'),
      '#default_value' => $cron['type'] ? $cron['type'] : 'all',
      '#options' => [
        'all' => $this->t('All'),
        'synonym' => $this->t('Synonyms'),
        'spelling_error' => $this->t('Spelling errors')
      ],
    ];

    $form['cron']['filter'] = [
      '#type' => 'radios',
      '#title' => t('Filter'),
      '#description' => t('Which filters should be used when selecting synonyms.'),
      '#default_value' => $cron['filter'] ? $cron['filter'] : 'none',
      '#options' => [
        'none' => $this->t('No filter'),
        'nospace' => $this->t('Synonyms without spaces in the word'),
        'onlyspace' => $this->t('Synonyms with spaces in the word')
      ],
    ];

    $form['cron']['separate_files'] = [
      '#type' => 'checkbox',
      '#title' => t('Separate files'),
      '#description' => t('Export synonyms with and without spaces into separate files.'),
      '#default_value' => $cron['separate_files'] ? $cron['separate_files'] : '',
      '#states' => [
        'visible' => [
          ':radio[name="cron[filter]"]' => ['value' => 'none'],
        ],
      ],
    ];

    $form['cron']['export_if_changed'] = [
      '#type' => 'checkbox',
      '#title' => t('Only export if changes'),
      '#description' => t('Only export synonyms if their is either new or changed synonyms since last export.'),
      '#default_value' => $cron['export_if_changed'] ? $cron['export_if_changed'] : TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->getEditableConfigNames()[0])
      ->set('cron', $form_state->getValue('cron'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
