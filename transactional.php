<?php

require_once 'transactional.civix.php';

/**
 * Implements hook_civicrm_alterMailParams().
 *
 * Add bounce headers for non-CiviMail messages.
 *
 * @link https://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterMailParams
 */
function transactional_civicrm_alterMailParams(&$params, $context) {
  if ($context == 'civimail') {
    return;
  }
  CRM_Mailing_Transactional::singleton()->verpify($params);
}

/**
 * Implements hook_civicrm_alterReportVar().
 *
 * @link https://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterReportVar
 */
function transactional_civicrm_alterReportVar($varType, &$var, &$object) {
  if (is_a($object, 'CRM_Report_Form_Mailing_Detail')) {
    if ($varType == 'sql') {
      CRM_Mailing_Report::modifyQueryParameters($var);
    }
    if ($varType == 'rows') {
      CRM_Mailing_Report::alterReportDisplay($var);
    }
    if ($varType == 'columns') {
      CRM_Mailing_Report::addEntityIdToDetailReport($var, $object);
    }
  }
}

/**
 * Implements hook_civicrm_postEmailSend().
 *
 * Mark mail as delivered.
 *
 * @link https://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postEmailSend
 */
function transactional_civicrm_postEmailSend($params) {
  CRM_Mailing_Transactional::singleton()->delivered($params);
}

/**
 * Implements hook_civicrm_alterTemplateFile().
 *
 * Use a different template for transactional mailing reports.
 *
 * @link https://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterTemplateFile
 */
function transactional_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  if ($formName == 'CRM_Mailing_Page_Report' && $context == 'page') {
    if (CRM_Mailing_Transactional::singleton()->isTransactionalMailing($form->_mailing_id)) {
      $tplName = 'CRM/Mailing/Page/Transactional.tpl';
    }
  }
}

/**
 * Implements hook_civicrm_searchColumns().
 *
 * @link https://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_searchColumns
 */
function transactional_civicrm_searchColumns($objectName, &$headers, &$rows, &$selector) {
  if ($objectName == 'mailing' && count($headers) == 3) {
    $headers['activity'] = array('name' => 'Activity');
    $activity = civicrm_api3('OptionValue', 'getsingle', array(
      'return' => array("value"),
      'name' => "ReceiptActivity",
    ));
    foreach ($rows as $queueId => $val) {
      if (!$queueId) {
        continue;
      }
      $activityId = CRM_Core_DAO::singleValueQuery("SELECT receipt_activity_id FROM civicrm_recipient_receipt WHERE queue_id = {$queueId}");
      if ($activityId) {
        $activityURL = CRM_Utils_System::url('civicrm/activity', "atype={$activity['value']}&action=view&reset=1&id=$activityId");
        $rows[$queueId]['activity'] = "<a href='$activityURL' title='Go to Receipt Activity'>Receipt Activity</a>";
      }
    }
  }
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Just stash the contact ID away for later use.
 *
 * @link https://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 */
function transactional_civicrm_buildForm($formName, &$form) {
  $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);
  if (empty($contactId)) {
    $contactId = !empty($form->_contactID) ? $form->_contactID : $form->getLoggedInUserContactID();
  }
  CRM_Mailing_Transactional::singleton()->setFormContact($contactId);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function transactional_civicrm_config(&$config) {
  _transactional_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function transactional_civicrm_xmlMenu(&$files) {
  _transactional_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function transactional_civicrm_install() {
  CRM_Core_DAO::executeQuery('CREATE TABLE IF NOT EXISTS `civicrm_recipient_receipt` (
    `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT \'Unique ID\',
    `queue_id` int(10)    COMMENT \'Event Queue id\',
    `receipt_activity_id` int(10)    COMMENT \'Activity id of the receipt.\',
      PRIMARY KEY ( `id` )
    )  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;
  ');
  CRM_Core_DAO::executeQuery('CREATE TABLE IF NOT EXISTS `civicrm_transactional_mapping` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `entity_id` int(11) DEFAULT NULL,
    `option_group_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
    `mailing_event_queue_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
  ');

  civicrm_api3('OptionValue', 'create', array(
    'option_group_id' => "activity_type",
    'label' => "Receipt",
    'name' => "ReceiptActivity",
    'description' => "Receipt Sent",
    'icon' => "fa-envelope-o",
  ));
  _transactional_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function transactional_civicrm_postInstall() {
  _transactional_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function transactional_civicrm_uninstall() {
  $activity = civicrm_api3('OptionValue', 'getsingle', array(
    'return' => array("id"),
    'name' => "ReceiptActivity",
  ));
  civicrm_api3('OptionValue', 'delete', array(
    'id' => $activity['id'],
  ));
  CRM_Core_DAO::executeQuery("DROP TABLE civicrm_transactional_mapping");
  CRM_Core_DAO::executeQuery("DROP TABLE civicrm_recipient_receipt");
  _transactional_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function transactional_civicrm_enable() {
  _transactional_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function transactional_civicrm_disable() {
  _transactional_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function transactional_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _transactional_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function transactional_civicrm_managed(&$entities) {
  _transactional_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function transactional_civicrm_caseTypes(&$caseTypes) {
  _transactional_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function transactional_civicrm_angularModules(&$angularModules) {
  _transactional_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function transactional_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _transactional_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
