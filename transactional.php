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
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function transactional_civicrm_navigationMenu(&$menu) {
  _transactional_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => ts('Transactional Settings'),
    'name' => 'transactional-settings',
    'url' => 'civicrm/transactional/settings',
    'permission' => 'administer CiviCRM',
  ]);
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
 * Do not consider transactional mailing to be displayed
 * on contact summary page (Mailing tab).
 */
function transactional_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  if ($apiRequest['entity'] == 'MailingContact') {
    if ($apiRequest['action'] == 'get') {
      $wrappers[] = new CRM_Mailing_MailingContactAPIWrapper();
    }
    if ($apiRequest['action'] == 'getcount') {
      $wrappers[] = new CRM_Mailing_MailingContactGetCountAPIWrapper();
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
    $headers['activity'] = ['name' => 'Activity'];
    $activity = civicrm_api3('OptionValue', 'getsingle', [
      'return' => ['value'],
      'name' => "ReceiptActivity",
    ]);
    foreach ($rows as $queueId => $val) {
      if (!$queueId) {
        continue;
      }
      $activityId = CRM_Transactional_BAO_RecipientReceipt::getReceiptActivityFromQueueID($queueId);
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
  if (!in_array($formName, [
    'CRM_Contribute_Form_Contribution',
    'CRM_Member_Form_Membership',
    'CRM_Event_Form_Participant',
    'CRM_Contact_Form_Task_Email',
    'CRM_Contribute_Form_Contribution_Main',
    'CRM_Contribute_Form_Contribution_Confirm',
    'CRM_Contribute_Form_Contribution_ThankYou',
    'CRM_Event_Form_Registration_Register',
    'CRM_Event_Form_Registration_Confirm',
    'CRM_Event_Form_Registration_ThankYou',
  ])) {
    return;
  }

  $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);
  if (empty($contactId)) {
    $contactId = !empty($form->_contactID) ? $form->_contactID : CRM_Core_Session::getLoggedInContactID();
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
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function transactional_civicrm_install() {
  Civi::settings()->set('create_activities', TRUE);

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
    `mailing_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
    `mailing_event_queue_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
  ');

  civicrm_api3('OptionValue', 'create', [
    'option_group_id' => "activity_type",
    'label' => "Receipt",
    'name' => "ReceiptActivity",
    'description' => "Receipt Sent",
    'icon' => "fa-envelope-o",
  ]);
  _transactional_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function transactional_civicrm_uninstall() {
  $activity = civicrm_api3('OptionValue', 'getsingle', [
    'return' => ['id'],
    'name' => 'ReceiptActivity',
  ]);
  civicrm_api3('OptionValue', 'delete', [
    'id' => $activity['id'],
  ]);
  CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS civicrm_transactional_mapping");
  CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS civicrm_recipient_receipt");
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function transactional_civicrm_enable() {
  _transactional_civix_civicrm_enable();
}
