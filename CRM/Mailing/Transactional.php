<?php

class CRM_Mailing_Transactional {

  /**
   * Name of the transactional mailing.
   *
   * @var string const
   */
  const MAILING_NAME = 'Transactional Email';

  /**
   * Whether to create separate mailings per sender.
   * e.g. Activity Emaill Sender or Scheduled Reminder Sender, etc.
   *
   * @var bool const
   */
  const BY_SENDER = TRUE;

  /**
   * We only need one instance of this object. So we use the singleton
   * pattern and cache the instance in this variable
   *
   * @var CRM_Mailing_Transactional
   */
  private static $_singleton = NULL;

  /**
   * The presence of any of these query vars in a URL will prevent it from being click tracked.
   *
   * @var array $dont_track_query_vars
   */
  protected $dont_track_query_vars = array('cid', 'cs');

  /**
   * When a form is submitted, the related contact ID is stored here.
   * It is used to identify the correct contact and email IDs to use in VERP headers.
   *
   * @var int $form_contact
   */
  protected $form_contact;

  /**
   * This array holds the mailing and job IDs for all transactional mailings.
   *
   * @var array $mailings
   */
  protected $mailings = array();

  /**
   * Loading transactional mailings from the civicrm_setting table.
   *
   * @return void
   */
  private function __construct() {
    $api = civicrm_api3('Setting', 'getsingle', array(
      'return' => 'transactional_mailings',
    ));
    if (!empty($api['transactional_mailings'])) {
      $this->mailings = $api['transactional_mailings'];
    }
  }

