<?php
use CRM_Transactional_ExtensionUtil as E;

class CRM_Transactional_BAO_RecipientReceipt extends CRM_Transactional_DAO_RecipientReceipt {

  /**
   * Create a new RecipientReceipt based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Transactional_DAO_RecipientReceipt|NULL
   * */
  public static function create($params) {
    $className = 'CRM_Transactional_DAO_RecipientReceipt';
    $entityName = 'RecipientReceipt';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, $params['id'] ?? NULL, $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

  /**
   * Get Receipt activity ID from event queue id.
   */
  public static function getReceiptActivityFromQueueID($queueID) {
    if (empty($queueID)) {
      return NULL;
    }
    $instance = new CRM_Transactional_DAO_RecipientReceipt();
    $instance->queue_id = $queueID;
    $instance->fetch();
    return $instance->receipt_activity_id;
  }

  /**
   * Start recording receipt.
   *
   * @param array &$params
   *  The params passed to hook_civicrm_alterMailParams.
   */
  public static function initiateReceipt(&$params) {
    if (Civi::settings()->get('invoice_is_email_pdf') && !empty($params['PDFFilename']) && empty($params['contributionId'])) {
      return;
    }
    $workflowName = $params['workflow'] ?? $params['valueName'] ?? NULL;
    if (empty($params['receipt_activity_id']) && !empty($workflowName)) {
      $workflow = explode('_', $workflowName);

      if (!empty($workflow[2]) && $workflow[2] == 'receipt') {
        $activityParams = array(
          'subject' => ts('Receipt Email initiated for %1 template.', [1 => $workflowName]),
          'source_contact_id' => $params['contactId'] ?? NULL,
          'activity_type_id' => "ReceiptActivity",
          'source_record_id' => $params['contributionId'] ?? NULL,
          'status_id' => "Scheduled",
        );
        if (!empty($params['tplParams']) && !empty($params['tplParams']['contactID'])) {
          $activityParams['target_contact_id'] = $params['tplParams']['contactID'];
        }
        else {
          $activityParams['target_contact_id'] = $params['contactId'] ?? NULL;
        }

        $id = CRM_Core_Session::getLoggedInContactID();
        if ($id) {
          $activityParams['source_contact_id'] = $id;
        }
        $result = civicrm_api3('activity', 'create', $activityParams);
        $params['receipt_activity_id'] = $result['id'];
      }
    }
  }

  /**
   * Complete receipt activity created from initiateReceipt().
   *
   * @param array &$params
   */
  public static function completeReceiptActivity($params) {
    $activityParams = [
      'id' => $params['receipt_activity_id'],
      'subject' => 'Receipt Sent - ' .  CRM_Utils_Array::value('subject', $params),
      'details' => $params['html'] ?? NULL,
      'status_id' => 'Completed',
    ];
    civicrm_api3('activity', 'create', $activityParams);
  }

}
