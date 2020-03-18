<?php

namespace Drupal\search_api_synonym\Plugin\search_api_synonym\export;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_solr\SolrConnectorInterface;
use Drupal\search_api_synonym\Export\ApiExporterInterface;
use Drupal\search_api_synonym\Export\ExportPluginBase;
use Drupal\search_api_synonym\Export\ExportPluginInterface;
use Drush\Log\LogLevel;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a synonym export plugin for Apache Solr (API).
 *
 * @SearchApiSynonymExport(
 *   id = "solr_api",
 *   label = @Translation("Solr API"),
 *   description = @Translation("Synonym export plugin for Apache Solr (API)")
 * )
 */
class SolrApi extends ExportPluginBase implements ExportPluginInterface, ApiExporterInterface {

  /**
   * The httpClient plugin.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    ConfigFactoryInterface $config_factory,
    Client $httpClient
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);
    $this->httpClient = $httpClient;
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
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedSynonyms(array $synonyms) {
    $elements = [];

    // Generate a line for each synonym.
    foreach ($synonyms as $synonym) {
      if ($synonym->type = 'synonym') {
        $exploded_synonyms = explode(',', $synonym->synonyms);
        $elements[$synonym->word] = $exploded_synonyms;
      }
    }

    return $elements;
  }

  /**
   * {@inheritDoc}
   */
  public function exportToApi($data, $export_options) {
    $options = explode(',', $export_options);
    $solrServer = Server::load($options[0]);
    if (!$solrServer) {
      drush_log('Backend not loaded.', LogLevel::ERROR);
      return;
    }

    /** @var \Drupal\search_api_solr\SolrConnectorInterface $solrConnector */
    $solrConnector = $solrServer->getBackend()->getSolrConnector();

    // Delete the current synonyms.
    $this->resetStoredSynonyms($solrConnector, '/schema/analysis/synonyms/' . $options[1]);

    // Store new items.
    $this->setStoredSynonyms($solrConnector, '/schema/analysis/synonyms/' . $options[1], $data);

    // Reload core.
    $solrConnector->reloadCore();

    drush_log('Synonym export finished.', LogLevel::OK);
  }

  /**
   * Reset stored synonyms.
   */
  private function resetStoredSynonyms(SolrConnectorInterface $solrConnector, $uri) {

    // Fetch current items.
    $response = $solrConnector->coreRestGet($uri);
    $items = array_keys($response['synonymMappings']['managedMap']);

    // There is no delete method in SolrConnectorInterface, so
    // we need to use the drupal client for delete.
    $coreLink = $solrConnector->getCoreLink()->getText();

    // Delete current items.
    foreach ($items as $item) {
      try {
        $this->httpClient->delete($coreLink . '/' . $uri . '/' . $item);
      }
      catch (Exception $e) {
        drush_log($e->getMessage(), LogLevel::ERROR);
      }
    }
  }

  /**
   * Fills the solr database with new synonyms.
   */
  private function setStoredSynonyms(SolrConnectorInterface $solrConnector, $uri, $data) {

    // There is no put method in SolrConnectorInterface, so
    // we need to use the drupal client for put.
    $coreLink = $solrConnector->getCoreLink()->getText();

    try {
      $this->httpClient->put($coreLink . '/' . $uri, ['json' => $data]);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }
  }

}
