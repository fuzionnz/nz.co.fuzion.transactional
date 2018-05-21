<?php

class CRM_Mailing_Report {

  /**
   * Add entity_id to report columns.
   */
  public static function addEntityIdToDetailReport($form) {
    $columns = $form->getVar('_columns');
    $newCol = array(
      'fields' => array(
        'entity_id' => array(
          'name' => 'entity_id',
          'title' => 'Entity ID',
          'dbAlias' => 'civicrm_transactional_mapping.entity_id',
        )
      ),
      'order_bys' => array(
        'entity_id' => array(
          'name' => 'entity_id',
          'title' => 'Entity ID',
          'dbAlias' => 'civicrm_transactional_mapping.entity_id',
        )
      )
    );
    $columns = self::insertKeyValuePair($columns, 'civicrm_transactional_mapping', $newCol, 2);
    $form->setVar('_columns', $columns);

    $filters = $form->getVar('_filters');
    $newFilter = array(
      'entity_id' => array(
        'name' => 'entity_id',
        'title' => 'Entity ID',
        'dbAlias' => 'civicrm_transactional_mapping.entity_id',
      )
    );
    $filters = self::insertKeyValuePair($filters, 'civicrm_transactional_mapping', $newFilter, 2);
    $form->setVar('_filters', $filters);
  }

  /**
   * Add/Update column in the report.
   */
  public static function alterReportDisplay($var) {
    foreach ($var as $key => $value) {
      $tableName = '';
      if (empty($value['civicrm_mailing_mailing_subject']) && CRM_Utils_String::startsWith($value['civicrm_mailing_mailing_name'], 'Transactional Email')) {
        $mailName = explode("Transactional Email", $value['civicrm_mailing_mailing_name']);
        $transactionalType = trim($mailName[1], "( )");

        if (in_array($transactionalType, array('Scheduled Reminder Sender', 'msg_tpl_workflow_case', 'Activity Email Sender'))) {
          $dao = CRM_Core_DAO::executeQuery("
            SELECT entity_id
            FROM civicrm_transactional_mapping
            WHERE mailing_event_queue_id = {$value['mailing_queue_id']} AND option_group_name = '{$transactionalType}'"
          );
          if ($dao->fetch()) {
            $tableName = ($transactionalType == 'Scheduled Reminder Sender') ? 'civicrm_action_schedule' : 'civicrm_activity';
            $var[$key]['civicrm_mailing_mailing_subject'] = CRM_Core_DAO::singleValueQuery("SELECT subject FROM {$tableName} WHERE id = {$dao->entity_id}");
          }
        }
      }
    }
  }

  /**
   * Alter Report query.
   */
  public static function modifyQueryParameters($var) {
    $params = $var->getVar('_params');
    $orderBys = array_column($params['order_bys'], 'column');

    if (!empty($params['fields']['entity_id']) || !empty($params['entity_id_value']) || in_array('entity_id', $orderBys)) {
      $from = $var->getVar('_from');
      $from .= "
        LEFT JOIN civicrm_transactional_mapping ON civicrm_mailing_event_queue.id = civicrm_transactional_mapping.mailing_event_queue_id";
      $var->setVar('_from', $from);
    }
    if (!empty($params['entity_id_op']) && !empty($params['entity_id_value'])) {
      $where = $var->getVar('_where');
      $where .= "
        AND civicrm_transactional_mapping.entity_id = {$params['entity_id_value']}";
      $var->setVar('_where', $where);
    }

    $var->_columnHeaders['mailing_queue_id'] = array(
      'type' => 1,
      'title' => 'Mailing Queue id',
      'no_display' => TRUE,
    );
    $var->_select .= ", civicrm_mailing_event_queue.id as mailing_queue_id";
  }

  /**
   * Add new key to the columns array.
   */
  public static function insertKeyValuePair($arr, $key, $val, $index){
    $arrayEnd = array_splice($arr, $index);
    $arrayStart = array_splice($arr, 0, $index);
    return array_merge($arrayStart, array($key=>$val), $arrayEnd);
  }

}
