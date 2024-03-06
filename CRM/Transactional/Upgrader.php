<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Transactional_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   *
  public function postInstall() {
    $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'return' => array("id"),
      'name' => "customFieldCreatedViaManagedHook",
    ));
    civicrm_api3('Setting', 'create', array(
      'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
    ));
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_4700() {
    $this->ctx->log->info('Applying update 4700');
    CRM_Core_DAO::executeQuery('CREATE TABLE `civicrm_recipient_receipt` (
      `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT \'Unique ID\',
      `queue_id` int(10)    COMMENT \'Event Queue id\',
      `receipt_activity_id` int(10)    COMMENT \'Activity id of the receipt.\',
       PRIMARY KEY ( `id` )
    )  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;
    ');

    civicrm_api3('OptionValue', 'create', array(
      'option_group_id' => "activity_type",
      'label' => "Receipt",
      'name' => "ReceiptActivity",
      'description' => "Receipt Sent",
      'icon' => "fa-envelope-o",
    ));
    return TRUE;
  }

  public function upgrade_4701() {
    $this->ctx->log->info('Applying update 4701');
    CRM_Core_DAO::executeQuery('RENAME TABLE  `civicrm_receipient_receipt` TO  `civicrm_recipient_receipt`');
    return TRUE;
  }

  public function upgrade_4702() {
    $this->ctx->log->info('Applying update 4702');
     CRM_Core_DAO::executeQuery('CREATE TABLE `civicrm_transactional_mapping` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `entity_id` int(11) DEFAULT NULL,
      `option_group_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
      `mailing_event_queue_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ');
    return TRUE;
  }

  public function upgrade_4703() {
    Civi::settings()->set('create_activities', TRUE);
    return TRUE;
  }

  public function upgrade_4704() {
    if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_transactional_mapping', 'option_group_name')) {
      CRM_Core_DAO::executeQuery('ALTER TABLE civicrm_transactional_mapping CHANGE option_group_name mailing_name varchar(255)');
      if (CRM_Core_Config::singleton()->logging && CRM_Core_BAO_SchemaHandler::checkIfFieldExists('log_civicrm_transactional_mapping', 'option_group_name')) {
        CRM_Core_DAO::executeQuery('ALTER TABLE log_civicrm_transactional_mapping CHANGE option_group_name mailing_name varchar(255)');
      }
    }
    return TRUE;
  }

}
