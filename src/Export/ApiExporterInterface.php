<?php

namespace Drupal\search_api_synonym\Export;

/**
 * Interface for export plugins that export synonyms to an API.
 */
interface ApiExporterInterface {

  /**
   * Export the synonyms to a search backend via API.
   *
   * @param array $data
   *   The synonyms.
   * @param string $export_options
   *   Comma delimited options as given to the plugin_specific drush option.
   */
  public function exportToApi($data, $export_options);

}
