<?php

namespace Drupal\search_api_synonym\Plugin\search_api_synonym\export;

use Drupal\Component\Serialization\Json;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_synonym\Export\ExportPluginBase;
use Drupal\search_api_synonym\Export\ExportPluginInterface;
use Drush\Log\LogLevel;

/**
 * Provides a synonym export plugin for Apache Solr (API).
 *
 * @SearchApiSynonymExport(
 *   id = "solr_api",
 *   label = @Translation("Solr API"),
 *   description = @Translation("Synonym export plugin for Apache Solr (API)")
 * )
 */
class SolrApi extends ExportPluginBase implements ExportPluginInterface {

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
   * Performs export
   */
  public function performExport($data, $export_options) {
    $options = explode(',', $export_options);
    $solr_server = Server::load($options[0]);
    if (!$solr_server) {
      drush_log('Backend not loaded.', LogLevel::ERROR);
      return;
    }

    $solr_uri = $solr_server->getBackend()->getSolrConnector()->getServerLink()->getText();
    $solr_configuration = $solr_server->getBackend()->getSolrConnector()->getConfiguration();

    // Delete the current synonyms
    $solr_synonyms_uri = $solr_uri . $solr_configuration['core'] . '/schema/analysis/synonyms/' . $options['1'];
    $this->resetStoredSynonyms($solr_synonyms_uri);

    // Store new items
    $this->setStoredSynonyms($solr_synonyms_uri, $data);

    // Reload core
    $reload_uri = $solr_uri . 'admin/cores';
    $this->reloadCore($reload_uri, $solr_configuration['core']);

    drush_log('Synonym export finished.', LogLevel::OK);
  }

  /**
   * Fetches the current stored synonyms
   */
  private function resetStoredSynonyms($uri) {
    $client = \Drupal::httpClient();

    // Fetch current items
    $request = $client->get($uri);
    $response = $request->getBody();
    $decoded = Json::decode($response);
    $items = array_keys($decoded['synonymMappings']['managedMap']);

    // Delete current items
    foreach ($items as $item) {
      try {
        $client->delete($uri . '/' . $item);
      }
      catch (Exception $e) {
        drush_log($e->getMessage(), LogLevel::ERROR);
      }
    }
  }

  /**
   * Fills the solr database with new synonyms
   */
  private function setStoredSynonyms($uri, $data) {
    $client = \Drupal::httpClient();

    try {
      $client->put($uri, ['json'    => $data]);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }
  }

  /**
   * Reloads the config
   */
  private function reloadCore($uri, $core) {
    $client = \Drupal::httpClient();

    try {
      $client->get($uri, ['query' => ['action' => 'RELOAD', 'core' => $core]]);
    }
    catch (Exception $e) {
      drush_log($e->getMessage(), LogLevel::ERROR);
    }
  }

}