  /**
   * Get the singleton.
   *
   * @return CRM_Mailing_Transactional
   */
  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Mailing_Transactional();
    }
    return self::$_singleton;
  }

  /**
   * This should be called from hook_civicrm_postEmailSend() to mark the message as delivered.
   *
   * @param array $params Mail parameters
   */
  public function delivered($params) {
    if (!isset($params['returnPath'])) {
      return;
    }

    $parts = explode(CRM_Core_Config::singleton()->verpSeparator, $params['returnPath']);
    $delivered = new CRM_Mailing_Event_BAO_Delivered();
    $delivered->event_queue_id = $parts[2];
    $delivered->time_stamp = date('YmdHis');
    $delivered->save();

    // check if an activityId was added in hook_civicrm_alterMailParams
    // if so, update the activity's status and add a target_contact_id
    if (!empty($params['receipt_activity_id'])) {
      $activityParams = array(
        'id' => $params['receipt_activity_id'],
        'subject' => 'Receipt Sent - ' .  CRM_Utils_Array::value('subject', $params),
        'details' => CRM_Utils_Array::value('html', $params),
        'status_id' => 'Completed',
      );
      civicrm_api3('activity', 'create', $activityParams);
    }
  }

  /**
   * Get the contact and email IDs based on the info passed in $params.
   *
   * @param  array $params The params passed to hook_civicrm_alterMailParams.
   * @return array An array containing the derived contact and email IDs.
   */
  public function getContactAndEmailIds($params) {
    // if being sent from a message template, the contactId may already be there
    $contact_id = CRM_Utils_Array::value('contactId', $params);
    if ($contact_id) {
      try {
        $email = civicrm_api3('Email', 'getsingle', array(
          'contact_id' => $contact_id,
          'email' => $params['toEmail'],
          'options' => array(
            'sort' => 'is_primary DESC',
            'limit' => 1,
          ),
        ));
      }
      catch (CiviCRM_API3_Exception $e) {
        $contact_id = NULL;
      }
    }
    if (!$contact_id) {
      // lets see if a form was submitted with a contact that has this email address
      $contact_id = $this->form_contact;
      if ($contact_id) {
        try {
          $email = civicrm_api3('Email', 'getsingle', array(
            'contact_id' => $contact_id,
            'email' => $params['toEmail'],
            'options' => array(
              'sort' => 'is_primary DESC',
              'limit' => 1,
            ),
          ));
        }
        catch (CiviCRM_API3_Exception $e) {
          $contact_id = NULL;
        }
      }
    }
    if (!$contact_id) {
      // now we have to just pick one
      try {
        $email = civicrm_api3('Email', 'getsingle', array(
          'email' => $params['toEmail'],
          'options' => array(
            'sort' => 'is_primary DESC, id DESC',
            'limit' => 1,
          ),
        ));
        $contact_id = $email['contact_id'];
      }
      catch (CiviCRM_API3_Exception $e) {
        $contact_id = NULL;
      }
    }
    return array($contact_id ?: 0, $contact_id ? $email['id'] : 0);
  }

  /**
   * Given the name of a mailing, get the mailing and encompassing job ID.
   * Create them if needed.
   *
   * @param  string $name Name of the mailing.
   * @return array An array containing the mailing ID and the job ID for the requested mailing.
   */
  public function getMailingIds($name) {
    if (!empty($this->mailings[$name])) {
      try {
        //Check if mailing id and job id are valid.
        civicrm_api3('Mailing', 'getsingle', array('id' => $this->mailings[$name]['mailing_id']));
        civicrm_api3('MailingJob', 'getsingle', array('id' => $this->mailings[$name]['job_id']));
      }
      catch (CiviCRM_API3_Exception $e) {
        unset($this->mailings[$name]);
      }
    }
    if (empty($this->mailings[$name])) {
      $api = civicrm_api3('Mailing', 'create', array(
        'sequential' => 1,
        'name' => $name,
        'created_id' => 'user_contact_id',
        'header_id' => NULL,
        'footer_id' => NULL,
        'reply_id' => NULL,
        'unsubscribe_id' => NULL,
        'resubscribe_id' => NULL,
        'optout_id' => NULL,
        'url_tracking' => 1,
        'open_tracking' => 1,
        'is_completed' => 1,
        'override_verp' => 0,
        'visibility' => 'User and User Admin Only',
      ));
      $mailing = $api['values'][0];

      $job = new CRM_Mailing_BAO_MailingJob();
      $job->mailing_id = $mailing['id'];
      $job->status = 'Complete';
      $job->is_test = 0;
      $job->scheduled_date = $job->start_date = $job->end_date = date('YmdHis');
      $job->save();

      $this->mailings[$name] = array(
        'mailing_id' => $mailing['id'],
        'job_id' => $job->id,
      );
      // Save the mailings to the civicrm_setting table.
      civicrm_api3('Setting', 'create', array(
        'transactional_mailings' => $this->mailings,
      ));
    }
    return array_values($this->mailings[$name]);
  }

  /**
   * Determine if the mailing is transactional.
   *
   * @param  int $mailing_id The mailing ID to check.
   * @return boolean Whether the mailing is transactional or not.
   */
  public function isTransactionalMailing($mailing_id) {
    foreach ($this->mailings as $mailing) {
      if ($mailing['mailing_id'] == $mailing_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Set the contact ID of form.
   *
   * @param int $contact_id The contact ID to save for possible check later.
   */
  public function setFormContact($contact_id) {
    $this->form_contact = $contact_id;
  }

  /**
   * Start recording receipt.
   *
   * @param array &$params The params passed to hook_civicrm_alterMailParams.
   */
  public function initiateReceipt(&$params) {
    if (empty($params['receipt_activity_id']) && !empty($params['valueName'])) {
      $valueName = explode('_', $params['valueName']);

      if (!empty($valueName[2]) && $valueName[2] == 'receipt') {
        $activityParams = array(
          'source_contact_id' => CRM_Utils_Array::value('contactId', $params),
          'activity_type_id' => "ReceiptActivity",
          'source_record_id' => CRM_Utils_Array::value('contributionId', $params),
          'status_id' => "Scheduled",
        );
        if (!empty($params['tplParams']) && !empty($params['tplParams']['contactID'])) {
          $activityParams['target_contact_id'] = $params['tplParams']['contactID'];
        }
        else {
          $activityParams['target_contact_id'] = CRM_Utils_Array::value('contactId', $params);
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
   * Set VERP headers so CiviMail will properly process a bounce.
   * Also do what's necessary to report things including open and click tracking.
   *
   * @param  array &$params The params passed to hook_civicrm_alterMailParams.
   * @param  bool $setReturnPath Whether to set the Return-Path. If FALSE, only X-CiviMail-Bounce will be set. Mainly for testing purposes.
   * @return void
   */
  public function verpify(&$params, $setReturnPath = TRUE) {
    $this->initiateReceipt($params);

    list($mailing_id, $job_id) = $this->getMailingIds(self::MAILING_NAME . (self::BY_SENDER ? " ({$params['groupName']})" : ''));
    list($contact_id, $email_id) = $this->getContactAndEmailIds($params);

    if ($job_id && $contact_id && $email_id) {
      $config = CRM_Core_Config::singleton();

      $hash = substr(sha1("{$job_id}:{$email_id}:{$contact_id}:" . time()), 0, 16);
      $api = civicrm_api3('MailingEventQueue', 'create', array(
        'job_id' => $job_id,
        'email_id' => $email_id,
        'contact_id' => $contact_id,
        'hash' => $hash,
      ));
      $event_queue_id = $api['id'];

      // create the VERP header just like CiviMail does
      $localpart = CRM_Core_BAO_MailSettings::defaultLocalpart();
      $emailDomain = CRM_Core_BAO_MailSettings::defaultDomain();

      $bounce = implode($config->verpSeparator,
        array(
          $localpart . 'b',
          $job_id,
          $event_queue_id,
          $hash,
        )
      ) . "@$emailDomain";

      // add the header to the mail params
      if ($setReturnPath) {
        $params['returnPath'] = $bounce;
      }
      if (empty($params['headers'])) {
        $params['headers'] = array();
      }
      $params['headers']['X-CiviMail-Bounce'] = $bounce;

      // update the end date of the job
      $job = new CRM_Mailing_BAO_MailingJob();
      $job->id = $job_id;
      $job->find(TRUE);
      $job->end_date = date('YmdHis');
      $job->save();

      // add the recipient to the mailing
      $recipient = new CRM_Mailing_BAO_Recipients();
      $recipient->mailing_id = $mailing_id;
      $recipient->contact_id = $contact_id;
      $recipient->email_id = $email_id;
      $recipient->save();

      if (!empty($params['receipt_activity_id']) && $event_queue_id) {
        $insertSQL = "INSERT INTO
          civicrm_recipient_receipt (queue_id, receipt_activity_id)
          VALUES ({$event_queue_id}, {$params['receipt_activity_id']})";
        CRM_Core_DAO::executeQuery($insertSQL);
      }
      $entityId = self::getEntityId($params);
      if (!empty($entityId) && !empty($params['groupName']) && !empty($event_queue_id)) {
        CRM_Core_DAO::executeQuery("INSERT INTO civicrm_transactional_mapping
          (entity_id, option_group_name, mailing_event_queue_id) VALUES
          ({$entityId}, '{$params['groupName']}', {$event_queue_id})");
      }

      // open tracking
      $params['html'] = CRM_Utils_Array::value('html', $params, '');
      $params['html'] .= "\n" . '<img src="' . $config->userFrameworkResourceURL .
        "extern/open.php?q=$event_queue_id\" width='1' height='1' alt='' border='0'>";

      // click tracking - it's a little more involved
      // first we find all href attributes in the HTML
      preg_match_all('/href=[\'|"].*?[\'|"]/', $params['html'], $matches);
      $urls = $matches[0];
      $vars = $new = array();

      foreach ($urls as $url) {
        $parts = parse_url(substr($url, 6, -1));
        // don't track things like mailto: and tel:
        if (isset($parts['scheme']) && strpos($parts['scheme'], 'http') === 0) {
          // CiviMail doesn't track URLs that include tokens
          // by time we get the message token replacement has already happend
          // so we decide based on the presence of certain query vars, e.g. contact id or checksum
          if (isset($parts['query'])) {
            parse_str(html_entity_decode($parts['query']), $vars);
          }
          if (!array_intersect($this->dont_track_query_vars, array_keys($vars))) {
            $url = CRM_Mailing_BAO_TrackableURL::getTrackerURL($url, $mailing_id, $event_queue_id);
          }
        }
        $new[] = $url;
      }
      $params['html'] = str_replace($urls, $new, $params['html']);
    }
  }

  /**
   * @param array $params
   *
   * @return integer
   */
  public static function getEntityId($params) {
    if ($params['groupName'] == 'msg_tpl_workflow_case'
      && !empty($params['tplParams']['contact']) && !empty($params['tplParams']['contact']['activity_id'])) {
      return $params['tplParams']['contact']['activity_id'];
    }
    elseif ($params['groupName'] == 'Activity Email Sender') {
      $dao = CRM_Core_DAO::executeQuery("SELECT MAX(id) as activity_id FROM civicrm_activity");
      if ($dao->fetch()) {
        return $dao->activity_id;
      }
    }
    return CRM_Utils_Array::value('entity_id', $params);
  }

}
