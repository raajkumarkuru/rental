<?php
namespace Drupal\expteche_drush_command\Commands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
/**
 * A ExptecheDrushCommandCommands.
 */
class ExptecheDrushCommandCommands extends DrushCommands {
  /**
   * Command description here.
   *
   * @param $message
   *   Argument description.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option status
   *   status : Site status
   * @usage expteche_drush_command:message Welcome to exptechestatus
   *   Usage description
   *
   * @command expteche_drush_command:message
   * @aliases exp_message
   */
  public function print($message = 'Default message', $options = ['custom' => false]) {
    if ($options['custom']) {
      $this->logger()->log('notice',dt('Implementing Custom code'));
      # code
      return true;
    }
    return $this->logger()->log('success',dt('Message: @msg',['@msg'=>$message]));
  }
}
