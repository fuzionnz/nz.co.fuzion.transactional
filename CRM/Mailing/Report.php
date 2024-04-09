<?php

class CRM_Mailing_Report {

  /**
   * Add entity_id to report columns.
   *
   * @param object $form
   */
  public static function addEntityIdToDetailReport(&$columns, $form) {
    $newCol = [
      'fields' => [
        'entity_id' => [
          'name' => 'entity_id',
          'title' => 'Entity ID',
          'dbAlias' => 'civicrm_transactional_mapping.entity_id',
        ],
      ],
      'filters' => [
        'entity_id' => [
          'name' => 'entity_id',
          'title' => 'Entity ID',
          'type' => CRM_Utils_Type::T_STRING,
          'dbAlias' => 'civicrm_transactional_mapping.entity_id',
        ],
      ],
      'order_bys' => [
        'entity_id' => [
          'name' => 'entity_id',
          'title' => 'Entity ID',
          'dbAlias' => 'civicrm_transactional_mapping.entity_id',
        ],
      ],
    ];
    $columns = self::insertKeyValuePair($columns, 'civicrm_transactional_mapping', $newCol, 2);

    $filters = $form->getVar('_filters');
    $newFilter = [
      'entity_id' => [
        'name' => 'entity_id',
        'title' => 'Entity ID',
        'dbAlias' => 'civicrm_transactional_mapping.entity_id',
      ],
    ];
    $filters = self::insertKeyValuePair($filters, 'civicrm_transactional_mapping', $newFilter, 2);
    $form->setVar('_filters', $filters);
  }

  /**
   * Add/Update column in the report.
   *
   * @param object $var
   */
  public static function alterReportDisplay(&$var) {
    foreach ($var as $key => $value) {
      $tableName = '';
      if (empty($value['civicrm_mailing_mailing_subject']) && str_starts_with($value['civicrm_mailing_mailing_name'], 'Transactional Email')) {
        $mailName = explode("Transactional Email", $value['civicrm_mailing_mailing_name']);
        $transactionalType = trim($mailName[1], "( )");

        if (in_array($transactionalType, ['Scheduled Reminder Sender', 'case_activity', 'Activity Email Sender'])) {
          $dao = CRM_Core_DAO::executeQuery("
            SELECT entity_id
            FROM civicrm_transactional_mapping
            WHERE mailing_event_queue_id = {$value['mailing_queue_id']} AND mailing_name = '{$transactionalType}'"
          );
          if ($dao->fetch()) {
            $tableName = 'civicrm_activity';
            if ($transactionalType == 'Scheduled Reminder Sender') {
              $tableName = 'civicrm_action_schedule';
              if (!empty($var[$key]['civicrm_transactional_mapping_entity_id'])) {
                $var[$key]['civicrm_transactional_mapping_entity_id'] .= " (" . CRM_Core_DAO::singleValueQuery("SELECT title FROM {$tableName} WHERE id = {$dao->entity_id}") . ")";
              }
            }
            $var[$key]['civicrm_mailing_mailing_subject'] = CRM_Core_DAO::singleValueQuery("SELECT subject FROM {$tableName} WHERE id = {$dao->entity_id}");
          }
        }
      }
    }
  }

  /**
   * Alter Report query.
   *
   * @param object $var
   */
  public static function modifyQueryParameters(&$var) {
    $params = $var->getVar('_params');
    $orderBys = array_column($params['order_bys'], 'column');

    if (!empty($params['fields']['entity_id']) || !empty($params['entity_id_value']) || in_array('entity_id', $orderBys)) {
      if (strpos($var->_select, 'civicrm_transactional_mapping.entity_id') == false) {
        $var->_select .= ", civicrm_transactional_mapping.entity_id as civicrm_transactional_mapping_entity_id";
        $var->_selectClauses[] = "civicrm_transactional_mapping.entity_id as civicrm_transactional_mapping_entity_id";

        $selectAliases = $var->getVar('_selectAliases');
        $selectAliases[] = "civicrm_transactional_mapping_entity_id";
        $var->setVar('_selectAliases', $selectAliases);

        $selectTables = $var->getVar('_selectedTables');
        $selectTables[] = "civicrm_transactional_mapping";
        $var->setVar('_selectedTables', $selectTables);
      }
      $from = $var->getVar('_from');
      $from .= "
        LEFT JOIN civicrm_transactional_mapping ON civicrm_mailing_event_queue.id = civicrm_transactional_mapping.mailing_event_queue_id";
      $var->setVar('_from', $from);
    }
    if (!empty($var->_columnHeaders) && empty($var->_columnHeaders['civicrm_transactional_mapping_entity_id']) && !empty($params['fields']['entity_id'])) {
      $var->_columnHeaders['civicrm_transactional_mapping_entity_id'] = [
        'title' => "Entity ID",
      ];
    }

    $var->_columnHeaders['mailing_queue_id'] = [
      'type' => 1,
      'title' => 'Mailing Queue id',
      'no_display' => TRUE,
    ];
    $var->_select .= ", civicrm_mailing_event_queue.id as mailing_queue_id";
  }

  /**
   * Add new key to the columns array.
   *
   * @param array $arr
   * @param string $key
   * @param array $val
   * @param integer $index
   */
  public static function insertKeyValuePair($arr, $key, $val, $index){
    $arrayEnd = array_splice($arr, $index);
    $arrayStart = array_splice($arr, 0, $index);
    return array_merge($arrayStart, [$key => $val], $arrayEnd);
  }

}
